<?php

namespace App\Jobs;

use App\Enums\Decoder;
use App\Enums\Encoder;
use App\Enums\MediaStorage;
use App\Enums\ResponseState;
use App\Enums\StreamingFormat;
use App\Models\UploadSlot;
use App\Models\Version;
use CdnHelper;
use FFMpeg\Format\Video\X264;
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
    protected Decoder $decoder;
    protected Encoder $encoder;
    protected string $originalFilePath;
    protected string $uploadToken;
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
        protected Version $version,
        protected UploadSlot $uploadSlot,
        protected ?int $oldVersionNumber = null,
        protected ?bool $wasProcessed = null
    )
    {
        $this->onQueue('video-transcoding');
        \Log::info(sprintf('Constructing job for media %s and version %s with uploadToken %s.', $version->Media->identifier, $version->getKey(), $uploadSlot->token));
        $this->originalFilePath = $version->originalFilePath();
        $this->uploadToken = $this->uploadSlot->token;
        $this->decoder = Decoder::from(config('transmorpher.decoder'));
        $this->encoder = Encoder::from(config('transmorpher.encoder'));
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        \Log::info(sprintf('Transcoding video for media %s and version %s.', $this->version->Media->identifier, $this->version->getKey()));
        // Check for newer versions and validity of upload slot.
        if ($this->isMostRecentVersion()) {
            $this->originalsDisk = MediaStorage::ORIGINALS->getDisk();
            $this->derivativesDisk = MediaStorage::VIDEO_DERIVATIVES->getDisk();
            $this->localDisk = Storage::disk('local');
            $this->tempOriginalFilename = $this->getTempOriginalFilename();

            $this->transcode();
        } else {
            $this->responseState = ResponseState::TRANSCODING_ABORTED;
        }

        \Log::info(sprintf('Transcoding finished for media %s and version %s with response state %s.', $this->version->Media->identifier, $this->version->getKey(), $this->responseState->value));
        match ($this->responseState) {
            ResponseState::TRANSCODING_SUCCESSFUL => Transcode::callback($this->responseState, $this->uploadToken, $this->version->Media, $this->version->number),
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
        \Log::error(sprintf('Transcoding video for media %s and version %s failed. Exception: %s', $this->version->Media->identifier, $this->version->getKey(), $exception?->getMessage()));

        $localDisk = Storage::disk('local');

        MediaStorage::VIDEO_DERIVATIVES->getDisk()->deleteDirectory($this->getTempDerivativesDirectoryPath());
        $localDisk->delete($this->getTempMp4Filename());
        $localDisk->delete($this->getTempOriginalFilename());
        $localDisk->deleteDirectory($this->getFfmpegTempDirectory());
        // This directory stores local temp derivatives in case cloud storage is used.
        $localDisk->deleteDirectory($this->getTempDerivativesDirectoryPath());

        if (!$this->oldVersionNumber) {
            // A failed upload must not create a version.
            $this->version->delete();
            $versionNumber = $this->version->number - 1;
        } else {
            // Restoring an old version has failed, therefore we restore its previous state.
            $this->version->update(['number' => $this->oldVersionNumber, 'processed' => $this->wasProcessed]);
            $versionNumber = $this->oldVersionNumber;
        }

        Transcode::callback($this->responseState ?? ResponseState::TRANSCODING_FAILED, $this->uploadToken, $this->version->Media, $versionNumber);
    }

    /**
     * Initiates all steps necessary to transcode a video.
     *
     * @return void
     */
    protected function transcode(): void
    {
        $ffmpeg = FFMpeg::create([
            'timeout' => $this->timeout,
            'temporary_directory' => $this->localDisk->path($this->getFfmpegTempDirectory())
        ]);

        \Log::info(sprintf('Downloading video for media %s and version %s.', $this->version->Media->identifier, $this->version->getKey()));
        $video = $this->loadVideo($ffmpeg);

        $this->setFilePaths();
        $this->generateMp4($video);
        $this->saveVideo($video, StreamingFormat::HLS);
        $this->saveVideo($video, StreamingFormat::DASH);

        $this->localDisk->delete($this->tempOriginalFilename);
        $this->localDisk->deleteDirectory($this->getFfmpegTempDirectory());
        $this->localDisk->deleteDirectory($this->getTempDerivativesDirectoryPath());

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
        $localPath = $this->originalsDisk->path($this->originalFilePath);

        if (!$this->isLocalFilesystem($this->originalsDisk)) {
            $this->localDisk->writeStream($this->tempOriginalFilename, $this->originalsDisk->readStream($this->originalFilePath));
            $localPath = $this->localDisk->path($this->tempOriginalFilename);
        }

        return $ffmpeg->customInput($localPath, $this->decoder->getInitialParameters());
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
        $this->derivativesDestinationPath = $this->version->Media->baseDirectory();
    }

    /**
     * Saves the transcoded video to storage.
     *
     * @param StreamingMedia $media
     * @param StreamingFormat $streamingFormat
     *
     * @return void
     */
    protected function saveVideo(StreamingMedia $media, StreamingFormat $streamingFormat): void
    {
        $configuredMedia = $streamingFormat->configure($media, $this->encoder);
        $tempDerivativeFilePath = $this->getTempDerivativeFilePath($streamingFormat->value);

        \Log::info(sprintf('Generating %s for media %s and version %s. Using: %s -> %s', strtoupper($streamingFormat->value), $this->version->Media->identifier, $this->version->getKey(), $this->decoder->name, $this->encoder->name));
        // Save to temporary folder first, to prevent race conditions when multiple versions are uploaded simultaneously.
        if ($this->isLocalFilesystem($this->derivativesDisk)) {
            $configuredMedia->save($this->derivativesDisk->path($tempDerivativeFilePath));
        } else {
            // When using cloud storage, we save to local storage first and then upload manually,
            // because the php-ffmpeg-video-streaming package direct upload functionality led to S3 disconnects for large files.
            $configuredMedia->save($this->localDisk->path($tempDerivativeFilePath));

            $tempDerivativesFormatDirectoryPath = $this->getTempDerivativesFormatDirectoryPath($streamingFormat->value);

            \Log::info(sprintf('Writing %s to S3 for media %s and version %s.', $streamingFormat->value, $this->version->Media->identifier, $this->version->getKey()));
            foreach ($this->localDisk->allFiles($tempDerivativesFormatDirectoryPath) as $filePath) {
                $this->derivativesDisk->writeStream(
                    $filePath,
                    $this->localDisk->readStream($filePath));
            }

            $this->localDisk->deleteDirectory($tempDerivativesFormatDirectoryPath);
        }
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
        $tempMp4Filename = $this->getTempMp4Filename();
        \Log::info(sprintf('Generating MP4 for media %s and version %s. Using: %s -> %s', $this->version->Media->identifier, $this->version->getKey(), $this->decoder->name, $this->encoder->name));
        // GPU accelerated encoding cannot be set via setVideoCodec(). h264_nvenc may be set through the additional params.
        $video->save(
            (new X264())
                ->setInitialParameters($this->decoder->getInitialParameters())
                ->setAdditionalParameters($this->encoder->getAdditionalParameters(forMp4Fallback: true)),
            $this->localDisk->path($tempMp4Filename)
        );

        \Log::info(sprintf('Writing MP4 to %s for media %s and version %s.', MediaStorage::VIDEO_DERIVATIVES->getDiskName(), $this->version->Media->identifier, $this->version->getKey()));
        $this->derivativesDisk->writeStream(
            sprintf('%s.%s', $this->getTempDerivativeFilePath('mp4'), 'mp4'),
            $this->localDisk->readStream($tempMp4Filename)
        );

        $this->localDisk->delete($tempMp4Filename);
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
            \Log::info(sprintf('Moving video derivatives for media %s and version %s.', $this->version->Media->identifier, $this->version->getKey()));
            $this->moveFromTempDirectory();

            // Invalidate the cache again for the newly generated derivative.
            \Log::info(sprintf('Invalidating CDN cache after moving for media %s and version %s.', $this->version->Media->identifier, $this->version->getKey()));
            $this->invalidateCdnCache();

            $this->version->update(['processed' => true]);
            $this->responseState = ResponseState::TRANSCODING_SUCCESSFUL;
        } else {
            \Log::info(sprintf('Transcoding aborted since not latest version for media %s and version %s.', $this->version->Media->identifier, $this->version->getKey()));
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
            $this->derivativesDisk->move($this->getTempDerivativesDirectoryPath(), $this->derivativesDestinationPath);
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
        $hlsFiles = $this->derivativesDisk->allFiles($this->getTempDerivativesFormatDirectoryPath(StreamingFormat::HLS->value));
        $dashFiles = $this->derivativesDisk->allFiles($this->getTempDerivativesFormatDirectoryPath(StreamingFormat::DASH->value));

        foreach ($hlsFiles as $file) {
            $this->derivativesDisk->move($file, $this->version->Media->videoDerivativeFilePath(StreamingFormat::HLS->value, basename($file)));
        }

        foreach ($dashFiles as $file) {
            $this->derivativesDisk->move($file, $this->version->Media->videoDerivativeFilePath(StreamingFormat::DASH->value, basename($file)));
        }

        // Move MP4 file.
        $this->derivativesDisk->move(
            sprintf('%s.mp4', $this->getTempDerivativeFilePath('mp4')),
            sprintf('%s.mp4', $this->version->Media->videoDerivativeFilePath('mp4'))
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
            \Log::info(sprintf('Invalidating CDN cache for media %s and version %s.', $this->version->Media->identifier, $this->version->getKey()));
            // If this fails, the 'failed()'-method will handle the cleanup.
            CdnHelper::invalidateMedia($this->version->Media->type, $this->derivativesDestinationPath);
        } else {
            \Log::info('Skipping CDN invalidation. CDN is not configured.');
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

    /**
     * Get the path to a temporary video derivative.
     * Path structure: {username}/{identifier}-{versionKey}-temp/{format}/video
     *
     * @param string $format
     * @return string
     */
    protected function getTempDerivativeFilePath(string $format): string
    {
        return sprintf('%s/%s', $this->getTempDerivativesFormatDirectoryPath($format), 'video');
    }

    /**
     * Get the path to the temporary video derivatives directory.
     * Path structure: {username}/{identifier}-{versionKey}-temp
     *
     * @return string
     */
    protected function getTempDerivativesDirectoryPath(): string
    {
        return sprintf('%s-%s-temp', $this->version->Media->baseDirectory(), $this->version->getKey());
    }

    /**
     * Get the path to the temporary video derivatives directory for a format.
     * Path structure: {username}/{identifier}-{versionKey}-temp/{format}
     */
    protected function getTempDerivativesFormatDirectoryPath(string $format): string
    {
        return sprintf('%s/%s', $this->getTempDerivativesDirectoryPath(), $format);
    }
}
