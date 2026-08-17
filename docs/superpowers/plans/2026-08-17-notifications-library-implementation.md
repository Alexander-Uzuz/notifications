# План имплементации библиотеки `alexweb/notifications`

> **Для агентных воркеров:** REQUIRED SUB-SKILL: используй `superpowers:subagent-driven-development` (рекомендуется) или `superpowers:executing-plans` для пошаговой имплементации. Шаги используют чекбокс-синтаксис (`- [ ]`) для отслеживания.

**Цель:** Реализовать v1 пакета `alexweb/notifications` согласно дизайну `docs/superpowers/specs/2026-08-17-notifications-library-design.md`.

**Архитектура:** Тонкий Laravel-пакет поверх `laravel-notification-channels/telegram`. Фасад `Notifier` для быстрых текстовых сообщений, поддержка обычных Laravel Notification-классов, Monolog handler для `Log::channel('telegram')`. По умолчанию — очередь, с опцией `sendNow()`.

**Стек:** PHP 8.2+, Laravel 11/12, `laravel-notification-channels/telegram` ^5, Monolog.

**Важные правила проекта:**
- **Не пишем тесты** — правило пользователя.
- Комментарии в коде — только на английском.
- Русский язык — только в `docs/superpowers/` и в пользовательских README (примерах).
- Работаем в `/home/localhost/Projects/notifications`, ветка `main`.
- Композит-тим-фичи и артефакты интеграции с `journey-predictor-api` — вне области v1.

**Именование:**
- Composer-пакет: `alexweb/notifications`
- PSR-4 namespace: `Alexweb\Notifications\`
- Facade accessor: `alexweb.notifier`

---

## Файловая структура (итоговое состояние после плана)

```
notifications/
├── composer.json
├── .gitignore
├── README.md
├── config/
│   └── notifications.php
├── docs/
│   └── superpowers/
│       ├── specs/2026-08-17-notifications-library-design.md   (уже есть)
│       └── plans/2026-08-17-notifications-library-implementation.md   (этот файл)
└── src/
    ├── NotificationsServiceProvider.php
    ├── Notifier.php
    ├── Facades/
    │   └── Notifier.php
    ├── Channels/
    │   └── Telegram/
    │       └── TelegramRecipient.php
    ├── Notifications/
    │   └── SimpleTextNotification.php
    ├── Logging/
    │   └── TelegramLogHandler.php
    └── Exceptions/
        └── MissingConfigurationException.php
