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

use Horde\Imap\Client\Exception\WireEncodingException;

/**
 * One IMAP client command (RFC 3501 §2.2.1): A tag, a command name and
 * zero or more arguments.
 *
 * `segments()`:
 * An argument or a member nested arbitrarily deep
 * inside one {@see ImapWireList} that needs a literal cannot be flattened into the plain
 * command string the way {@see ImapWireEncodable::escape()} normally
 * would.
 * This walks the argument tree and produces an ordered sequence
 * of {@see ImapCommandSegment}s instead. Plain text to write as-is,
 * interspersed with literal payloads a connection sends only after
 * announcing their length
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapCommand
{
    /** @var list<ImapWireEncodable> */
    private readonly array $arguments;

    /**
     * @param iterable<ImapWireEncodable|string> $arguments A plain string
     *                                                       is treated as
     *                                                       an atom.
     */
    public function __construct(
        public readonly string $tag,
        public readonly string $name,
        iterable $arguments = [],
    ) {
        $normalized = [];

        foreach ($arguments as $argument) {
            $normalized[] = is_string($argument) ? new ImapWireAtom($argument) : $argument;
        }

        $this->arguments = $normalized;
    }

    /**
     * Does any argument (recursively, through nested lists) require a
     * literal? Pipelining more than one such command at a time is not
     * safe since only one continuation exchange can be outstanding on
     * a connection.
     */
    public function needsContinuation(): bool
    {
        foreach ($this->arguments as $argument) {
            if ($this->containsLiteral($argument)) {
                return true;
            }
        }

        return false;
    }

    private function containsLiteral(ImapWireEncodable $value): bool
    {
        if ($value->isLiteral()) {
            return true;
        }

        if (!$value instanceof ImapWireList) {
            return false;
        }

        foreach ($value as $member) {
            if ($this->containsLiteral($member)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<ImapCommandSegment>
     */
    public function segments(): array
    {
        $segments = [ImapCommandSegment::text($this->tag . ' ' . $this->name)];

        foreach ($this->arguments as $argument) {
            $segments[] = ImapCommandSegment::text(' ');
            array_push($segments, ...$this->flatten($argument));
        }

        return $segments;
    }

    /**
     * @return list<ImapCommandSegment>
     */
    private function flatten(ImapWireEncodable $value): array
    {
        if ($value->isLiteral()) {
            return [ImapCommandSegment::literal($value->isBinary(), $value->rawBytes())];
        }

        try {
            $escaped = $value->escape();

            // ImapWireList::escape() only joins its members.
            // A list used directly as a command argument is its own top-level "parent" here.
            // Otherwise the parent is responsible for parantheses.
            return [ImapCommandSegment::text($value instanceof ImapWireList ? "({$escaped})" : $escaped)];
        } catch (WireEncodingException $e) {
            if (!$value instanceof ImapWireList) {
                // Only a list can fail escape() without being a literal
                throw $e;
            }
        }

        $segments = [ImapCommandSegment::text('(')];
        $first = true;

        foreach ($value as $member) {
            if (!$first) {
                $segments[] = ImapCommandSegment::text(' ');
            }

            $first = false;
            array_push($segments, ...$this->flatten($member));
        }

        $segments[] = ImapCommandSegment::text(')');

        return $segments;
    }
}
