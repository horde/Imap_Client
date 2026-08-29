<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

use ArrayIterator;
use Iterator;

/**
 * A flat, unordered set of POP3 message identifiers.
 *
 * Unlike IMAP UIDs, POP3 UIDLs (RFC 1939 §7) are opaque strings with no
 * ordering or range guarantee. "there is no requirement they need be
 * incrementing". So, unlike an `ImapIdSet` this never attempts
 * range-compression. `$sequence` distinguishes a set of ephemeral
 * session-local message-sequence numbers (1..N, valid for one session
 * only) from a set of durable UIDL strings.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class Pop3IdSet implements MessageIdSet
{
    /** @var list<int|string> */
    private readonly array $ids;

    /**
     * @param array<int|string> $ids
     */
    public function __construct(array $ids = [], private readonly bool $sequence = false)
    {
        // Preserve first-seen order while deduplicating, matching the
        // legacy `array_keys(array_flip($this->_ids))` behavior.
        $this->ids = array_keys(array_flip($ids));
    }

    /**
     * Parse a POP3 message sequence string. Space-delimited. The only
     * printable ASCII character RFC 1939 §7 disallows in a UID.
     */
    public static function fromSequenceString(string $str, bool $sequence = false): self
    {
        $trimmed = trim($str);

        return new self($trimmed === '' ? [] : explode(' ', $trimmed), $sequence);
    }

    public function isSequence(): bool
    {
        return $this->sequence;
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }

    /**
     * @return array<int|string>
     */
    public function toArray(): array
    {
        return $this->ids;
    }

    public function count(): int
    {
        return count($this->ids);
    }

    /**
     * @return Iterator<int, int|string>
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->ids);
    }

    public function __toString(): string
    {
        return implode(' ', $this->ids);
    }
}
