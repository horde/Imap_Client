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
 * Turns the untagged `* THREAD` responses of a THREAD command into an
 * {@see ImapThreadResult} (RFC 5256 §4).
 *
 * The response is a sequence of top-level parenthesized lists, one per
 * thread, each nesting further lists to express the reply tree, e.g.
 * `* THREAD (2)(3 6 (4 23)(44 7 96))`. The tokenizer hands these back as
 * nested arrays; this flattens each thread into an ordered id => level
 * map the way the legacy `_parseThreadLevel` walker did off its streaming
 * cursor: scalar ids at a given depth take increasing levels, and a
 * nested list continues from the current level rather than resetting it.
 *
 * Stateless: every method is static. `$sequence` records the id kind.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapThreadParser
{
    private function __construct() {}

    /**
     * @param list<ImapResponse> $untagged
     */
    public static function parse(array $untagged, bool $sequence): ImapThreadResult
    {
        $threads = [];

        foreach ($untagged as $response) {
            if (!$response->isUntagged() || $response->data === [] || !is_string($response->data[0])) {
                continue;
            }

            if (strtoupper($response->data[0]) !== 'THREAD') {
                continue;
            }

            foreach (array_slice($response->data, 1) as $thread) {
                if (!is_array($thread)) {
                    continue;
                }

                $flat = [];
                self::flatten($thread, $flat, 0);

                if ($flat !== []) {
                    $threads[] = $flat;
                }
            }
        }

        return new ImapThreadResult($threads, $sequence);
    }

    /**
     * Flatten one thread's nested id lists into an ordered id => level
     * map. Within a branch, scalar ids take increasing levels; a nested
     * sub-thread recurses starting at the branch's current level, and
     * sibling sub-threads at the same position therefore share that level
     * (RFC 5256 §4). This mirrors the legacy `_parseThreadLevel` walker,
     * which passed its level by value on recursion so a child's advance
     * never leaked back to its parent or its siblings.
     *
     * @param list<mixed>     $nodes
     * @param array<int, int> $flat
     */
    private static function flatten(array $nodes, array &$flat, int $level): void
    {
        foreach ($nodes as $node) {
            if (is_array($node)) {
                self::flatten($node, $flat, $level);

                continue;
            }

            if (is_string($node) && ctype_digit($node)) {
                $flat[(int) $node] = $level++;
            }
        }
    }
}
