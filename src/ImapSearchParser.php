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
 * Turns the untagged responses of a `SEARCH` command into an
 * {@see ImapSearchResult}, handling both the classic `* SEARCH` form
 * (RFC 3501 §7.2.5) and the `* ESEARCH` form (RFC 4731 / RFC 4466 §2.6.2).
 *
 * Classic `* SEARCH 1 3 5` carries a space separated id list that may
 * span more than one untagged line; each line is folded into the running
 * match set. `* ESEARCH (TAG "A1") UID COUNT 5 MIN 1 MAX 19 ALL 4:19,21`
 * carries named return items after an optional search-correlator and an
 * optional `UID` marker; the `ALL` item is a compact sequence set parsed
 * through {@see ImapIdSet}.
 *
 * Stateless: every method is static. `$sequence` selects how ids are
 * interpreted, matching the mode the command was sent in.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapSearchParser
{
    private function __construct() {}

    /**
     * @param list<ImapResponse> $untagged
     */
    public static function parse(array $untagged, bool $sequence): ImapSearchResult
    {
        /** @var list<int> $matches */
        $matches = [];
        $count = null;
        $min = null;
        $max = null;
        $modseq = null;
        $relevancy = [];
        $usedEsearch = false;

        foreach ($untagged as $response) {
            if (!$response->isUntagged() || $response->data === [] || !is_string($response->data[0])) {
                continue;
            }

            $keyword = strtoupper($response->data[0]);

            // A SORT reply (RFC 5256) shares the classic SEARCH shape: a
            // space-separated id list, but in the server's sort order.
            if ($keyword === 'SEARCH' || $keyword === 'SORT') {
                foreach (array_slice($response->data, 1) as $token) {
                    if (is_string($token) && ctype_digit($token)) {
                        $matches[] = (int) $token;
                    }
                }

                continue;
            }

            if ($keyword === 'ESEARCH') {
                $usedEsearch = true;
                self::parseEsearch($response->data, $sequence, $matches, $count, $min, $max, $modseq, $relevancy);
            }
        }

        $match = new ImapIdSet($matches, $sequence);

        // Classic SEARCH does not report COUNT/MIN/MAX. Derive them from
        // the matching set so the caller sees a uniform result regardless
        // of which wire form the server used.
        if (!$usedEsearch) {
            $ids = $match->toArray();
            $count = count($ids);
            $min = $ids === [] ? null : min($ids);
            $max = $ids === [] ? null : max($ids);
        }

        return new ImapSearchResult(
            match: $match,
            count: $count,
            min: $min,
            max: $max,
            relevancy: $relevancy,
            modseq: $modseq,
        );
    }

    /**
     * @param list<mixed>  $data      The whole ESEARCH response tokens,
     *                                starting with the `ESEARCH` word.
     * @param list<int>    $matches
     * @param list<int>    $relevancy
     *
     * @param-out list<int> $matches
     * @param-out list<int> $relevancy
     */
    private static function parseEsearch(
        array $data,
        bool $sequence,
        array &$matches,
        ?int &$count,
        ?int &$min,
        ?int &$max,
        ?int &$modseq,
        array &$relevancy,
    ): void {
        $index = 1;

        // Skip the optional search-correlator "(TAG "A1")".
        if (isset($data[$index]) && is_array($data[$index])) {
            $index++;
        }

        // Skip the optional "UID" marker (present for a UID SEARCH).
        if (isset($data[$index]) && is_string($data[$index]) && strtoupper($data[$index]) === 'UID') {
            $index++;
        }

        $total = count($data);

        for (; $index < $total; $index += 2) {
            $name = $data[$index];
            $value = $data[$index + 1] ?? null;

            if (!is_string($name)) {
                continue;
            }

            switch (strtoupper($name)) {
                case 'ALL':
                    if (is_string($value)) {
                        foreach (ImapIdSet::fromSequenceString($value, $sequence)->toArray() as $id) {
                            $matches[] = $id;
                        }
                    }
                    break;

                case 'COUNT':
                    $count = self::intOrNull($value);
                    break;

                case 'MIN':
                    $min = self::intOrNull($value);
                    break;

                case 'MAX':
                    $max = self::intOrNull($value);
                    break;

                case 'MODSEQ':
                    $modseq = self::intOrNull($value);
                    break;

                case 'RELEVANCY':
                    if (is_array($value)) {
                        foreach ($value as $score) {
                            if (is_string($score) && ctype_digit($score)) {
                                $relevancy[] = (int) $score;
                            }
                        }
                    }
                    break;
            }
        }
    }

    private static function intOrNull(mixed $value): ?int
    {
        return (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }
}
