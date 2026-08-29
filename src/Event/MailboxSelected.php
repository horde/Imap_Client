<?php

declare(strict_types=1);

/**
 * Copyright 2008-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Event;

/**
 * Dispatched when a mailbox is opened (SELECT/EXAMINE).
 *
 * Carries the sync-relevant state the open reply advertised so an external
 * cache can detect a UID-space change (a new UIDVALIDITY means every cached
 * result for the mailbox is stale) or gauge freshness against
 * HIGHESTMODSEQ. A value of 0 means the server did not report that code.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class MailboxSelected extends ImapEvent
{
    /**
     * @param string $mailbox       The opened mailbox.
     * @param int    $uidvalidity   UIDVALIDITY (RFC 3501 §2.3.1.1), or 0.
     * @param int    $uidnext       UIDNEXT (RFC 3501 §2.3.1.1), or 0.
     * @param int    $highestmodseq HIGHESTMODSEQ (RFC 7162), or 0.
     */
    public function __construct(
        public readonly string $mailbox,
        public readonly int $uidvalidity = 0,
        public readonly int $uidnext = 0,
        public readonly int $highestmodseq = 0,
    ) {
        parent::__construct(
            sprintf('Mailbox %s opened', $mailbox),
            [
                'mailbox' => $mailbox,
                'uidvalidity' => $uidvalidity,
                'uidnext' => $uidnext,
                'highestmodseq' => $highestmodseq,
            ],
        );
    }
}
