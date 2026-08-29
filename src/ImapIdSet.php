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

use ArrayIterator;
use Iterator;

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
 * - an explicit list of integer IDs or
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
     * Explicit IDs in first-seen order. Empty when this is a special set.
     *
     * @var list<int>
     */
    private readonly array $ids;

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
            $this->ids = [];

            return;
        }

        $this->token = null;
        $this->ids = self::normalize(self::resolve($ids));
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
        return $this->token === null && $this->ids === [];
    }

    /**
     * The smallest ID, or null for an empty or special set.
     */
    public function min(): ?int
    {
        return $this->ids === [] ? null : min($this->ids);
    }

    /**
     * The largest ID, or null for an empty or special set.
     */
    public function max(): ?int
    {
        return $this->ids === [] ? null : max($this->ids);
    }

    /**
     * Return a new set with $ids added (union). Adding to a special set
     * replaces the token with the resulting explicit list.
     *
     * @param iterable<int|string>|string|int $ids
     */
    public function add(iterable|string|int $ids): self
    {
        return new self([...$this->ids, ...self::resolve($ids)], $this->sequence);
    }

    /**
     * Return a new set with $ids removed. Removing from a special set is a
     * no-op (there is no concrete list to subtract from).
     *
     * @param iterable<int|string>|string|int $ids
     */
    public function remove(iterable|string|int $ids): self
    {
        if ($this->token !== null) {
            return $this;
        }

        $drop = self::normalize(self::resolve($ids));

        return new self(array_values(array_diff($this->ids, $drop)), $this->sequence);
    }

    /**
     * @return list<int>
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
     * @return Iterator<int, int>
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->ids);
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

        return self::toSequenceString($this->ids);
    }

    /**
     * Resolve a constructor/add/remove argument into a flat list of ints.
     *
     * @param iterable<int|string>|string|int $ids
     *
     * @return list<int>
     */
    private static function resolve(iterable|string|int $ids): array
    {
        if (is_int($ids)) {
            return [$ids];
        }

        if (is_string($ids)) {
            return self::fromWireList($ids);
        }

        $out = [];
        foreach ($ids as $id) {
            $out[] = (int) $id;
        }

        return $out;
    }

    /**
     * Deduplicate while preserving first-seen order.
     *
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private static function normalize(array $ids): array
    {
        return array_keys(array_flip($ids));
    }

    /**
     * Parse an IMAP sequence string into an int list with ranges expanded.
     *
     * @return list<int>
     */
    private static function fromWireList(string $str): array
    {
        $str = trim($str);
        if ($str === '') {
            return [];
        }

        $ids = [];

        foreach (explode(',', $str) as $part) {
            $range = explode(':', $part, 2);
            if (isset($range[1])) {
                $start = (int) $range[0];
                $end = (int) $range[1];
                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }
                for ($i = $start; $i <= $end; ++$i) {
                    $ids[] = $i;
                }
            } else {
                $ids[] = (int) $part;
            }
        }

        return $ids;
    }

    /**
     * Compress a sorted, contiguity-collapsed IMAP sequence string.
     *
     * @param list<int> $ids
     */
    private static function toSequenceString(array $ids): string
    {
        if ($ids === []) {
            return '';
        }

        sort($ids, SORT_NUMERIC);

        $out = [];
        $start = $prev = $ids[0];

        foreach (array_slice($ids, 1) as $id) {
            if ($id === $prev + 1) {
                $prev = $id;
                continue;
            }

            $out[] = self::rangePart($start, $prev);
            $start = $prev = $id;
        }

        $out[] = self::rangePart($start, $prev);

        return implode(',', $out);
    }

    /**
     * Render a single range or singleton such as `5` vs `5:9`.
     */
    private static function rangePart(int $start, int $end): string
    {
        return $start === $end ? (string) $start : $start . ':' . $end;
    }
}
