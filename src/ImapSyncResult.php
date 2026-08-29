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
 * What {@see ImapClient::sync()} found changed in a mailbox since the
 * point a sync token was taken.
 *
 * `$newMsgs` are UIDs that did not exist at token time (UID greater than
 * the token's UIDNEXT). `$flagChanges` are UIDs whose flags changed since
 * the token's HIGHESTMODSEQ (CONDSTORE, RFC 7162). `$vanished` are UIDs
 * expunged since (from QRESYNC, when available). Each is an
 * {@see ImapIdSet}, empty when the corresponding criterion was not
 * requested or nothing matched.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final readonly class ImapSyncResult
{
    public function __construct(
        public ImapIdSet $newMsgs,
        public ImapIdSet $flagChanges,
        public ImapIdSet $vanished,
    ) {}
}
