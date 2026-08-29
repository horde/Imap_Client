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
use Countable;
use Horde\Imap\Client\Exception\WireEncodingException;
use IteratorAggregate;
use Traversable;

/**
 * An IMAP parenthesized list (RFC 3501 §4.4).
 *
 * `escape()` only works if every member (recursively) is itself
 * inline-representable. A list holding a member that requires a literal
 * cannot be flattened into one string. The interaction/pipelining layer
 * has to walk the tree itself, sending literal members through their own
 * `{n}`/continuation exchange and everything else inline in between.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 *
 * @implements IteratorAggregate<int, ImapWireEncodable>
 */
final class ImapWireList implements ImapWireEncodable, Countable, IteratorAggregate
{
    /** @var list<ImapWireEncodable> */
    private array $items = [];

    /**
     * @param iterable<ImapWireEncodable|string> $items
     */
    public function __construct(iterable $items = [])
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }

    /**
     * Add a member. A plain string is treated as an atom, matching how
     * bare flag/keyword names are usually written.
     */
    public function add(ImapWireEncodable|string $item): self
    {
        $this->items[] = is_string($item) ? new ImapWireAtom($item) : $item;

        return $this;
    }

    public function isLiteral(): bool
    {
        // A parenthesized list is never itself a literal; a literal
        // member inside it surfaces as an escape() failure instead.
        return false;
    }

    public function isBinary(): bool
    {
        return false;
    }

    public function length(): int
    {
        return strlen($this->escape());
    }

    public function escape(): string
    {
        $parts = [];

        foreach ($this->items as $item) {
            $parts[] = $item instanceof self
                ? '(' . $item->escape() . ')'
                : $item->escape();
        }

        return implode(' ', $parts);
    }

    public function rawBytes(): string
    {
        throw new WireEncodingException('A parenthesized list cannot be sent as a literal.');
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, ImapWireEncodable>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
