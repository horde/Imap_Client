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
 * A "special" IMAP message set that stands in for an explicit ID list.
 *
 * These replace the H5 `Horde_Imap_Client_Ids` magic-string constants
 * (`"\01"`, `"\02"`, `"\03"`) with a typed enum. Each case maps to the wire
 * token IMAP uses when a command's message set is one of these forms:
 *
 * - {@see self::All}    : every message in the mailbox (`1:*`).
 * - {@see self::Largest}: the single largest UID/sequence number (`*`).
 * - {@see self::SearchRes}: the server-side saved search result (`$`,
 *                            RFC 5182), reused from the previous `SEARCH`.
 *
 * An {@see ImapIdSet} is either one of these tokens or an explicit list of
 * integers, never both.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
enum ImapIdSetToken: string
{
    case All = '1:*';
    case Largest = '*';
    case SearchRes = '$';

    /**
     * The IMAP wire representation of this special set.
     */
    public function toWire(): string
    {
        return $this->value;
    }
}
