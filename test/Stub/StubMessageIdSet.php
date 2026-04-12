<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Stub;

use ArrayIterator;
use Horde\Imap\Client\MessageIdSet;
use Traversable;

/**
 * Minimal MessageIdSet implementation for testing.
 */
class StubMessageIdSet implements MessageIdSet
{
    /** @param array<int|string> $ids */
    public function __construct(
        private readonly array $ids = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }

    public function toArray(): array
    {
        return $this->ids;
    }

    public function __toString(): string
    {
        return implode(',', array_map('strval', $this->ids));
    }

    public function count(): int
    {
        return count($this->ids);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->ids);
    }
}
