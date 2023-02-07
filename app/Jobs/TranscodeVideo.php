<?php

namespace App\Jobs;

use App\Enums\MediaStorage;
use App\Models\Media;
use App\Models\Version;
use CdnHelper;
use FilePathHelper;
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

    protected string $tempPath;
    protected string $tempPathOnDisk;
    protected string $destinationBasePath;
    protected string $fileName;

    protected const DASH = 'dash';
    protected const HLS  = 'hls';

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
    public function handle(): void
    {
        $this->originalsDisk   = MediaStorage::ORIGINALS->getDisk();
        $this->derivativesDisk = MediaStorage::VIDEO_DERIVATIVES->getDisk();
        $this->s3              = $this->getConfiguredS3();

        $this->transcodeVideo();

        Transcode::callback(true, $this->callbackUrl, $this->idToken, $this->media->User->name, $this->media->identifier, $this->version->number);
    }

    /**
     * Handle a job failure.
     *
     * @param Throwable $exception
     *
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        // Properties are not initialized here.
        $tempPath = $this->getTempPath();

        MediaStorage::VIDEO_DERIVATIVES->getDisk()->deleteDirectory($tempPath);
        MediaStorage::ORIGINALS->getDisk()->delete($this->originalFilePath);
        $this->version->delete();

        Transcode::callback(false, $this->callbackUrl, $this->idToken, $this->media->User->name, $this->media->identifier, $this->version->number - 1);
    }

    /**
     * Return a configuration for S3 used by the PHP-FFmpeg-Streaming package.
     *
     * @return S3
     */
    protected function getConfiguredS3(): S3
    {
        $config = [
            'version'     => 'latest',
            'region'      => config('transmorpher.aws.region'),
            'credentials' => [
                'key'    => config('transmorpher.aws.key'),
                'secret' => config('transmorpher.aws.secret'),
            ],
        ];

        return new S3($config);
    }

    /**
     * Initiates all steps necessary to transcode a video.
     *
     * @return void
     */
    protected function transcodeVideo(): void
    {
        $ffmpeg = FFMpeg::create();
        $video  = $this->openVideo($ffmpeg);

        // Set necessary file path information.
        $this->setFilePaths();

        $this->generateHls($video);
        $this->generateDash($video);

        // Derivatives are generated at this point of time and located in the temporary folder.
        $this->moveToDestinationPath();
    }

    /**
     * Open a video to use it with FFmpeg.
     *
     * @param FFMpeg $ffmpeg
     *
     * @return StreamingMedia
     */
    protected function openVideo(FFMpeg $ffmpeg): StreamingMedia
    {
        return $this->isLocalFilesystem($this->originalsDisk) ?
            $ffmpeg->open($this->originalsDisk->path($this->originalFilePath))
            : $this->openFromCloud($ffmpeg);
    }

    /**
     * Checks whether the used disk is a local disk.
     *
     * @param Filesystem $disk
     *
     * @return bool
     */
    protected function isLocalFilesystem(Filesystem $disk): bool
    {
        return $disk->getAdapter() instanceof LocalFilesystemAdapter;
    }

    /**
     * Opens a video from the cloud.
     *
     * @param FFMpeg $ffmpeg
     *
     * @return StreamingMedia
     */
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

    /**
     * Sets the file name and file paths which are needed for the transcoding process.
     *
     * @return void
     */
    protected function setFilePaths(): void
    {
        [, $this->fileName]        = explode('-', pathinfo($this->originalFilePath, PATHINFO_FILENAME), 2);
        $this->destinationBasePath = FilePathHelper::getVideoDerivativeBasePath($this->media->User, $this->media->identifier);
        $this->tempPath            = $this->getTempPath();
        $this->tempPathOnDisk      = $this->derivativesDisk->path($this->tempPath);
    }

    /**
     * Returns a path used to temporarily store video derivatives.
     *
     * @return string
     */
    protected function getTempPath(): string
    {
        return sprintf('%s-%d-temp', FilePathHelper::getVideoDerivativeBasePath($this->media->User, $this->media->identifier), $this->version->number);
    }

    /**
     * Generates HLS video representations.
     *
     * @param StreamingMedia $video
     *
     * @return void
     */
    protected function generateHls(StreamingMedia $video): void
    {
        $video = $video->hls()
            ->x264()
            ->autoGenerateRepresentations(config('transmorpher.representations'));

        $this->saveVideo($video, static::HLS);
    }

    /**
     * Generates DASH video representations.
     *
     * @param StreamingMedia $video
     *
     * @return void
     */
    protected function generateDash(StreamingMedia $video): void
    {
        $video = $video->dash()
            ->x264()
            ->autoGenerateRepresentations(config('transmorpher.representations'));

        $this->saveVideo($video, static::DASH);
    }

    /**
     * Saves the transcoded video to storage.
     *
     * @param Streaming $video
     * @param string    $format
     *
     * @return void
     */
    protected function saveVideo(Streaming $video, string $format): void
    {
        // Save to temporary folder first, to prevent race conditions when multiple versions are uploaded simultaneously.
        $this->isLocalFilesystem($this->derivativesDisk) ?
            $video->save(FilePathHelper::getVideoDerivativePath($this->tempPathOnDisk, $format, $this->fileName))
            : $this->saveToCloud($video, $format);
    }

    /**
     * Saves the transcoded video to a cloud storage.
     *
     * @param Streaming $video
     * @param string    $format
     *
     * @return void
     */
    protected function saveToCloud(Streaming $video, string $format): void
    {
        $toS3 = [
            'cloud'   => $this->s3,
            'options' => [
                'dest'     => sprintf('s3://%s/%s/%s',
                    config('filesystems.disks.s3VideoDerivatives.bucket'),
                    $this->tempPathOnDisk,
                    $format
                ),
                'filename' => $this->fileName,
            ],
        ];

        $video->save(null, $toS3);
    }

    /**
     * Handles the move from the temporary path to the destination path.
     *
     * @return void
     */
    protected function moveToDestinationPath(): void
    {
        if ($this->version->number === $this->media->Versions()->max('number')) {
            $this->derivativesDisk->deleteDirectory($this->destinationBasePath);
            $this->moveFromTempDirectory();
            $this->invalidateCdnCache();

            $this->version->update(['processed' => true]);
        } else {
            $this->derivativesDisk->deleteDirectory($this->tempPath);
        }
    }

    /**
     * Moves the transcoded video from the temporary path to the destination path.
     *
     * @return void
     */
    protected function moveFromTempDirectory(): void
    {
        if ($this->isLocalFilesystem($this->derivativesDisk)) {
            $this->derivativesDisk->move($this->tempPath, $this->destinationBasePath);
        } else {
            $this->moveFromCloudTempDirectory();
        }
    }

    /**
     * Moves files one by one.
     * S3 can't move multiple files at once.
     *
     * @return void
     */
    protected function moveFromCloudTempDirectory(): void
    {
        $hlsFiles  = $this->derivativesDisk->allFiles(sprintf('%s/%s/', $this->tempPath, static::HLS));
        $dashFiles = $this->derivativesDisk->allFiles(sprintf('%s/%s/', $this->tempPath, static::DASH));

        foreach ($hlsFiles as $file) {
            $this->derivativesDisk->move($file, FilePathHelper::getVideoDerivativePath($this->destinationBasePath, static::HLS, basename($file)));
        }

        foreach ($dashFiles as $file) {
            $this->derivativesDisk->move($file, FilePathHelper::getVideoDerivativePath($this->destinationBasePath, static::DASH, basename($file)));
        }
    }

    /**
     * Invalidates the CDN cache.
     *
     * @return void
     */
    protected function invalidateCdnCache(): void
    {
        // If this fails, the 'failed()'-method will handle the cleanup.
        if (CdnHelper::isConfigured()) {
            CdnHelper::invalidate(sprintf('/%s/*', $this->derivativesDisk->path($this->destinationBasePath)));
        }
    }
}
