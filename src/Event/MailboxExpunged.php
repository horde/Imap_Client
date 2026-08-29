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

use Horde\Imap\Client\ImapIdSet;

/**
 * Dispatched after messages are removed from a mailbox: A plain EXPUNGE, a
 * UID EXPUNGE or the source-side removal of a MOVE.
 *
 * An external cache (for example a search-result cache the library
 * deliberately does not own) can listen for this to invalidate entries
 * that reference the removed messages. Whether the removed set is
 * expressed as UIDs or as sequence numbers depends on what the server
 * reported: VANISHED (QRESYNC) and UID EXPUNGE carry UIDs; a plain EXPUNGE
 * carries only sequence numbers. Inspect {@see ImapIdSet::isSequence()} on
 * {@see $vanished}: when it is UIDs a listener can intersect precisely;
 * when it is sequence numbers (or empty) a listener must invalidate the
 * mailbox conservatively, since sequence numbers are not stable keys.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class MailboxExpunged extends ImapEvent
{
    /**
     * @param string     $mailbox     The mailbox messages were removed from.
     * @param ImapIdSet  $vanished    The removed messages (UIDs or sequence
     *                                numbers, per {@see ImapIdSet::isSequence()};
     *                                may be empty when the server reported
     *                                nothing enumerable).
     * @param int        $uidvalidity The mailbox UIDVALIDITY in effect, or 0
     *                                if unknown, so a listener can scope
     *                                cache keys to the right UID space.
     */
    public function __construct(
        public readonly string $mailbox,
        public readonly ImapIdSet $vanished,
        public readonly int $uidvalidity = 0,
    ) {
        parent::__construct(
            sprintf(
                '%d message(s) removed from %s',
                $vanished->count(),
                $mailbox,
            ),
            [
                'mailbox' => $mailbox,
                'ids' => $vanished->toArray(),
                'sequence' => $vanished->isSequence(),
                'uidvalidity' => $uidvalidity,
            ],
        );
    }
}
