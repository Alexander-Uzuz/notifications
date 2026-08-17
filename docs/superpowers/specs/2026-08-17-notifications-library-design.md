# Дизайн: библиотека уведомлений `alexweb/notifications`

**Дата:** 2026-08-17
**Статус:** Approved
**Область:** v1 — только сам пакет; интеграция в целевые проекты — отдельный дизайн

## Цель

Внутренняя PHP/Laravel-библиотека для отправки уведомлений через разные каналы. В v1 поддерживается только Telegram. Библиотека подключается во все проекты компании (сейчас — 4 Laravel-репозитория) как composer-пакет и предоставляет:

- единый фасад для быстрой отправки текстовых сообщений;
- поддержку Laravel Notifications-классов для повторяющихся событий;
- Monolog-канал для отправки логов в Telegram через стандартный `Log`-фасад.

## Мотивация

Начальник поставил задачу: единая обёртка над уведомлениями, которую можно расширять новыми каналами (Slack, email, SMS) без переписывания вызовов в бизнес-коде. Ключевая ценность — не переизобретение Bot API, а стандартизация: единый конфиг, единый фасад, единый log-канал, единый способ тестирования, чтобы во всех 4 репозиториях уведомления работали одинаково.

## Область v1

**Входит:**
- Telegram-канал (через готовый пакет `laravel-notification-channels/telegram`)
- Отправка в один общий чат команды (`TELEGRAM_CHAT_ID` в `.env`)
- Фасад `Notifier` с методами `send`, `sendNow`, `channel`
- Поддержка Laravel Notification-классов
- Monolog handler для `Log::channel('telegram')`
- Тестовый хелпер `Notifier::fake()`
- README с инструкциями по установке и настройке

**НЕ входит (YAGNI):**
- Другие каналы (Slack, email, SMS, push)
- Регистрация конечных пользователей в боте, привязка `chat_id` к моделям
- Свой rate limiter (ограничение через один воркер очереди)
- Шаблоны, локализация, отписки, тихие часы, приоритеты
- Метрики/трейсинг отправок
- Работа с медиа через фасад (фото, файлы, кнопки — доступны только через Notification-классы, используя штатный API `TelegramMessage`)
- Интеграция в `journey-predictor-api` (отдельный дизайн + PR)

## Архитектурные решения

### 1. Пакет строится поверх Laravel Notifications

Регистрируем канал `telegram` через `Notification::extend()` (по факту его уже регистрирует `laravel-notification-channels/telegram`). Наш фасад под капотом всегда вызывает `Notification::send($recipient, $notification)`. Это даёт из коробки: очередь, ретраи, событийную модель, testing-хелперы.

**Причина:** все целевые репозитории на Laravel; переиспользуем инфраструктуру фреймворка вместо своей.

### 2. HTTP к Bot API делает внешняя зависимость

Используем `laravel-notification-channels/telegram` (де-факто стандарт, 1M+ установок). Наш пакет НЕ пишет собственный HTTP-клиент к `api.telegram.org`.

**Причина:** ценность — в обёртке (фасад, log-канал, конфиг), а не в SDK. Все Telegram-фичи (Markdown/HTML, кнопки, файлы) уже реализованы.

### 3. По умолчанию — отправка через очередь, синхронная опционально

Классы-нотификации с `ShouldQueue` уходят в job. Фасад `Notifier::send()` тоже ставит job. Отдельный метод `Notifier::sendNow()` — синхронная отправка (для CLI, отладки, критичных случаев). Log-канал — всегда синхронный (чтобы логи не терялись, если воркер лежит).

**Причина:** аптайм Telegram нестабилен, лимиты жёсткие; синхронная отправка блокирует основной поток и уязвима к 429/500.

### 4. Notifiable-получатель — синглтон «команда»

Класс `TelegramRecipient` реализует `Illuminate\Notifications\Notifiable`, метод `routeNotificationForTelegram()` возвращает `chat_id` из конфига. Один инстанс на приложение — резолвится через сервис-контейнер.

**Причина:** v1 = алерты команде в один чат. Модели пользователей с `chat_id` — вне области.

### 5. `parse_mode` по умолчанию — HTML

Telegram HTML требует escape только 3 символов (`<`, `>`, `&`). MarkdownV2 — 18 спецсимволов, часто ломает сообщения с непредсказуемым контентом (JSON в логах, имена пользователей).

**Причина:** алерты часто содержат произвольные строки; HTML безопаснее по умолчанию.

## Структура репозитория

```
notifications/
├── src/
│   ├── NotificationsServiceProvider.php
│   ├── Facades/
│   │   └── Notifier.php
│   ├── Notifier.php
│   ├── Channels/
│   │   └── Telegram/
│   │       └── TelegramRecipient.php
│   ├── Notifications/
│   │   └── SimpleTextNotification.php
│   ├── Logging/
│   │   └── TelegramLogHandler.php
│   ├── Testing/
│   │   └── FakesNotifications.php
│   └── Exceptions/
│       └── MissingConfigurationException.php
├── config/
│   └── notifications.php
├── composer.json
├── README.md
└── docs/
    └── superpowers/
        └── specs/
            └── 2026-08-17-notifications-library-design.md
```

## Зависимости

- `php: ^8.2`
- `laravel/framework: ^11.0|^12.0`
- `laravel-notification-channels/telegram: ^5.0` (или актуальная стабильная)

Dev-зависимости не нужны (тесты в v1 не пишем — правило проекта).

