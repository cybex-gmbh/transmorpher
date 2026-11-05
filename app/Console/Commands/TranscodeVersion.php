<?php

namespace App\Console\Commands;

use App\Enums\MediaType;
use App\Models\Version;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;
use Transcode;

#[Signature(
    'transcode:version
        {versionId : The id of the version to transcode}
        {uploadToken : The token originally associated when the version was uploaded}'
)]
#[Description('Transcode one video version synchronously.')]
class TranscodeVersion extends Command
{
    public function handle(): int
    {
        try {
            Transcode::getJobClass()::dispatchSync(
                Version::whereRelation('Media', 'type', MediaType::VIDEO)->findOrFail($this->argument('versionId')),
                $this->argument('uploadToken'),
            );

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            report($throwable);

            $this->fail($throwable->getMessage());
        }
    }
}
