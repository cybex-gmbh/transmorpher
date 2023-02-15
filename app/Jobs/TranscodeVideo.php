<?php

namespace App\Jobs;

use App\Enums\MediaStorage;
use App\Enums\StreamingFormat;
use App\Models\Media;
use App\Models\Version;
use CdnHelper;
use CloudStorage;
use FFMpeg\Format\Video\X264;
use FilePathHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Storage;
use Streaming\FFMpeg;
use Streaming\Media as StreamingMedia;
use Streaming\Streaming;
use Throwable;
use Transcode;

class TranscodeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
    * The number of times the job may be attempted.
    *
    * @var int
    */
    public int $tries = 1;

    /**
    * The number of seconds the job can run before timing out.
    *
    * @var int
    */
    public int $timeout = 600;

    protected Filesystem $originalsDisk;
    protected Filesystem $derivativesDisk;
    protected Filesystem $localDisk;

    protected string $tempPath;
    protected string $tempMp4FileName;
    protected string $tempLocalOriginal;
    protected string $destinationBasePath;
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
        $this->onQueue('video-transcoding');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->originalsDisk     = MediaStorage::ORIGINALS->getDisk();
        $this->derivativesDisk   = MediaStorage::VIDEO_DERIVATIVES->getDisk();
        $this->localDisk         = Storage::disk('local');
        $this->tempMp4FileName   = $this->getTempMp4FileName();
        $this->tempLocalOriginal = $this->getTempLocalOriginal();

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
        $tempPath  = FilePathHelper::toTempVideoDerivativesDirectory($this->media->User, $this->media->identifier, $this->version->number);
        $localDisk = Storage::disk('local');

        MediaStorage::VIDEO_DERIVATIVES->getDisk()->deleteDirectory($tempPath);
        MediaStorage::ORIGINALS->getDisk()->delete($this->originalFilePath);
        $localDisk->delete($this->getTempMp4FileName());
        $localDisk->delete($this->getTempLocalOriginal());
        $this->version->delete();

        Transcode::callback(false, $this->callbackUrl, $this->idToken, $this->media->User->name, $this->media->identifier, $this->version->number - 1);
    }

    /**
     * Initiates all steps necessary to transcode a video.
     *
     * @return void
     */
    protected function transcodeVideo(): void
    {
        $ffmpeg = FFMpeg::create();
        $video  = $this->loadVideo($ffmpeg);

        // Set necessary file path information.
        $this->setFilePaths();

        // Generate MP4. Has to be saved locally first since the php-ffmpeg-video-streaming library only offers cloud support for DASH and HLS.
        $this->generateMp4($video);
        // Generate HLS
        $this->saveVideo(StreamingFormat::HLS->configure($video), StreamingFormat::HLS->value);
        // Generate DASH
        $this->saveVideo(StreamingFormat::DASH->configure($video), StreamingFormat::DASH->value);
        $this->localDisk->delete($this->tempLocalOriginal);

        // Derivatives are generated at this point of time and located in the temporary folder.
        $this->moveToDestinationPath();
    }

    /**
     * Load a video to use it with FFmpeg.
     *
     * @param FFMpeg $ffmpeg
     *
     * @return StreamingMedia
     */
    protected function loadVideo(FFMpeg $ffmpeg): StreamingMedia
    {
        return $this->isLocalFilesystem($this->originalsDisk) ?
            $ffmpeg->open($this->originalsDisk->path($this->originalFilePath))
            : $ffmpeg->openFromCloud(
                CloudStorage::getOpenConfiguration($this->originalsDisk->path($this->originalFilePath)),
                $this->localDisk->path($this->tempLocalOriginal)
            );
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
     * Sets the file name and file paths which are needed for the transcoding process.
     *
     * @return void
     */
    protected function setFilePaths(): void
    {
        $this->destinationBasePath = FilePathHelper::toBaseDirectory($this->media->User, $this->media->identifier);
        $this->tempPath            = FilePathHelper::toTempVideoDerivativesDirectory($this->media->User, $this->media->identifier, $this->version->number);
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
            $video->save($this->derivativesDisk->path(FilePathHelper::toTempVideoDerivativeFile($this->media->User, $this->media->identifier, $this->version->number, $format)))
            : $video->save(null,
                CloudStorage::getSaveConfiguration(
                    sprintf('%s/%s', $this->derivativesDisk->path($this->tempPath), $format), $this->media->identifier
                )
            );
    }

    /**
     * Generates MP4 video.
     * The php-ffmpeg-video-streaming package only offers support for DASH and HLS.
     * Therefore, the basic PHP FFmpeg package has to be used which means the mp4 file has to be saved locally first, since saving directly to cloud is not supported.
     *
     * @param StreamingMedia $video
     *
     * @return void
     */
    protected function generateMp4(StreamingMedia $video): void
    {
        $video->save(new X264(), $this->localDisk->path($this->tempMp4FileName));

        $derivativePath = FilePathHelper::toTempVideoDerivativeFile($this->media->User, $this->media->identifier, $this->version->number, 'mp4');
        $this->derivativesDisk->writeStream(
            sprintf('%s.%s', $derivativePath, 'mp4'),
            $this->localDisk->readStream($this->tempMp4FileName)
        );

        $this->localDisk->delete($this->tempMp4FileName);
    }

    /**
     * Handles the move from the temporary path to the destination path.
     *
     * @return void
     */
    protected function moveToDestinationPath(): void
    {
        if ($this->version->number === $this->media->Versions()->max('number')) {
            // This will make sure we can invalidate the cache before the current derivative gets deleted.
            // If this fails, the job will stop and cleanup will be done in the failed() method.
            $this->invalidateCdnCache();

            $this->derivativesDisk->deleteDirectory($this->destinationBasePath);
            $this->moveFromTempDirectory();

            // Invalidate the cache again for the newly generated derivative.
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
        $hlsFiles   = $this->derivativesDisk->allFiles(sprintf('%s/%s/', $this->tempPath, StreamingFormat::HLS->value));
        $dashFiles  = $this->derivativesDisk->allFiles(sprintf('%s/%s/', $this->tempPath, StreamingFormat::DASH->value));
        $user       = $this->media->User;
        $identifier = $this->media->identifier;

        foreach ($hlsFiles as $file) {
            $this->derivativesDisk->move($file, FilePathHelper::toVideoDerivativeFile($user, $identifier, StreamingFormat::HLS->value, basename($file)));
        }

        foreach ($dashFiles as $file) {
            $this->derivativesDisk->move($file, FilePathHelper::toVideoDerivativeFile($user, $identifier, StreamingFormat::DASH->value, basename($file)));
        }

        $tempDerivativePath = FilePathHelper::toTempVideoDerivativeFile($this->media->User, $this->media->identifier, $this->version->number, 'mp4');
        // Move MP4 file.
        $this->derivativesDisk->move(
            sprintf('%s.%s', $tempDerivativePath, 'mp4'),
            sprintf('%s.%s', FilePathHelper::toVideoDerivativeFile($user, $identifier, 'mp4'), 'mp4')
        );
    }

    /**
     * Invalidates the CDN cache.
     *
     * @return void
     */
    protected function invalidateCdnCache(): void
    {
        if (CdnHelper::isConfigured()) {
            // If this fails, the 'failed()'-method will handle the cleanup.
            CdnHelper::invalidate(sprintf('/%s/*', $this->derivativesDisk->path($this->destinationBasePath)));
        }
    }

    /**
     * @return string
     */
    protected function getTempMp4FileName(): string
    {
        return sprintf('temp-derivative-%s-%d.mp4', $this->media->identifier, $this->version->number);
    }

    /**
     * @return string
     */
    protected function getTempLocalOriginal(): string
    {
        return sprintf('temp-original-%s-%d', $this->media->identifier, $this->version->number);
    }
}