## Конфигурация

**Файл `config/notifications.php`** (публикуется через `vendor:publish`):

```php
return [
    'default' => env('NOTIFICATIONS_DEFAULT_CHANNEL', 'telegram'),

    'queue' => [
        'enabled'    => env('NOTIFICATIONS_QUEUE_ENABLED', true),
        'connection' => env('NOTIFICATIONS_QUEUE_CONNECTION', null),
        'name'       => env('NOTIFICATIONS_QUEUE_NAME', 'notifications'),
        'tries'      => env('NOTIFICATIONS_QUEUE_TRIES', 3),
    ],

    'channels' => [
        'telegram' => [
            'bot_token'  => env('TELEGRAM_BOT_TOKEN'),
            'chat_id'    => env('TELEGRAM_CHAT_ID'),
            'parse_mode' => env('TELEGRAM_PARSE_MODE', 'HTML'),
        ],
    ],
];
```

**`.env` целевого проекта:**

```
TELEGRAM_BOT_TOKEN=123456:AAA...
TELEGRAM_CHAT_ID=-1001234567890
```

**Log-канал** — вручную дописывается пользователем в `config/logging.php` целевого проекта (пример в README):

```php
'telegram' => [
    'driver'  => 'monolog',
    'handler' => \Alexweb\Notifications\Logging\TelegramLogHandler::class,
    'level'   => 'error',
],
```

## Публичный API

### Фасад `Notifier`

```php
use Alexweb\Notifications\Facades\Notifier;

// Быстрая текстовая отправка (через очередь)
Notifier::send('Заказ №123 создан');

// Явный канал (для будущих)
Notifier::channel('telegram')->send('Алерт: очередь переполнена');

// Синхронно
Notifier::sendNow('Критично: БД недоступна');

// Отправка Notification-класса
Notifier::send(new OrderCreated($order));
```

Под капотом фасад:
- если аргумент — строка, создаёт `SimpleTextNotification($text)` и вызывает `Notification::send($teamRecipient, $notification)`;
- если аргумент — экземпляр `Notification`, вызывает `Notification::send($teamRecipient, $arg)`;
- `sendNow()` — то же самое через `Notification::sendNow(...)`;
- `channel($name)` возвращает fluent builder, который переопределяет `via()` при следующем `send()`.

### Notification-классы

Пишутся как обычные Laravel Notifications с методом `toTelegram($notifiable)`, возвращающим `TelegramMessage`. Наш пакет не добавляет своего base-класса. Пример — в README.

### Log-канал

```php
Log::channel('telegram')->error('Payment webhook failed', [
    'order_id' => $order->id,
    'reason'   => $e->getMessage(),
]);
```

`TelegramLogHandler` форматирует запись как:

```
🔴 ERROR — channel.name
Payment webhook failed

order_id: 123
reason: Timeout
```

и вызывает `Notifier::sendNow(...)`. Синхронно, потому что логи.

### Тестовый хелпер

```php
Notifier::fake();

// ... код, отправляющий уведомления ...

Notifier::assertSent(fn ($notification) => $notification instanceof OrderCreated);
```

Реализован как обёртка над `Notification::fake()` — при вызове `Notifier::fake()` подменяем в контейнере фасад Laravel Notification на fake, все вызовы `Notifier::*` начинают перехватываться.

## Обработка ошибок

**Очередь:**
- `tries` из конфига (дефолт 3), экспоненциальный backoff 10s → 30s → 60s (метод `backoff(): array` в `SimpleTextNotification`; пользовательские Notification-классы могут переопределить свой)
- После всех попыток — стандартный `failed_jobs`, стандартное логирование Laravel
- **Никаких «алертов об упавшем алерте»** — риск бесконечной петли

**Синхронно (`sendNow` и Log-канал):**
- Без ретраев
- Исключение от Telegram ловится, пишется в дефолтный лог (`Log::error(...)`), приложение не падает
- В `TelegramLogHandler` — дополнительный guard: любое исключение внутри handler'а молча проглатывается (чтобы падение Telegram не убило пользовательское логирование)

**Отсутствие конфига (`TELEGRAM_BOT_TOKEN` или `TELEGRAM_CHAT_ID` не заданы):**
- В `local`/`testing` окружении — бросается `Alexweb\Notifications\Exceptions\MissingConfigurationException` (чтобы разработчик сразу увидел)
- В `production` — молча пропускается, пишется warning в дефолтный лог (чтобы забытая настройка не роняла прод)

## Rate limiting

В v1 отдельного лимитера нет. Полагаемся на очередь: если использовать один воркер `queue:work --queue=notifications`, естественный поток ≤ 30 msg/sec. Если 429-е появятся — добавим middleware/декоратор позже.

## Развёртывание пакета

1. Локальная разработка в `/home/localhost/Projects/notifications`
2. Push в приватный GitHub `github.com/alexweb/notifications` (личный аккаунт alex)
3. Тег `v0.1.0`
4. Позже: права передаются организации компании
5. Установка в целевых проектах через `composer.json`:
   ```json
   "repositories": [
       { "type": "vcs", "url": "git@github.com:alexweb/notifications.git" }
   ],
   "require": {
       "alexweb/notifications": "^0.1"
   }
   ```
6. Service provider регистрируется через package auto-discovery.

## Открытые вопросы

Пока нет. Все решения приняты в ходе брейншторма.

## История изменений

- **2026-08-17** — первая версия дизайна, согласована с пользователем.
