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
 * The result of a {@see ImapClient::search()}.
 *
 * Modeled on the ESEARCH response shape (RFC 4731) first, with classic
 * `SEARCH` (RFC 3501 §7.2.5) as its degenerate case: an ESEARCH reply can
 * carry `COUNT`, `MIN`, `MAX`, `ALL` (the matching set) and `RELEVANCY`
 * independently, so this holds each as its own slot. A plain `SEARCH`
 * reply only ever produces the matching set, from which `count`/`min`/
 * `max` are derived; the parser fills those in so a caller sees a uniform
 * result whichever wire form the server used.
 *
 * `$match` is always present (an empty {@see ImapIdSet} when nothing
 * matched). `$count`/`$min`/`$max`/`$relevancy` are null when the server
 * did not report them and they were not derivable.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final readonly class ImapSearchResult
{
    /**
     * @param ImapIdSet    $match     The matching messages (UIDs or
     *                                sequence numbers, per the search
     *                                mode). Empty when nothing matched.
     * @param ?int         $count     Number of matches (ESEARCH `COUNT`),
     *                                or derived from `$match`.
     * @param ?int         $min       Lowest matching id (ESEARCH `MIN`).
     * @param ?int         $max       Highest matching id (ESEARCH `MAX`).
     * @param ?bool        $saved     Whether the server saved the result
     *                                to the search-result variable (`$`,
     *                                RFC 5182). Null when not requested.
     * @param list<int>    $relevancy Per-match relevancy scores
     *                                (RFC 6203), empty when not reported.
     * @param ?int         $modseq    Highest MODSEQ among matches when the
     *                                server reported one (RFC 7162).
     */
    public function __construct(
        public ImapIdSet $match,
        public ?int $count = null,
        public ?int $min = null,
        public ?int $max = null,
        public ?bool $saved = null,
        public array $relevancy = [],
        public ?int $modseq = null,
    ) {}
}
