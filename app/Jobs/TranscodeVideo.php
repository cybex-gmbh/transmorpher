<?php

namespace App\Jobs;

use App\Enums\MediaStorage;
use App\Enums\ResponseState;
use App\Enums\StreamingFormat;
use App\Models\UploadSlot;
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
    public int $timeout = 60 * 60 * 3;

    protected Filesystem $originalsDisk;
    protected Filesystem $derivativesDisk;
    protected Filesystem $localDisk;

    protected string $callbackUrl;
    protected string $uploadToken;

    // Derivatives are saved to a temporary folder first, else race conditions could cause newer versions to be overwritten.
    protected string $tempDerivativesDirectoryPath;
    // Mp4 needs to be saved locally after transcoding, before being streamed to the configured disk.
    protected string $tempMp4Filename;
    // Videos stored in the cloud have to be downloaded for transcoding.
    protected string $tempOriginalFilename;
    protected string $derivativesDestinationPath;

    protected ResponseState $responseState;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected string $originalFilePath,
        protected Version $version,
        protected UploadSlot $uploadSlot,
        protected ?int $oldVersionNumber = null,
        protected ?bool $wasProcessed = null
    )
    {
        $this->onQueue('video-transcoding');
        $this->callbackUrl = $this->uploadSlot->callback_url;
        $this->uploadToken = $this->uploadSlot->token;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // Check for newer versions and validity of upload slot.
        if ($this->isMostRecentVersion()) {
            $this->originalsDisk = MediaStorage::ORIGINALS->getDisk();
            $this->derivativesDisk = MediaStorage::VIDEO_DERIVATIVES->getDisk();
            $this->localDisk = Storage::disk('local');
            $this->tempMp4Filename = $this->getTempMp4Filename();
            $this->tempOriginalFilename = $this->getTempOriginalFilename();

            $this->transcodeVideo();
        } else {
            $this->responseState = ResponseState::TRANSCODING_ABORTED;
        }

        match ($this->responseState) {
            ResponseState::TRANSCODING_SUCCESSFUL => Transcode::callback($this->responseState, $this->callbackUrl, $this->uploadToken, $this->version->Media, $this->version->number),
            ResponseState::TRANSCODING_ABORTED => $this->failed(null),
        };
    }

    /**
     * Handle a job failure.
     *
     * @param Throwable|null $exception
     * @return void
     */
    public function failed(?Throwable $exception): void
    {
        // All properties have not yet been initialized, because failed jobs use a new instance.

        $tempDerivativesDirectoryPath = FilePathHelper::toTempVideoDerivativesDirectory($this->version);
        $localDisk = Storage::disk('local');

        MediaStorage::VIDEO_DERIVATIVES->getDisk()->deleteDirectory($tempDerivativesDirectoryPath);
        $localDisk->delete($this->getTempMp4Filename());
        $localDisk->delete($this->getTempOriginalFilename());
        $localDisk->deleteDirectory($this->getFfmpegTempDirectory());

        if (!$this->oldVersionNumber) {
            // A failed upload must not create a version.
            $this->version->delete();
            $versionNumber = $this->version->number - 1;
        } else {
            // Restoring an old version has failed. It will not get a higher version number, but keep its old one.
            $this->version->update(['number' => $this->oldVersionNumber, 'processed' => $this->wasProcessed]);
            $versionNumber = $this->oldVersionNumber;
        }

        Transcode::callback($this->responseState ?? ResponseState::TRANSCODING_FAILED, $this->callbackUrl, $this->uploadToken, $this->version->Media, $versionNumber);
    }

    /**
     * Initiates all steps necessary to transcode a video.
     *
     * @return void
     */
    protected function transcodeVideo(): void
    {
        $ffmpeg = FFMpeg::create([
            'timeout' => $this->timeout,
            'temporary_directory' => $this->localDisk->path($this->getFfmpegTempDirectory())
        ]);

        $video = $this->loadVideo($ffmpeg);

        // Set the necessary file path information.
        $this->setFilePaths();

        // Generate MP4.
        $this->generateMp4($video);
        // Generate HLS
        $this->saveVideo(StreamingFormat::HLS->configure($video), StreamingFormat::HLS->value);
        // Generate DASH
        $this->saveVideo(StreamingFormat::DASH->configure($video), StreamingFormat::DASH->value);

        $this->localDisk->delete($this->tempOriginalFilename);
        $this->localDisk->deleteDirectory($this->getFfmpegTempDirectory());

        // Derivatives are generated at this point of time and located in the temporary folder.
        $this->moveDerivativesToDestinationPath();
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
                $this->localDisk->path($this->tempOriginalFilename)
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
        $this->derivativesDestinationPath = FilePathHelper::toBaseDirectory($this->version->Media);
        $this->tempDerivativesDirectoryPath = FilePathHelper::toTempVideoDerivativesDirectory($this->version);
    }

    /**
     * Saves the transcoded video to storage.
     *
     * @param Streaming $video
     * @param string $format
     *
     * @return void
     */
    protected function saveVideo(Streaming $video, string $format): void
    {
        // Save to temporary folder first, to prevent race conditions when multiple versions are uploaded simultaneously.
        $this->isLocalFilesystem($this->derivativesDisk) ?
            $video->save($this->derivativesDisk->path(FilePathHelper::toTempVideoDerivativeFile($this->version, $format)))
            : $video->save(null,
                CloudStorage::getSaveConfiguration(
                    sprintf('%s/%s', $this->derivativesDisk->path($this->tempDerivativesDirectoryPath), $format), $this->version->Media->identifier
                )
            );
    }

    /**
     * Generates MP4 video.
     * The basic PHP-FFmpeg package is used for this, since the php-ffmpeg-video-streaming package only offers support for DASH and HLS.
     * Therefore, the mp4 file has to be saved locally first, since saving directly to cloud is not supported.
     *
     * @param StreamingMedia $video
     *
     * @return void
     */
    protected function generateMp4(StreamingMedia $video): void
    {
        $video->save((new X264())->setAdditionalParameters(config('transmorpher.additional_transcoding_parameters')), $this->localDisk->path($this->tempMp4Filename));

        $derivativePath = FilePathHelper::toTempVideoDerivativeFile($this->version, 'mp4');
        $this->derivativesDisk->writeStream(
            sprintf('%s.%s', $derivativePath, 'mp4'),
            $this->localDisk->readStream($this->tempMp4Filename)
        );

        $this->localDisk->delete($this->tempMp4Filename);
    }

    /**
     * Handles the move from the temporary path to the destination path.
     *
     * @return void
     */
    protected function moveDerivativesToDestinationPath(): void
    {
        // Check for newer versions and validity of upload slot.
        if ($this->isMostRecentVersion()) {
            // This will make sure we can invalidate the cache before the current derivative gets deleted.
            // If this fails, the job will stop and cleanup will be done in the 'failed()'-method.
            $this->invalidateCdnCache();

            $this->derivativesDisk->deleteDirectory($this->derivativesDestinationPath);
            $this->moveFromTempDirectory();

            // Invalidate the cache again for the newly generated derivative.
            $this->invalidateCdnCache();

            $this->version->update(['processed' => true]);
            $this->responseState = ResponseState::TRANSCODING_SUCCESSFUL;
        } else {
            $this->responseState = ResponseState::TRANSCODING_ABORTED;
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
            $this->derivativesDisk->move($this->tempDerivativesDirectoryPath, $this->derivativesDestinationPath);
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
        $hlsFiles = $this->derivativesDisk->allFiles(sprintf('%s/%s/', $this->tempDerivativesDirectoryPath, StreamingFormat::HLS->value));
        $dashFiles = $this->derivativesDisk->allFiles(sprintf('%s/%s/', $this->tempDerivativesDirectoryPath, StreamingFormat::DASH->value));

        foreach ($hlsFiles as $file) {
            $this->derivativesDisk->move($file, FilePathHelper::toVideoDerivativeFile($this->version->Media, StreamingFormat::HLS->value, basename($file)));
        }

        foreach ($dashFiles as $file) {
            $this->derivativesDisk->move($file, FilePathHelper::toVideoDerivativeFile($this->version->Media, StreamingFormat::DASH->value, basename($file)));
        }

        $tempDerivativePath = FilePathHelper::toTempVideoDerivativeFile($this->version, 'mp4');
        // Move MP4 file.
        $this->derivativesDisk->move(
            sprintf('%s.mp4', $tempDerivativePath),
            sprintf('%s.mp4', FilePathHelper::toVideoDerivativeFile($this->version->Media, 'mp4'))
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
            CdnHelper::invalidateMedia($this->version->Media->type, $this->derivativesDestinationPath);
        }
    }

    /**
     * @return string
     */
    protected function getTempMp4Filename(): string
    {
        return sprintf('temp-derivative-%s-%s.mp4', $this->version->Media->identifier, $this->version->getKey());
    }

    /**
     * @return string
     */
    protected function getTempOriginalFilename(): string
    {
        return sprintf('temp-original-%s-%s', $this->version->Media->identifier, $this->version->getKey());
    }

    /**
     * @return string
     */
    protected function getFfmpegTempDirectory(): string
    {
        return sprintf('ffmpeg-temp%s%s-%s', DIRECTORY_SEPARATOR, $this->version->Media->identifier, $this->version->getKey());
    }

    /**
     * @return bool
     */
    protected function isMostRecentVersion(): bool
    {
        return $this->version->number === $this->version->Media->Versions()->max('number')
            && $this->version->Media->User->UploadSlots()->withoutGlobalScopes()->whereToken($this->uploadSlot->token)->first();
    }
}
