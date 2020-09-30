<?php

declare(strict_types=1);

namespace SymfonyBehatContext\Collection;

use Symfony\Component\Cache\ResettableInterface;

class ExtendedMockHttpClientCollection implements ResettableInterface
{
    private iterable $handlers;

    public function __construct(iterable $handlers)
    {
        $this->handlers = $handlers;
    }

    public function getHandlers(): iterable
    {
        return $this->handlers;
    }

    public function setHandlers(iterable $handlers): void
    {
        $this->handlers = $handlers;
    }

    public function reset(): void
    {
        $this->handlers = [];
    }
}
