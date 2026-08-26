<?php

namespace Tests\v2\Unit;

use App\Enums\MediaType;
use App\Enums\Queue as QueueTarget;
use App\Exceptions\InvalidConfigurationException;
use App\Jobs\ClientPurgeNotification;
use App\Jobs\TranscodeVideo;
use App\Models\User;
use App\Notifications\ApiVersionDeprecationNotice;
use App\Notifications\NewApiVersionNotice;
use Illuminate\Console\Command;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueueTest extends TestCase
{
    use RefreshDatabase;
    use WithConsoleEvents;

    #[Test]
    #[DataProvider('provideDispatchConfigs')]
    public function dispatchesOnCorrectQueueAndConnection(string $dispatchableClass, QueueTarget $target): void
    {
        config()->set(sprintf('transmorpher.queue.%s.queue', $target->value), sprintf('configured-%s', $target->value));
        config()->set(sprintf('transmorpher.queue.%s.connection', $target->value), 'database');
        config()->set(sprintf('transmorpher.queue.%s.use_sqs_fifo', $target->value), false);

        Queue::fake();

        $this->dispatch($dispatchableClass);

        $this->assertCorrectlyPushed($dispatchableClass, 'database', sprintf('configured-%s', $target->value));
    }

    #[Test]
    public function addsFifoSuffixIfConfigured(): void
    {
        config()->set('transmorpher.queue.video_transcoding.queue', 'video-transcoding');
        config()->set('transmorpher.queue.video_transcoding.use_sqs_fifo', true);

        $this->assertSame('video-transcoding.fifo', QueueTarget::VIDEO_TRANSCODING->getQueue());
    }

    #[Test]
    public function doesNotAddFifoSuffixIfDisabled(): void
    {
        config()->set('transmorpher.queue.video_transcoding.queue', 'video-transcoding');
        config()->set('transmorpher.queue.video_transcoding.use_sqs_fifo', false);

        $this->assertSame('video-transcoding', QueueTarget::VIDEO_TRANSCODING->getQueue());
    }

    #[Test]
    public function failsIfShouldUseFifoButNoSqsDriver(): void
    {
        config()->set('transmorpher.queue.video_transcoding.queue', 'video-transcoding');
        config()->set('transmorpher.queue.video_transcoding.connection', 'database');
        config()->set('transmorpher.queue.video_transcoding.use_sqs_fifo', true);

        $this->artisan('transmorpher:queue-work', [
            'target' => 'video_transcoding',
        ])->assertFailed();
    }

    #[Test]
    public function runsQueueWorkCommandWithCorrectConfiguration(): void
    {
        config()->set('transmorpher.queue.video_transcoding.queue', 'configured-video-transcoding');
        config()->set('transmorpher.queue.video_transcoding.connection', 'database');
        config()->set('transmorpher.queue.video_transcoding.use_sqs_fifo', false);

        $capturedEvents = [];
        $this->app['events']->listen(CommandStarting::class, function (CommandStarting $event) use (&$capturedEvents): void {
            $capturedEvents[] = $event;
        });

        $this->artisan('transmorpher:queue-work', [
            'target' => 'video_transcoding',
            '--once' => true,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertTrue(collect($capturedEvents)->contains(fn(CommandStarting $event): bool => $event->command === 'queue:work'
            && $event->input->getArgument('connection') === 'database'
            && $event->input->getOption('queue') === 'configured-video-transcoding'
            && $event->input->getOption('once') === true));
    }

    #[Test]
    public function failsWhenConnectionConfigIsNotAnArray(): void
    {
        config()->set('transmorpher.queue.video_transcoding.connection', 'invalid');
        config()->set('queue.connections.invalid', null);

        $this->expectException(InvalidConfigurationException::class);

        QueueTarget::VIDEO_TRANSCODING->validateConfiguration();
    }

    #[Test]
    public function failsWhenConnectionHasNoDriver(): void
    {
        config()->set('transmorpher.queue.video_transcoding.connection', 'no-driver');
        config()->set('queue.connections.no-driver', []);

        $this->expectException(InvalidConfigurationException::class);

        QueueTarget::VIDEO_TRANSCODING->validateConfiguration();
    }


    public static function provideDispatchConfigs(): array
    {
        return [
            'client purge notification job' => [ClientPurgeNotification::class, QueueTarget::CLIENT_NOTIFICATIONS],
            'video transcode job' => [TranscodeVideo::class, QueueTarget::VIDEO_TRANSCODING],
            'new api version notice notification' => [NewApiVersionNotice::class, QueueTarget::EMAIL],
            'api deprecation notice notification' => [ApiVersionDeprecationNotice::class, QueueTarget::EMAIL],
        ];
    }

    protected function dispatch(string $dispatchableClass): void
    {
        match ($dispatchableClass) {
            ClientPurgeNotification::class => $this->dispatchClientPurgeNotification(),
            TranscodeVideo::class => $this->dispatchTranscodeVideo(),
            NewApiVersionNotice::class => $this->dispatchNewApiVersionNotice(),
            ApiVersionDeprecationNotice::class => $this->dispatchApiVersionDeprecationNotice(),
        };
    }

    protected function dispatchClientPurgeNotification(): void
    {
        ClientPurgeNotification::dispatch(User::factory()->create(), 1);
    }

    protected function dispatchTranscodeVideo(): void
    {
        $user = User::factory()->create();
        $media = $user->Media()->create([
            'identifier' => 'queue-test-video',
            'type' => MediaType::VIDEO,
        ]);
        $version = $media->Versions()->create([
            'number' => 1,
            'filename' => 'queue-test-video.mp4',
        ]);
        $uploadSlot = $user->UploadSlots()->create([
            'identifier' => $media->identifier,
            'filename' => 'queue-test-video.mp4',
            'media_type' => MediaType::VIDEO,
        ]);

        TranscodeVideo::dispatch($version, $uploadSlot);
    }

    protected function dispatchNewApiVersionNotice(): void
    {
        User::factory()->create()->notify(new NewApiVersionNotice(2));
    }

    protected function dispatchApiVersionDeprecationNotice(): void
    {
        User::factory()->create()->notify(new ApiVersionDeprecationNotice(2));
    }

    protected function assertCorrectlyPushed(string $dispatchableClass, string $expectedConnection, string $expectedQueue): void
    {
        Queue::assertPushed($this->queuedClassFor($dispatchableClass), fn(ShouldQueue $job): bool => $job->connection === $expectedConnection
            && $job->queue === $expectedQueue
            && $this->dispatchedClassFor($job) === $dispatchableClass);
    }

    protected function queuedClassFor(string $dispatchableClass): string
    {
        // Laravel queues notifications by wrapping them in SendQueuedNotifications.
        return is_subclass_of($dispatchableClass, Notification::class)
            ? SendQueuedNotifications::class
            : $dispatchableClass;
    }

    protected function dispatchedClassFor(ShouldQueue $queuedJob): string
    {
        // Laravel queues notifications by wrapping them in SendQueuedNotifications.
        if ($queuedJob instanceof SendQueuedNotifications) {
            return $queuedJob->notification::class;
        }

        return $queuedJob::class;
    }
}
