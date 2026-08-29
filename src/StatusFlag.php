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

namespace Horde\Imap\Client;

/**
 * Bitmask flags for `MailboxProtocol::status()`.
 *
 * Values match the legacy `Horde_Imap_Client::STATUS_*` constants so
 * callers migrating from `lib/` don't need to remap bits. IMAP-only
 * members are added at their legacy bit values as `ImapClient` grows to
 * need them (`HighestModSeq` for CONDSTORE, RFC 7162).
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
enum StatusFlag: int
{
    case Messages = 1;
    case Recent = 2;
    case UidNext = 4;
    case UidValidity = 8;
    case Unseen = 16;
    case All = 32;
    /** RFC 7162 (CONDSTORE). Requested only when the server advertises it. */
    case HighestModSeq = 512;
}
