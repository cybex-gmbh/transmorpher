<?php

namespace App\Notifications;

use App\Enums\Queue;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewApiVersionNotice extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param int $apiVersion
     */
    public function __construct(protected int $apiVersion)
    {
        $this->onQueue(Queue::EMAIL->getQueue());
        $this->onConnection(Queue::EMAIL->getConnection());
    }

    /**
     * Get the message group ID for SQS queues.
     * Groups by API version to keep all notifications for the same version together.
     *
     * @return string
     */
    public function messageGroup(): string
    {
        return sprintf('api-version-%s', $this->apiVersion);
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
        return sprintf('new-api-version-%s', $this->apiVersion);
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
            ->bcc(User::get()->pluck('email'))
            ->subject(trans('new-version-notice.subject', ['apiVersion' => $this->apiVersion]))
            ->greeting(trans('new-version-notice.title',  ['apiVersion' => $this->apiVersion]))
            ->line(trans('new-version-notice.new_api_version_released', ['apiVersion' => $this->apiVersion]))
            ->line(trans('new-version-notice.update_client_implementations'))
            ->action(trans('new-version-notice.check_out_on_github'), 'https://github.com/cybex-gmbh/transmorpher/releases');
    }
}
