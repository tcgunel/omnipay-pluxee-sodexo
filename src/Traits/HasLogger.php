<?php

namespace Omnipay\PluxeeSodexo\Traits;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Optional PSR-3 logging.
 *
 * The package never writes to files or the error log itself: every gateway
 * accepts any PSR-3 logger (Laravel's Log, Monolog, ...) and forwards it to
 * every request it creates. Without a logger a NullLogger is used, so there
 * is no output and no overhead.
 */
trait HasLogger
{
    protected ?LoggerInterface $logger = null;

    /**
     * @return $this
     */
    public function setLogger(?LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger ??= new NullLogger();
    }
}
