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
 * Collects the UIDs reported by untagged `* VANISHED` responses
 * (RFC 7162 §3.2.10).
 *
 * VANISHED comes in two forms, both carrying a UID sequence set as their
 * final token:
 *   * VANISHED (EARLIER) 41,43:47   (a QRESYNC FETCH / SELECT reply)
 *   * VANISHED 41,43:47             (a live expunge notification)
 * The set is always UIDs, never sequence numbers (RFC 7162 §3.2.10), so
 * the returned {@see ImapIdSet} is a UID set.
 *
 * Stateless: every method is static.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapVanishedParser
{
    private function __construct() {}

    /**
     * @param list<ImapResponse> $untagged
     */
    public static function parse(array $untagged): ImapIdSet
    {
        $uids = [];

        foreach ($untagged as $response) {
            if (!$response->isUntagged() || $response->data === [] || !is_string($response->data[0])) {
                continue;
            }

            if (strtoupper($response->data[0]) !== 'VANISHED') {
                continue;
            }

            // The sequence set is the last string token; the optional
            // (EARLIER) marker is a nested array before it.
            $set = null;

            foreach (array_slice($response->data, 1) as $token) {
                if (is_string($token)) {
                    $set = $token;
                }
            }

            if ($set !== null) {
                foreach (ImapIdSet::fromSequenceString($set, false)->toArray() as $uid) {
                    $uids[] = $uid;
                }
            }
        }

        return new ImapIdSet($uids, false);
    }
}
