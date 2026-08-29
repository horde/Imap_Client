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
use IteratorAggregate;
use Traversable;

/**
 * The set of namespaces returned by {@see ImapClient::getNamespaces()}
 * (RFC 2342).
 *
 * Namespaces are keyed by their UTF-8 name so a caller can look one up
 * directly, and iterated in the order the server reported them. The
 * legacy `ArrayAccess`/`Serializable` surface is dropped in favour of
 * the explicit {@see get()} and {@see getForMailbox()} lookups.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 *
 * @implements IteratorAggregate<string, ImapNamespace>
 */
final class ImapNamespaceList implements Countable, IteratorAggregate
{
    /** @var array<string, ImapNamespace> */
    private array $namespaces = [];

    /**
     * @param iterable<ImapNamespace> $namespaces
     */
    public function __construct(iterable $namespaces = [])
    {
        foreach ($namespaces as $namespace) {
            $this->namespaces[$namespace->name] = $namespace;
        }
    }

    /**
     * The namespace registered under an exact name, or null if none.
     */
    public function get(string $name): ?ImapNamespace
    {
        return $this->namespaces[$name] ?? null;
    }

    /**
     * The namespace a mailbox path belongs to, matching the longest
     * prefix first and falling back to the empty ("") namespace.
     *
     * @param bool $personalOnly When true, the empty-namespace fallback
     *                           applies only if that namespace is
     *                           personal (RFC 2342 personal namespace).
     */
    public function getForMailbox(string $mailbox, bool $personalOnly = false): ?ImapNamespace
    {
        if (isset($this->namespaces[$mailbox])) {
            return $this->namespaces[$mailbox];
        }

        foreach ($this->namespaces as $namespace) {
            if ($namespace->name !== '' && str_starts_with($mailbox . ($namespace->delimiter ?? ''), $namespace->name)) {
                return $namespace;
            }
        }

        $empty = $this->namespaces[''] ?? null;

        if ($empty === null) {
            return null;
        }

        return (!$personalOnly || $empty->type === NamespaceType::Personal) ? $empty : null;
    }

    public function count(): int
    {
        return count($this->namespaces);
    }

    /**
     * @return Traversable<string, ImapNamespace>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->namespaces);
    }
}
