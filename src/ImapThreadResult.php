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

use Countable;
use stdClass;

/**
 * The threaded result of {@see ImapClient::thread()} (RFC 5256).
 *
 * The internal structure is a list of threads, each an ordered map of
 * message id to nesting level (`[baseId => 0, childId => 1, ...]`), the
 * shape the THREAD response's nested parenthesized lists flatten into.
 * `$sequence` records whether the ids are message sequence numbers or
 * UIDs, matching the mode the command was sent in.
 *
 * The legacy `Serializable` surface is dropped; a caller that needs to
 * persist a thread result serializes the value object directly.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapThreadResult implements Countable
{
    /**
     * @param list<array<int, int>> $threads One entry per thread, each an
     *                                       ordered id => level map.
     */
    public function __construct(
        private readonly array $threads,
        private readonly bool $sequence = false,
    ) {}

    /**
     * Whether the ids are message sequence numbers (true) or UIDs (false).
     */
    public function isSequence(): bool
    {
        return $this->sequence;
    }

    /**
     * Every message id across all threads, in thread order, as an
     * {@see ImapIdSet}.
     */
    public function messageList(): ImapIdSet
    {
        return new ImapIdSet($this->allIds(), $this->sequence);
    }

    /**
     * The thread containing `$index`, as an ordered map of id to a small
     * object describing its place in the tree, or an empty array if the
     * id is not part of any thread.
     *
     * Each value object carries:
     * - `base`  (?int)  The thread's base id, or null for a lone message.
     * - `level` (int)   The message's nesting level.
     * - `last`  (bool)  Whether it is the last id at its level.
     *
     * @return array<int, stdClass>
     */
    public function getThread(int $index): array
    {
        foreach ($this->threads as $thread) {
            if (isset($thread[$index])) {
                return $this->describeThread($thread);
            }
        }

        return [];
    }

    /**
     * Every thread, each described the same way {@see getThread()}
     * describes one.
     *
     * @return list<array<int, stdClass>>
     */
    public function getThreads(): array
    {
        return array_map($this->describeThread(...), $this->threads);
    }

    public function count(): int
    {
        return count($this->allIds());
    }

    /**
     * @return list<int>
     */
    private function allIds(): array
    {
        $ids = [];

        foreach ($this->threads as $thread) {
            foreach (array_keys($thread) as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Turn one id => level map into the id => stdClass description
     * {@see getThread()}/{@see getThreads()} return, computing the `base`
     * and per-level `last` flags. Ported verbatim from the legacy
     * `Data_Thread::getThread()` traversal.
     *
     * @param array<int, int> $thread
     *
     * @return array<int, stdClass>
     */
    private function describeThread(array $thread): array
    {
        $base = count($thread) > 1 ? array_key_first($thread) : null;

        /** @var array<int, int> $levels Last id seen at each level. */
        $levels = [];
        $out = [];
        $last = 0;

        foreach ($thread as $id => $level) {
            $ob = new stdClass();
            $ob->base = $base;
            $ob->level = $level;
            $ob->last = false;
            $out[$id] = $ob;

            if ($last < $level && isset($levels[$level])) {
                $out[$levels[$level]]->last = true;
            }

            $levels[$level] = $id;
            $last = $level;
        }

        foreach ($levels as $id) {
            $out[$id]->last = true;
        }

        return $out;
    }
}
