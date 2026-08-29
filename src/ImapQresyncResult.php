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
 * What a QRESYNC-parameterized `SELECT`/`EXAMINE` reported about changes
 * since the client's last known state (RFC 7162 §3.2.5).
 *
 * A QRESYNC open tells the server the mailbox's last-seen UIDVALIDITY and
 * HIGHESTMODSEQ; the server answers with the messages expunged since
 * (`VANISHED (EARLIER)`) and a FETCH for each message whose flags changed.
 * This bundles both so the caller can update its own view in one step
 * without a separate {@see ImapClient::vanished()} plus flag fetch.
 *
 * `$changed` is keyed by UID, each value the {@see ImapFetchResult} the
 * server volunteered (typically FLAGS + MODSEQ).
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final readonly class ImapQresyncResult
{
    /**
     * @param ImapIdSet                    $vanished UIDs expunged since the
     *                                               client's last sync.
     * @param array<int, ImapFetchResult>  $changed  Flag-change fetches,
     *                                               keyed by UID.
     */
    public function __construct(
        public ImapIdSet $vanished,
        public array $changed = [],
    ) {}
}
