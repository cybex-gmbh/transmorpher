<?php

namespace App\Jobs;

use App\Enums\MediaStorage;
use App\Models\Media;
use App\Models\Version;
use CdnHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Streaming\Clouds\S3;
use Streaming\FFMpeg;
use Streaming\Media as StreamingMedia;
use Streaming\Streaming;
use Throwable;
use Transcode;

class TranscodeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Filesystem $originalsDisk;
    protected Filesystem $derivativesDisk;
    protected S3         $s3;

    protected string $tempDestinationPath;
    protected string $tempPathOnStorage;
    protected string $destinationPathOnStorage;
    protected string $destinationPath;
    protected string $fileName;

    protected const DASH = 'dash';
    protected const HLS = 'hls';

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected string  $originalFilePath,
        protected Media   $media,
        protected Version $version,
        protected string  $callbackUrl,
        protected string  $idToken,
    )
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->originalsDisk   = MediaStorage::ORIGINALS->getDisk();
        $this->derivativesDisk = MediaStorage::VIDEO_DERIVATIVES->getDisk();

        $config = [
            'version'     => 'latest',
            'region'      => 'eu-central-1',
            'credentials' => [
                'key'    => config('transmorpher.aws.key'),
                'secret' => config('transmorpher.aws.secret'),
            ],
        ];

        $this->s3 = new S3($config);

        $this->transcodeVideo();

        Transcode::callback(true, $this->callbackUrl, $this->idToken);
    }

    /**
     * Handle a job failure.
     *
     * @param Throwable $exception
     *
     * @return void
     */
    public function failed(Throwable $exception)
    {
        // Properties are not initialized here.
        $tempPathOnStorage = sprintf('derivatives/videos/%s/%s-%d-temp', $this->media->User->name, $this->media->identifier, $this->version->number);

        MediaStorage::VIDEO_DERIVATIVES->getDisk()->deleteDirectory($tempPathOnStorage);
        MediaStorage::ORIGINALS->getDisk()->delete($this->originalFilePath);
        $this->version->delete();

        Transcode::callback(false, $this->callbackUrl, $this->idToken);
    }

    protected function transcodeVideo()
    {
        $ffmpeg = FFMpeg::create();
        $video  = $this->openVideo($ffmpeg);

        // TODO Look into extracting this into the FilePathHelper.
        [, $this->fileName]    = explode('-', pathinfo($this->originalFilePath, PATHINFO_FILENAME), 2);

        $this->destinationPathOnStorage = sprintf('derivatives/videos/%s/%s', $this->media->User->name, $this->media->identifier);
        $this->destinationPath          = $this->derivativesDisk->path($this->destinationPathOnStorage);

        $this->tempPathOnStorage =
            sprintf('derivatives/videos/%s/%s-%d-temp', $this->media->User->name, $this->media->identifier, $this->version->number);
        $this->tempDestinationPath = $this->derivativesDisk->path($this->tempPathOnStorage);

        $this->generateHls($video);
        $this->generateDash($video);

        // Derivatives are generated at this point and located in the temporary folder.
        if ($this->version->is(Version::whereNumber($this->media->Versions()->max('number'))->first())) {
            $this->derivativesDisk->deleteDirectory($this->destinationPathOnStorage);
            $this->moveFromTempDirectory();
        } else {
            $this->derivativesDisk->deleteDirectory($this->tempPathOnStorage);
        }

        if (CdnHelper::isConfigured()) {
            CdnHelper::createInvalidation(sprintf('%s/*', $this->destinationPath));
        }
    }

    protected function openVideo(FFMpeg $ffmpeg): StreamingMedia
    {
        return $this->isLocalFilesystem($this->originalsDisk) ?
            $ffmpeg->open($this->originalsDisk->path($this->originalFilePath))
            : $this->openFromCloud($ffmpeg);
    }

    protected function openFromCloud(FFmpeg $ffmpeg): StreamingMedia
    {
        $fromS3 = [
            'cloud'   => $this->s3,
            'options' => [
                'Bucket' => config('filesystems.disks.s3Main.bucket'),
                'Key'    => $this->originalsDisk->path($this->originalFilePath),
            ],
        ];

        return $ffmpeg->openFromCloud($fromS3);
    }

    protected function generateHls(StreamingMedia $video)
    {
        $video = $video->hls()
            ->x264()
            ->autoGenerateRepresentations(config('transmorpher.representations'));

        $this->saveVideo($video, self::HLS);
    }

    protected function generateDash(StreamingMedia $video)
    {
        $video = $video->dash()
            ->x264()
            ->autoGenerateRepresentations(config('transmorpher.representations'));

        $this->saveVideo($video, self::DASH);
    }

    protected function saveVideo(Streaming $video, string $format): void
    {
        // Save to temporary folder first, to prevent race conditions when multiple versions are uploaded at simultaneously.
        $this->isLocalFilesystem($this->derivativesDisk) ?
            $video->save(sprintf('%s/%s/%s', $this->tempDestinationPath, $format, $this->fileName))
            : $this->saveToCloud($video, $format);
    }

    protected function saveToCloud(Streaming $video, string $format)
    {
        $toS3 = [
            'cloud'   => $this->s3,
            'options' => [
                'dest'     => sprintf('s3://%s/%s/%s',
                    config('filesystems.disks.s3VideoDerivatives.bucket'),
                    $this->tempDestinationPath,
                    $format
                ),
                'filename' => $this->fileName,
            ],
        ];

        $video->save(null, $toS3);
    }

    /**
     * @param Filesystem $disk
     *
     * @return bool
     */
    protected function isLocalFilesystem(Filesystem $disk): bool
    {
        return $disk->getAdapter() instanceof LocalFilesystemAdapter;
    }

    protected function invalidateCdnCache()
    {
        // TODO Do this.
        // TODO Maybe extract logic from ImageController.
    }

    protected function moveFromTempDirectory()
    {
        if ($this->isLocalFilesystem($this->derivativesDisk)) {
            $this->derivativesDisk->move($this->tempPathOnStorage, $this->destinationPathOnStorage);
        } else {
            $this->moveCloudTempDirectory();
        }
    }

    /**
     * Moves files one by one.
     * S3 can't move multiple files at once.
     *
     * @return void
     */
    protected function moveCloudTempDirectory()
    {
        $hlsFiles = $this->derivativesDisk->allFiles(sprintf('%s/%s/', $this->tempPathOnStorage, self::HLS));
        $dashFiles = $this->derivativesDisk->allFiles(sprintf('%s/%s/', $this->tempPathOnStorage, self::DASH));

        foreach($hlsFiles as $file)
        {
            $this->derivativesDisk->move($file, sprintf('%s/%s/%s', $this->destinationPathOnStorage, self::HLS, basename($file)));
        }

        foreach($dashFiles as $file)
        {
            $this->derivativesDisk->move($file, sprintf('%s/%s/%s', $this->destinationPathOnStorage, self::DASH, basename($file)));
        }
    }

    // TODO Cleanup this mess.
    // TODO Add PhpDoc everywhere.
}
