<?php

namespace Alexweb\Notifications;

use Alexweb\Notifications\Channels\Telegram\TelegramRecipient;
use Alexweb\Notifications\Exceptions\MissingConfigurationException;
use Alexweb\Notifications\Notifications\SimpleTextNotification;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

class Notifier
{
    private ?string $forcedChannel = null;

    public function __construct(private Application $app) {}

    public function channel(string $name): self
    {
        $clone = clone $this;
        $clone->forcedChannel = $name;

        return $clone;
    }

    public function send(string|Notification $message): void
    {
        $recipient = $this->resolveRecipient();
        if ($recipient === null) {
            return;
        }

        $notification = $this->makeNotification($message);

        if (config('notifications.queue.enabled', true)) {
            NotificationFacade::send($recipient, $notification);

            return;
        }

        $this->safeSendNow($recipient, $notification);
    }

    public function sendNow(string|Notification $message): void
    {
        $recipient = $this->resolveRecipient();
        if ($recipient === null) {
            return;
        }

        $this->safeSendNow($recipient, $this->makeNotification($message));
    }

    public function fake(): self
    {
        NotificationFacade::fake();

        return $this;
    }

    public function assertSent(string $notificationClass, ?callable $callback = null): void
    {
        NotificationFacade::assertSentTo(
            $this->app->make(TelegramRecipient::class),
            $notificationClass,
            $callback
        );
    }

    public function assertNothingSent(): void
    {
        NotificationFacade::assertNothingSent();
    }

    private function makeNotification(string|Notification $message): Notification
    {
        if ($message instanceof Notification) {
            return $message;
        }

        return new SimpleTextNotification($message, $this->forcedChannel);
    }

    private function resolveRecipient(): ?TelegramRecipient
    {
        if (! $this->isConfigured()) {
            $this->handleMissingConfig();

            return null;
        }

        return $this->app->make(TelegramRecipient::class);
    }

    private function isConfigured(): bool
    {
        return ! empty(config('notifications.channels.telegram.bot_token'))
            && ! empty(config('notifications.channels.telegram.chat_id'));
    }

    private function handleMissingConfig(): void
    {
        $message = 'Notifications: TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID is not configured';

        if (in_array($this->app->environment(), ['local', 'testing'], true)) {
            throw new MissingConfigurationException($message);
        }

        Log::warning($message);
    }

    private function safeSendNow(TelegramRecipient $recipient, Notification $notification): void
    {
        try {
            NotificationFacade::sendNow($recipient, $notification);
        } catch (Throwable $e) {
            Log::error('Notifier: failed to send notification synchronously', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
