<?php

namespace Alexweb\Notifications\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Alexweb\Notifications\Notifier channel(string $name)
 * @method static void send(string|\Illuminate\Notifications\Notification $message)
 * @method static void sendNow(string|\Illuminate\Notifications\Notification $message)
 * @method static \Alexweb\Notifications\Notifier fake()
 * @method static void assertSent(string $notificationClass, ?callable $callback = null)
 * @method static void assertNothingSent()
 *
 * @see \Alexweb\Notifications\Notifier
 */
class Notifier extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'alexweb.notifier';
    }
}
