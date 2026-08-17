<?php

namespace Alexweb\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class SimpleTextNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public function __construct(
        private string $text,
        private ?string $channelName = null,
    ) {
        $this->tries = (int) config('notifications.queue.tries', 3);
        $this->onConnection(config('notifications.queue.connection'));
        $this->onQueue(config('notifications.queue.name', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        return [$this->channelName ?? config('notifications.default', 'telegram')];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()
            ->to($notifiable->routeNotificationForTelegram())
            ->content($this->buildContent());

        $parseMode = config('notifications.channels.telegram.parse_mode', 'HTML');

        if ($parseMode !== null) {
            $message->options(['parse_mode' => $parseMode]);
        }

        return $message;
    }

    private function buildContent(): string
    {
        $appName = config('notifications.app_name') ?: config('app.name', 'App');

        return '<b>['.e($appName).']</b>'."\n\n".$this->text;
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
