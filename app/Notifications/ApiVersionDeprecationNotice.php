<?php

namespace App\Notifications;

use App\Enums\Queue;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApiVersionDeprecationNotice extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param int $apiVersion
     */
    public function __construct(protected int $apiVersion)
    {
        $this->onQueue(Queue::EMAIL->getName());
        $this->onConnection(Queue::EMAIL->getConnection());
    }

    /**
     * Get the message group ID for SQS (FIFO) queues.
     *
     * @return string
     */
    public function messageGroup(): string
    {
        return Queue::EMAIL->name;
    }

    /**
     * Get the message deduplication ID for SQS FIFO queues.
     * Uniquely identifies this notification by API version.
     *
     * @param string $payload
     * @param string $queue
     * @return string
     */
    public function deduplicationId(string $payload, string $queue): string
    {
        return sprintf('%s:api-deprecation-%s', Queue::EMAIL->name, $this->apiVersion);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        return [
            'mail'
        ];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->from(config('mail.from.address'), config('app.name'))
            ->bcc(User::pluck('email'))
            ->subject(trans('version-deprecation-notice.subject', ['apiVersion' => $this->apiVersion]))
            ->greeting(trans('version-deprecation-notice.title'))
            ->line(trans('version-deprecation-notice.version_soon_deprecated', ['apiVersion' => $this->apiVersion]))
            ->line(trans('version-deprecation-notice.update_client_implementations'));
    }
}
