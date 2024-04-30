<?php

namespace App\Jobs;

use App\Enums\ClientNotification;
use App\Exceptions\ClientNotificationFailedException;
use App\Helpers\SodiumHelper;
use App\Models\User;
use Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClientPurgeNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 14;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 10;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public int $backoff = 60 * 60 * 24; // 1 day

    protected ClientNotification $notificationType = ClientNotification::CACHE_INVALIDATION;

    /**
     * Create a new job instance.
     */
    public function __construct(protected User $user, protected int $cacheInvalidationCounter)
    {
        $this->onQueue('client-notifications');
    }

    /**
     * Execute the job.
     * @throws ClientNotificationFailedException
     */
    public function handle(): void
    {
        $notification = [
            'notification_type' => $this->notificationType,
            'cache_invalidation_counter' => $this->cacheInvalidationCounter
        ];

        $signedNotification = SodiumHelper::sign(json_encode($notification));

        $response = Http::post($this->user->api_url, ['signed_notification' => $signedNotification]);

        if (!$response->ok()) {
            throw new ClientNotificationFailedException($this->user->name, $this->notificationType->value, $response->status(), $response->reason());
        }
    }
}
