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
        $appName = config('notifications.app_name') ?: config('app.name', 'App');

        $header = sprintf(
            '<b>[%s]</b> | %s <b>%s</b> — <code>%s</code>',
            e($appName),
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