```

---

## Task 1: Скаффолд репозитория (composer.json, .gitignore, placeholder README)

**Файлы:**
- Создать: `composer.json`
- Создать: `.gitignore`
- Создать: `README.md`

- [ ] **Step 1: Создать `composer.json`**

Файл `/home/localhost/Projects/notifications/composer.json`:

```json
{
    "name": "alexweb/notifications",
    "description": "Internal multi-channel notifications library for Laravel projects.",
    "type": "library",
    "license": "proprietary",
    "authors": [
        {
            "name": "alex",
            "email": "gunfighter152487639@gmail.com"
        }
    ],
    "require": {
        "php": "^8.2",
        "illuminate/notifications": "^11.0|^12.0",
        "illuminate/support": "^11.0|^12.0",
        "illuminate/log": "^11.0|^12.0",
        "laravel-notification-channels/telegram": "^5.0"
    },
    "autoload": {
        "psr-4": {
            "Alexweb\\Notifications\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Alexweb\\Notifications\\NotificationsServiceProvider"
            ]
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

- [ ] **Step 2: Создать `.gitignore`**

Файл `/home/localhost/Projects/notifications/.gitignore`:

```
/vendor
composer.lock
.idea
.vscode
.DS_Store
```

Пояснение: `composer.lock` игнорируем, потому что это библиотека, а не приложение — lock-файл в библиотеках вреден (Composer его не читает при `composer require`).

- [ ] **Step 3: Создать placeholder `README.md`**

Файл `/home/localhost/Projects/notifications/README.md`:

```markdown
# alexweb/notifications

Internal multi-channel notifications library for Laravel projects. WIP.
```

Полный README добавим в Task 9.

- [ ] **Step 4: Установить зависимости**

Команда:

```bash
cd /home/localhost/Projects/notifications && composer install
```

Ожидается: успешная установка (`vendor/` появляется, никаких ошибок разрешения). Если что-то не резолвится — проверить версии в `composer.json`.

- [ ] **Step 5: Проверить, что автозагрузка работает**

Команда:

```bash
cd /home/localhost/Projects/notifications && composer dump-autoload
```

Ожидается: `Generated autoload files`, без warnings.

- [ ] **Step 6: Коммит**

Команда:

```bash
cd /home/localhost/Projects/notifications && git add composer.json .gitignore README.md && git commit -m "chore: bootstrap composer package skeleton"
```

---

## Task 2: Файл конфигурации

**Файлы:**
- Создать: `config/notifications.php`

- [ ] **Step 1: Создать `config/notifications.php`**

Файл `/home/localhost/Projects/notifications/config/notifications.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Notification Channel
    |--------------------------------------------------------------------------
    |
    | Channel used by the Notifier facade when no explicit channel is given
    | via ->channel('name'). Only "telegram" is supported in v1.
    |
    */

    'default' => env('NOTIFICATIONS_DEFAULT_CHANNEL', 'telegram'),

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    |
    | Notifier::send() dispatches notifications through the queue by default.
    | Set enabled=false to force synchronous delivery for every call.
    |
    */

    'queue' => [
        'enabled' => env('NOTIFICATIONS_QUEUE_ENABLED', true),
        'connection' => env('NOTIFICATIONS_QUEUE_CONNECTION'),
        'name' => env('NOTIFICATIONS_QUEUE_NAME', 'notifications'),
        'tries' => (int) env('NOTIFICATIONS_QUEUE_TRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    */

    'channels' => [
        'telegram' => [
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
            'parse_mode' => env('TELEGRAM_PARSE_MODE', 'HTML'),
        ],
    ],
];
```

- [ ] **Step 2: Проверить PHP-синтаксис**

Команда:

```bash
php -l /home/localhost/Projects/notifications/config/notifications.php
```

Ожидается: `No syntax errors detected`.

- [ ] **Step 3: Коммит**

Команда:

```bash
cd /home/localhost/Projects/notifications && git add config/notifications.php && git commit -m "feat: add notifications config file"
```

---

## Task 3: Service provider (merge/publish конфига)

**Файлы:**
- Создать: `src/NotificationsServiceProvider.php`

- [ ] **Step 1: Создать `NotificationsServiceProvider`**

Файл `/home/localhost/Projects/notifications/src/NotificationsServiceProvider.php`:

```php
<?php

namespace Alexweb\Notifications;

use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/notifications.php',
            'notifications'
        );
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
     * Forward TELEGRAM_BOT_TOKEN into the underlying
     * laravel-notification-channels/telegram config so the channel driver
     * picks up our value without requiring a separate env variable.
     */
    private function configureBotToken(): void
    {
        $token = config('notifications.channels.telegram.bot_token');

        if (! empty($token)) {
            config(['services.telegram-bot-api.token' => $token]);
        }
    }
}
```

Пояснение: `laravel-notification-channels/telegram` читает токен из `services.telegram-bot-api.token`. Мы мостим наш конфиг в его, чтобы пользователю не приходилось дублировать `.env`.

- [ ] **Step 2: Проверить синтаксис**

Команда:

```bash
php -l /home/localhost/Projects/notifications/src/NotificationsServiceProvider.php
```

Ожидается: `No syntax errors detected`.

- [ ] **Step 3: Проверить, что автозагрузка находит класс**

Команда:

```bash
cd /home/localhost/Projects/notifications && composer dump-autoload && php -r "require 'vendor/autoload.php'; class_exists('Alexweb\Notifications\NotificationsServiceProvider') ? print('OK'.PHP_EOL) : print('FAIL'.PHP_EOL);"
```

Ожидается: `OK`.

- [ ] **Step 4: Коммит**

Команда:

```bash
cd /home/localhost/Projects/notifications && git add src/NotificationsServiceProvider.php && git commit -m "feat: add service provider with config publishing"
```

---

## Task 4: Exceptions + TelegramRecipient (получатель-«команда»)

**Файлы:**
- Создать: `src/Exceptions/MissingConfigurationException.php`
- Создать: `src/Channels/Telegram/TelegramRecipient.php`
- Изменить: `src/NotificationsServiceProvider.php` (регистрация синглтона)

- [ ] **Step 1: Создать `MissingConfigurationException`**

Файл `/home/localhost/Projects/notifications/src/Exceptions/MissingConfigurationException.php`:

```php
<?php

namespace Alexweb\Notifications\Exceptions;

use RuntimeException;

class MissingConfigurationException extends RuntimeException
{
}
```

- [ ] **Step 2: Создать `TelegramRecipient`**

Файл `/home/localhost/Projects/notifications/src/Channels/Telegram/TelegramRecipient.php`:

```php
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
```

Пояснение: `Notifiable` даёт метод `notify()`, но мы используем этот объект как «маршрут», а не как модель. `routeNotificationForTelegram()` — стандартный контракт из Laravel Notifications, который читает канал telegram.

- [ ] **Step 3: Зарегистрировать `TelegramRecipient` как синглтон в провайдере**

Изменить файл `/home/localhost/Projects/notifications/src/NotificationsServiceProvider.php`. Заменить метод `register()`:

```php
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/notifications.php',
            'notifications'
        );

        $this->app->singleton(\Alexweb\Notifications\Channels\Telegram\TelegramRecipient::class, function ($app) {
            return new \Alexweb\Notifications\Channels\Telegram\TelegramRecipient(
                (string) config('notifications.channels.telegram.chat_id', '')
            );
        });
    }
```

- [ ] **Step 4: Проверить синтаксис всех изменённых файлов**

Команда:

```bash
find /home/localhost/Projects/notifications/src -name '*.php' -exec php -l {} \;
```

Ожидается: `No syntax errors detected` для каждого файла.

- [ ] **Step 5: Проверить автозагрузку**

Команда:

```bash
cd /home/localhost/Projects/notifications && composer dump-autoload && php -r "require 'vendor/autoload.php'; class_exists('Alexweb\Notifications\Channels\Telegram\TelegramRecipient') && class_exists('Alexweb\Notifications\Exceptions\MissingConfigurationException') ? print('OK'.PHP_EOL) : print('FAIL'.PHP_EOL);"
```

Ожидается: `OK`.

- [ ] **Step 6: Коммит**

Команда:

```bash
cd /home/localhost/Projects/notifications && git add src/ && git commit -m "feat: add TelegramRecipient and MissingConfigurationException"
```

---

## Task 5: `SimpleTextNotification` (внутренний класс для фасада)

**Файлы:**
- Создать: `src/Notifications/SimpleTextNotification.php`

- [ ] **Step 1: Создать `SimpleTextNotification`**

Файл `/home/localhost/Projects/notifications/src/Notifications/SimpleTextNotification.php`:

```php
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
            ->content($this->text);

        $parseMode = config('notifications.channels.telegram.parse_mode', 'HTML');
        if ($parseMode !== null) {
            $message->options(['parse_mode' => $parseMode]);
        }

        return $message;
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
```

Пояснения:
- `implements ShouldQueue` — по умолчанию класс дожидается очереди. При вызове `Notification::sendNow()` очередь обходится, что и надо `Notifier::sendNow()`.
- `backoff()` возвращает массив — Laravel применяет 10s → 30s → 60s между попытками.
- Явный `options(['parse_mode' => ...])` перекрывает дефолт HTML в самом пакете `laravel-notification-channels/telegram`.

- [ ] **Step 2: Проверить синтаксис**

Команда:

```bash
php -l /home/localhost/Projects/notifications/src/Notifications/SimpleTextNotification.php
```

Ожидается: `No syntax errors detected`.

- [ ] **Step 3: Проверить автозагрузку и разрешение всех use-импортов**

Команда:

```bash
cd /home/localhost/Projects/notifications && composer dump-autoload && php -r "require 'vendor/autoload.php'; class_exists('Alexweb\Notifications\Notifications\SimpleTextNotification') && class_exists('NotificationChannels\Telegram\TelegramMessage') ? print('OK'.PHP_EOL) : print('FAIL'.PHP_EOL);"
```

Ожидается: `OK`.

- [ ] **Step 4: Коммит**

Команда:

```bash
cd /home/localhost/Projects/notifications && git add src/Notifications/ && git commit -m "feat: add SimpleTextNotification queue-aware notification"
```

---

## Task 6: Фасад `Notifier` (ядро библиотеки + fake для тестов)

**Файлы:**
- Создать: `src/Notifier.php`
- Создать: `src/Facades/Notifier.php`
- Изменить: `src/NotificationsServiceProvider.php` (регистрация `alexweb.notifier`)

- [ ] **Step 1: Создать `Notifier`**

Файл `/home/localhost/Projects/notifications/src/Notifier.php`:

```php
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
```

Пояснения:
- `send()` — дефолт очередь; если `queue.enabled=false`, падаем в синхронную отправку.
- `sendNow()` — всегда синхронно, ошибки глотаем в лог.
- `fake()`, `assertSent()`, `assertNothingSent()` — тонкие обёртки над Laravel `Notification::fake()` для удобства пользователей библиотеки.
- Отсутствие конфига: в local/testing — исключение, в остальных окружениях — warning в лог.

- [ ] **Step 2: Создать фасад**

Файл `/home/localhost/Projects/notifications/src/Facades/Notifier.php`:

```php
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
```

- [ ] **Step 3: Зарегистрировать `alexweb.notifier` в провайдере**

Изменить файл `/home/localhost/Projects/notifications/src/NotificationsServiceProvider.php`. Заменить метод `register()` целиком:

```php
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/notifications.php',
            'notifications'
        );

        $this->app->singleton(\Alexweb\Notifications\Channels\Telegram\TelegramRecipient::class, function ($app) {
            return new \Alexweb\Notifications\Channels\Telegram\TelegramRecipient(
                (string) config('notifications.channels.telegram.chat_id', '')
            );
        });

        $this->app->singleton('alexweb.notifier', function ($app) {
            return new \Alexweb\Notifications\Notifier($app);
        });
    }
```

- [ ] **Step 4: Проверить синтаксис всех изменённых файлов**

Команда:

```bash
find /home/localhost/Projects/notifications/src -name '*.php' -exec php -l {} \;
```

Ожидается: `No syntax errors detected` для каждого файла.

- [ ] **Step 5: Проверить автозагрузку**

Команда:

```bash
cd /home/localhost/Projects/notifications && composer dump-autoload && php -r "require 'vendor/autoload.php'; class_exists('Alexweb\Notifications\Notifier') && class_exists('Alexweb\Notifications\Facades\Notifier') ? print('OK'.PHP_EOL) : print('FAIL'.PHP_EOL);"
```

Ожидается: `OK`.

- [ ] **Step 6: Коммит**

Команда:

```bash
cd /home/localhost/Projects/notifications && git add src/ && git commit -m "feat: add Notifier core service and facade"
```

---

## Task 7: `TelegramLogHandler` (Monolog handler для Log-канала)

**Файлы:**
- Создать: `src/Logging/TelegramLogHandler.php`

- [ ] **Step 1: Создать `TelegramLogHandler`**

Файл `/home/localhost/Projects/notifications/src/Logging/TelegramLogHandler.php`:

```php
<?php

namespace Alexweb\Notifications\Logging;

use Alexweb\Notifications\Facades\Notifier;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class TelegramLogHandler extends AbstractProcessingHandler
{
    public function __construct(int|string|Level $level = Level::Error, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            Notifier::sendNow($this->formatRecord($record));
        } catch (Throwable) {
            // Logging must never crash the app. Swallow any error from the notifier.
        }
    }

    private function formatRecord(LogRecord $record): string
    {
        $header = sprintf(
            '%s <b>%s</b> — <code>%s</code>',
            $this->emojiFor($record->level),
            e($record->level->getName()),
            e($record->channel),
        );

        $body = e($record->message);

        $context = '';
        if (! empty($record->context)) {
            $lines = [];
            foreach ($record->context as $key => $value) {
                $printable = is_scalar($value)
                    ? (string) $value
                    : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
                $lines[] = sprintf('%s: %s', e((string) $key), e($printable));
            }
            $context = "\n\n<pre>".implode("\n", $lines).'</pre>';
        }

        return $header."\n".$body.$context;
    }

    private function emojiFor(Level $level): string
    {
        return match ($level) {
            Level::Emergency, Level::Alert, Level::Critical, Level::Error => "\u{1F534}",
            Level::Warning => "\u{1F7E1}",
            Level::Notice, Level::Info => "\u{1F535}",
            Level::Debug => "\u{26AA}",
        };
    }
}
```

Пояснения:
- `AbstractProcessingHandler` — базовый класс Monolog v3, метод `write(LogRecord)` — точка расширения.
- `e()` — Laravel-хелпер экранирования HTML.
- Emoji заданы через Unicode escape (не литералами), чтобы файл был безопасно ASCII на всякий случай.
- Любые ошибки внутри handler'а глотаются — иначе падение Telegram убьёт лог.

- [ ] **Step 2: Проверить синтаксис**

Команда:

```bash
php -l /home/localhost/Projects/notifications/src/Logging/TelegramLogHandler.php
```

Ожидается: `No syntax errors detected`.

- [ ] **Step 3: Проверить автозагрузку**

Команда:

```bash
cd /home/localhost/Projects/notifications && composer dump-autoload && php -r "require 'vendor/autoload.php'; class_exists('Alexweb\Notifications\Logging\TelegramLogHandler') ? print('OK'.PHP_EOL) : print('FAIL'.PHP_EOL);"
```

Ожидается: `OK`.

- [ ] **Step 4: Коммит**

Команда:

```bash
cd /home/localhost/Projects/notifications && git add src/Logging/ && git commit -m "feat: add Monolog handler for telegram log channel"
```

---

## Task 8: Полный `README.md`

**Файлы:**
- Изменить: `README.md`

- [ ] **Step 1: Перезаписать `README.md` полной версией**

Файл `/home/localhost/Projects/notifications/README.md`:

```markdown
# alexweb/notifications

Внутренняя библиотека уведомлений для Laravel-проектов. Единый фасад, поддержка Laravel Notification-классов, лог-канал в Telegram.

В v1 поддерживается только один канал — Telegram — и один общий чат команды.

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
- `TELEGRAM_PARSE_MODE` (`HTML`) — режим форматирования Telegram (`HTML` | `Markdown` | `MarkdownV2`).

## Использование

### Быстрая текстовая отправка

```php
use Alexweb\Notifications\Facades\Notifier;

// В очередь (по умолчанию)
Notifier::send('Заказ №123 создан');

// С форматированием (HTML)
Notifier::send('<b>Ошибка</b>: <code>NullPointer</code>');

// Синхронно (для CLI и критичных случаев)
Notifier::sendNow('Критично: БД недоступна');

// Явный канал (для будущих каналов; сейчас работает только 'telegram')
Notifier::channel('telegram')->send('Алерт');
```

### Notification-классы для повторяющихся событий

Обычный Laravel Notification с методом `toTelegram()`:

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

Фасад сам подставляет получателя-«команду» и делает `Notification::send()`.

### Log-канал (алерты через `Log`)

Добавь в `config/logging.php`:

```php
'channels' => [
    // ...
    'telegram' => [
        'driver' => 'monolog',
        'handler' => \Alexweb\Notifications\Logging\TelegramLogHandler::class,
        'level' => 'error',
    ],
],
```

Использование:

```php
use Illuminate\Support\Facades\Log;

Log::channel('telegram')->error('Payment webhook failed', [
    'order_id' => $order->id,
    'reason' => $e->getMessage(),
]);
```

Для алертов о необработанных исключениях — в `bootstrap/app.php`:

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

- В окружениях `local` и `testing` — бросается `Alexweb\Notifications\Exceptions\MissingConfigurationException`, чтобы разработчик сразу заметил.
- В остальных окружениях (`production`) — уведомление молча пропускается, в дефолтный лог пишется `warning`, чтобы забытая настройка не роняла прод.

## Разработка

```bash
git clone git@github.com:alexweb/notifications.git
cd notifications
composer install
```

Тесты в v1 не пишутся — библиотека тестируется через использование в целевых проектах.
```

- [ ] **Step 2: Коммит**

Команда:

```bash
cd /home/localhost/Projects/notifications && git add README.md && git commit -m "docs: add complete README with usage examples"
```

---

## Task 9: Тег v0.1.0 + инструкции по публикации

**Файлы:** нет (только git-операции).

- [ ] **Step 1: Убедиться, что дерево чистое**

Команда:

```bash
cd /home/localhost/Projects/notifications && git status
```

Ожидается: `nothing to commit, working tree clean`.

- [ ] **Step 2: Просмотреть историю коммитов**

Команда:

```bash
cd /home/localhost/Projects/notifications && git log --oneline
```

Ожидается: цепочка ~9 коммитов (docs spec + 8 коммитов из Tasks 1-8).

- [ ] **Step 3: Создать тег `v0.1.0`**

Команда:

```bash
cd /home/localhost/Projects/notifications && git tag -a v0.1.0 -m "v0.1.0: initial release — telegram channel, facade, log handler"
git tag --list
```

Ожидается: в списке появляется `v0.1.0`.

- [ ] **Step 4: Инструкции для пользователя по публикации на GitHub**

Пользователю нужно выполнить самостоятельно (эти команды не запускаем — требуют доступ к GitHub аккаунту `alexweb`):

```bash
# Создать приватный репозиторий на GitHub через веб или gh CLI
gh repo create alexweb/notifications --private --source=/home/localhost/Projects/notifications --remote=origin

# Или вручную:
# 1. github.com → New repository → alexweb/notifications → Private
# 2. cd /home/localhost/Projects/notifications
# 3. git remote add origin git@github.com:alexweb/notifications.git

# Push с тегом
cd /home/localhost/Projects/notifications && git push -u origin main --tags
```

- [ ] **Step 5: Финальная проверка**

Команда:

```bash
cd /home/localhost/Projects/notifications && ls -la && git log --oneline && git tag --list
```

Ожидается: видны все файлы (composer.json, README.md, config/, src/, docs/, .gitignore), 9 коммитов, тег `v0.1.0`.

---

## Итог

После выполнения плана:
- Готовый пакет в `/home/localhost/Projects/notifications`
- Все файлы согласно спеке
- Тег `v0.1.0` создан локально
- Пользователь пушит в GitHub вручную (шаг 4 Task 9)

Интеграция в `journey-predictor-api` — **не входит в этот план**, будет отдельным дизайном + планом позже.
