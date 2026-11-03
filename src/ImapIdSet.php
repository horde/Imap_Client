<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2011-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

use Generator;
use Iterator;
use InvalidArgumentException;

/**
 * A range-aware set of IMAP message identifiers (UIDs or sequence numbers).
 *
 * Unlike {@see Pop3IdSet} whose POP3 UIDLs are opaque unordered strings,
 * IMAP identifiers are positive integers with a total order. A set can
 * be serialized compactly as an IMAP message sequence string (RFC 3501
 * §9, `seq-number` / `sequence-set`): contiguous runs collapse to
 * `start:end`, e.g. `1:5,7,9:11`.
 *
 * A set is one of two things:
 *
 * - an explicit list of integer IDs, stored as compact ranges, or
 * - a single {@see ImapIdSetToken} special set (`1:*`, `*`, or `$`).
 *
 * The `$sequence` flag distinguishes message-sequence numbers (positional,
 * valid only for the current mailbox view) from UIDs (durable), mirroring
 * `Pop3IdSet::isSequence()`. It does not change storage. Only how a
 * consumer interprets the numbers.
 *
 * Immutable: {@see add()}/{@see remove()} return new instances rather than
 * mutating in place unlike the H5 `Horde_Imap_Client_Ids`.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2011-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapIdSet implements MessageIdSet
{
    /**
     * Explicit IDs as inclusive ranges in first-seen order. Ranges may
     * overlap; public list views deduplicate them.
     *
     * @var list<array{int, int}>
     */
    private readonly array $ranges;

    /**
     * The special token this set represents or null for an explicit list.
     */
    private readonly ?ImapIdSetToken $token;

    /**
     * @param iterable<int|string>|string|int|ImapIdSetToken $ids
     *        Explicit IDs (array/iterable of ints or numeric strings), an
     *        IMAP sequence string (`1:5,7`), a single ID or a special
     *        {@see ImapIdSetToken}. An empty argument yields an empty set.
     * @param bool $sequence  True if these are sequence numbers, not UIDs.
     */
    public function __construct(
        iterable|string|int|ImapIdSetToken $ids = [],
        private readonly bool $sequence = false,
    ) {
        if ($ids instanceof ImapIdSetToken) {
            $this->token = $ids;
            $this->ranges = [];

            return;
        }

        $this->token = null;
        $this->ranges = self::resolveRanges($ids);
    }

    /**
     * Build a set from an IMAP message sequence string (`1:5,7,9:*`).
     *
     * A lone special token (`1:*`, `*`, `$`) is recognized and preserved as
     * such rather than being expanded into an integer list.
     */
    public static function fromSequenceString(string $str, bool $sequence = false): self
    {
        $trimmed = trim($str);

        $token = ImapIdSetToken::tryFrom($trimmed);
        if ($token !== null) {
            return new self($token, $sequence);
        }

        return new self($trimmed, $sequence);
    }

    /**
     * Is this set one of the special forms (`1:*`, `*`, `$`)?
     */
    public function isSpecial(): bool
    {
        return $this->token !== null;
    }

    /**
     * The special token this set represents or null for an explicit list.
     */
    public function token(): ?ImapIdSetToken
    {
        return $this->token;
    }

    public function isSequence(): bool
    {
        return $this->sequence;
    }

    public function isEmpty(): bool
    {
        return $this->token === null && $this->ranges === [];
    }

    /**
     * The smallest ID, or null for an empty or special set.
     */
    public function min(): ?int
    {
        if ($this->ranges === []) {
            return null;
        }

        return min(array_column($this->ranges, 0));
    }

    /**
     * The largest ID, or null for an empty or special set.
     */
    public function max(): ?int
    {
        if ($this->ranges === []) {
            return null;
        }

        return max(array_column($this->ranges, 1));
    }

    /**
     * Return a new set with $ids added (union). Adding to a special set
     * replaces the token with the resulting explicit list.
     *
     * @param iterable<int|string>|string|int|self $ids
     */
    public function add(iterable|string|int|self $ids): self
    {
        $ranges = $this->token === null ? $this->ranges : [];
        $ranges = [...$ranges, ...self::resolveRanges($ids)];

        return new self(self::rangesToSequenceString($ranges), $this->sequence);
    }

    /**
     * Return a new set with $ids removed. Removing from a special set is a
     * no-op (there is no concrete list to subtract from).
     *
      * @param iterable<int|string>|string|int|self $ids
     */
    public function remove(iterable|string|int|self $ids): self
    {
        if ($this->token !== null) {
            return $this;
        }

        $drop = self::mergeRanges(self::resolveRanges($ids));
        $remaining = [];
        foreach ($this->ranges as [$start, $end]) {
            foreach ($drop as [$dropStart, $dropEnd]) {
                if ($dropEnd < $start || $dropStart > $end) {
                    continue;
                }
                if ($dropStart > $start) {
                    $remaining[] = [$start, $dropStart - 1];
                }
                $start = max($start, $dropEnd + 1);
                if ($start > $end) {
                    break;
                }
            }
            if ($start <= $end) {
                $remaining[] = [$start, $end];
            }
        }

        return new self(
            self::rangesToSequenceString($remaining),
            $this->sequence,
        );
    }

    /**
     * @return list<int>
     */
    public function toArray(): array
    {
        $ids = [];
        foreach ($this->ranges as [$start, $end]) {
            for ($id = $start; $id <= $end; ++$id) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    public function count(): int
    {
        $count = 0;
        foreach (self::mergeRanges($this->ranges) as [$start, $end]) {
            $count += $end - $start + 1;
        }

        return $count;
    }

    /**
     * @return Iterator<int, int>
     */
    public function getIterator(): Iterator
    {
        return (static function (array $ranges): Generator {
            foreach (self::mergeRanges($ranges) as [$start, $end]) {
                for ($id = $start; $id <= $end; ++$id) {
                    yield $id;
                }
            }
        })($this->ranges);
    }

    /**
     * The IMAP sequence string, IDs sorted ascending and ranges compressed.
     *
     * A special set renders as its wire token (`1:*`, `*`, `$`). An empty
     * set renders as the empty string.
     */
    public function __toString(): string
    {
        if ($this->token !== null) {
            return $this->token->toWire();
        }

        $parts = [];
        foreach (self::mergeRanges($this->ranges) as [$start, $end]) {
            $parts[] = self::rangePart($start, $end);
        }

        return implode(',', $parts);
    }

    /**
     * Resolve a constructor/add/remove argument into a flat list of ints.
     *
     * @param iterable<int|string>|string|int|self $ids
     *
     * @return list<int>
     */
    private static function resolveRanges(iterable|string|int $ids): array
    {
        if ($ids instanceof self) {
            return $ids->ranges;
        }

        if (is_int($ids)) {
            return [[$ids, $ids]];
        }

        if (is_string($ids)) {
            return self::parseWireRanges($ids);
        }

        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            $out[] = [$id, $id];
        }

        return $out;
    }

    /**
     * Parse an IMAP sequence string into compact inclusive ranges.
     *
     * @return list<array{int, int}>
     */
    private static function parseWireRanges(string $str): array
    {
        $str = trim($str);
        if ($str === '') {
            return [];
        }

        $ranges = [];
        foreach (explode(',', $str) as $part) {
            $range = explode(':', $part, 2);
            if (isset($range[1])) {
                if (!ctype_digit($range[0]) || !ctype_digit($range[1])) {
                    throw new InvalidArgumentException(
                        'IMAP ID ranges must have numeric endpoints.'
                    );
                }
                $start = (int) $range[0];
                $end = (int) $range[1];
                $ranges[] = ($start <= $end)
                    ? [$start, $end]
                    : [$end, $start];
            } else {
                if (!ctype_digit($part)) {
                    throw new InvalidArgumentException(
                        'IMAP IDs must be decimal integers.'
                    );
                }
                $id = (int) $part;
                $ranges[] = [$id, $id];
            }
        }

        return $ranges;
    }

    /**
     * Merge overlapping or adjacent ranges into ascending, unique ranges.
     *
     * @param list<array{int, int}> $ranges
     *
     * @return list<array{int, int}>
     */
    private static function mergeRanges(array $ranges): array
    {
        if ($ranges === []) {
            return [];
        }

        usort($ranges, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [$ranges[0]];
        foreach (array_slice($ranges, 1) as [$start, $end]) {
            $last = count($merged) - 1;
            if ($start <= $merged[$last][1] + 1) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    /**
     * Serialize ranges into a compact numeric IMAP sequence string.
     *
     * @param list<array{int, int}> $ranges
     */
    private static function rangesToSequenceString(array $ranges): string
    {
        $parts = [];
        foreach (self::mergeRanges($ranges) as [$start, $end]) {
            $parts[] = self::rangePart($start, $end);
        }

        return implode(',', $parts);
    }

    /**
     * Compress a sorted, contiguity-collapsed IMAP sequence string.
     *
     * @param list<int> $ids
     */
    /**
     * Render a single range or singleton such as `5` vs `5:9`.
     */
    private static function rangePart(int $start, int $end): string
    {
        return $start === $end ? (string) $start : $start . ':' . $end;
    }
}
