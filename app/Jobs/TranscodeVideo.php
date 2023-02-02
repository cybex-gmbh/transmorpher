<?php

namespace App\Jobs;

use App\Enums\MediaStorage;
use App\Models\Media;
use App\Models\Version;
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
use Transcoder;

class TranscodeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Filesystem $originalsDisk;
    protected Filesystem $derivativesDisk;
    protected S3         $s3;

    // TODO Implement this logic.
    protected string $tempDestinationPath;
    protected string $destinationPath;
    protected string $fileName;

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

        Transcoder::callback(true, $this->callbackUrl, $this->idToken);
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
        // TODO Delete temp directory, if job failed then the temp directory still exists and transcoding stopped while processing.
        MediaStorage::ORIGINALS->getDisk()->delete($this->originalFilePath);
        $this->version->delete();

        Transcoder::callback(false, $this->callbackUrl, $this->idToken);
    }

    protected function transcodeVideo()
    {
        $ffmpeg = FFMpeg::create();
        $video  = $this->openVideo($ffmpeg);

        // TODO Look into extracting this into the FilePathHelper.
        [, $this->fileName]    = explode('-', pathinfo($this->originalFilePath, PATHINFO_FILENAME), 2);
        $this->destinationPath = $this->derivativesDisk->path(
            sprintf('derivatives/videos/%s/%s/hls/', $this->media->User->name, $this->media->identifier));

        $this->generateHls($video);
        $this->fail(new \Exception());
        $this->generateDash($video);

        $this->invalidateCdnCache();

        // TODO Move from temp folder to current folder (delete all files from current folder).
        // TODO Check if my version is still the current version, if not just delete the folder.
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

        $this->saveVideo($video);
    }

    protected function generateDash(StreamingMedia $video)
    {
        $video = $video->dash()
            ->x264()
            ->autoGenerateRepresentations(config('transmorpher.representations'));

        $this->saveVideo($video);
    }

    protected function saveVideo(Streaming $video): void
    {
        // TODO actually save to local folder first
        $this->isLocalFilesystem($this->derivativesDisk) ?
            $video->save(sprintf('%s/%s', $this->destinationPath, $this->fileName))
            : $this->saveToCloud($video);
    }

    protected function saveToCloud(Streaming $video)
    {
        // TODO actually save to local folder first
        $toS3 = [
            'cloud'   => $this->s3,
            'options' => [
                'dest'     => sprintf('s3://%s/%s', config('filesystems.disks.s3VideoDerivatives.bucket'), $this->destinationPath),
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

    // TODO Cleanup this mess.
    // TODO Add PhpDoc everywhere.
}
