<?php

namespace Omnipay\PluxeeSodexo\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * Collects every log record so tests can assert on the injected PSR-3
 * logging without a real logging stack.
 */
class ArrayLogger extends AbstractLogger
{
    /**
     * @var array<int, array{level: string, message: string, context: array<string, mixed>}>
     */
    public array $records = [];

    /**
     * @param  mixed  $level
     * @param  string|\Stringable  $message
     * @param  array<string, mixed>  $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function messages(): array
    {
        return array_column($this->records, 'message');
    }

    /**
     * @return array<int, string>
     */
    public function levels(): array
    {
        return array_column($this->records, 'level');
    }

    public function hasMessage(string $needle): bool
    {
        foreach ($this->messages() as $message) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{level: string, message: string, context: array<string, mixed>}|null
     */
    public function findByMessage(string $needle): ?array
    {
        foreach ($this->records as $record) {
            if (str_contains($record['message'], $needle)) {
                return $record;
            }
        }

        return null;
    }
}
