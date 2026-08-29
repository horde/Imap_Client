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

/**
 * Tracks which command tags are still outstanding on a connection.
 *
 * RFC 3501 §5.5 lets a client send more than one command before any of
 * their tagged responses arrive, as long as none of the outstanding
 * commands needs a continuation response. This class only does the
 * bookkeeping (which tag belongs to which {@see ImapCommand} and
 * completing one when its tagged response shows up); it does not read
 * from or write to the connection itself.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapPipeline
{
    /** @var array<string, ImapCommand> */
    private array $commands = [];

    public function enqueue(ImapCommand $command): void
    {
        $this->commands[$command->tag] = $command;
    }

    public function isPending(string $tag): bool
    {
        return isset($this->commands[$tag]);
    }

    /**
     * Remove and return the command for a tag, or null if that tag is
     * not (or is no longer) outstanding. A dangling tagged response i.e.
     * one left over from an aborted earlier exchange.
     */
    public function complete(string $tag): ?ImapCommand
    {
        $command = $this->commands[$tag] ?? null;
        unset($this->commands[$tag]);

        return $command;
    }

    public function count(): int
    {
        return count($this->commands);
    }

    public function isEmpty(): bool
    {
        return $this->commands === [];
    }
}
