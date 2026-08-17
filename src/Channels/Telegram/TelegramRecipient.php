<?php

namespace Alexweb\Notifications\Channels\Telegram;

use Illuminate\Notifications\Notifiable;

class TelegramRecipient
{
    use Notifiable;

    public function __construct(private string $chatId) {}

    public function routeNotificationForTelegram(): string
    {
        return $this->chatId;
    }

    public function hasChatId(): bool
    {
        return $this->chatId !== '';
    }
}
