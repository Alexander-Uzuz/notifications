# alexweb/notifications

Internal multi-channel notifications library for Laravel projects. Единый фасад, поддержка Laravel Notification-классов, лог-канал в Telegram.

В v1 поддерживается один канал — Telegram — и один общий чат команды.

## Установка

Добавь VCS-репозиторий и пакет в `composer.json` целевого проекта:

```json
{
    "repositories": [
        { "type": "vcs", "url": "git@github.com:alexweb/notifications.git" }
    ],
    "require": {
        "alexweb/notifications": "^0.1"
    }
}
```

Затем:

```bash
composer update alexweb/notifications
php artisan vendor:publish --tag=notifications-config
```

## Конфигурация

Добавь в `.env`:

```
TELEGRAM_BOT_TOKEN=123456:AAA...
TELEGRAM_CHAT_ID=-1001234567890
```

Как получить значения:
- **BOT_TOKEN** — создать бота у `@BotFather`, скопировать токен.
- **CHAT_ID** — добавить бота в чат, отправить любое сообщение, вызвать `https://api.telegram.org/bot<TOKEN>/getUpdates`, взять `chat.id`.

Опциональные переменные:
- `NOTIFICATIONS_QUEUE_ENABLED` (`true`) — принудительно синхронная отправка, если `false`.
- `NOTIFICATIONS_QUEUE_NAME` (`notifications`) — имя очереди.
- `NOTIFICATIONS_QUEUE_TRIES` (`3`) — количество попыток.
- `TELEGRAM_PARSE_MODE` (`HTML`) — режим форматирования (`HTML` | `Markdown` | `MarkdownV2`).

## Использование

### Быстрая текстовая отправка

```php
use Alexweb\Notifications\Facades\Notifier;

// В очередь (по умолчанию)
Notifier::send('Заказ №123 создан');

// С HTML-форматированием
Notifier::send('<b>Ошибка</b>: <code>NullPointer</code>');

// Синхронно (для CLI и критичных случаев)
Notifier::sendNow('Критично: БД недоступна');

// Явный канал (для будущих каналов)
Notifier::channel('telegram')->send('Алерт');
```

### Notification-классы для повторяющихся событий

```php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class OrderCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId, public float $total) {}

    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        return TelegramMessage::create()
            ->to($notifiable->routeNotificationForTelegram())
            ->content("<b>Заказ #{$this->orderId}</b>\nСумма: {$this->total} ₽");
    }
}
```

Отправка:

```php
use Alexweb\Notifications\Facades\Notifier;

Notifier::send(new OrderCreated($order->id, $order->total));
```

### Log-канал

Добавь в `config/logging.php`:

```php
'channels' => [
    'telegram' => [
        'driver'  => 'monolog',
        'handler' => \Alexweb\Notifications\Logging\TelegramLogHandler::class,
        'level'   => 'error',
    ],
],
```

Использование:

```php
Log::channel('telegram')->error('Payment webhook failed', [
    'order_id' => $order->id,
    'reason'   => $e->getMessage(),
]);
```

Для алертов о необработанных исключениях в `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->report(function (Throwable $e) {
        Log::channel('telegram')->error($e->getMessage(), [
            'file' => $e->getFile().':'.$e->getLine(),
        ]);
    });
})
```

## Тестирование в целевых проектах

```php
use Alexweb\Notifications\Facades\Notifier;

it('sends OrderCreated notification', function () {
    Notifier::fake();

    // ... код, который триггерит уведомление ...

    Notifier::assertSent(OrderCreated::class);
});
```

## Поведение при отсутствии конфига

- В `local` и `testing` — бросается `MissingConfigurationException`.
- В `production` — уведомление пропускается, в лог пишется `warning`.

## Разработка

```bash
git clone git@github.com:alexweb/notifications.git
cd notifications
composer install
```
