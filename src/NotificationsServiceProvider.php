<?php

namespace Alexweb\Notifications;

use Alexweb\Notifications\Channels\Telegram\TelegramRecipient;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/notifications.php',
            'notifications'
        );

        $this->app->singleton(TelegramRecipient::class, function ($app) {
            return new TelegramRecipient(
                (string) config('notifications.channels.telegram.chat_id', '')
            );
        });

        $this->app->singleton('alexweb.notifier', function ($app) {
            return new Notifier($app);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/notifications.php' => config_path('notifications.php'),
            ], 'notifications-config');
        }

        $this->configureBotToken();
    }

    /**
     * Forward bot token into the underlying telegram channel config.
     * laravel-notification-channels/telegram reads from services.telegram-bot-api.token.
     */
    private function configureBotToken(): void
    {
        $token = config('notifications.channels.telegram.bot_token');

        if (! empty($token)) {
            config(['services.telegram-bot-api.token' => $token]);
        }
    }
}
