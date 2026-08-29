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
 * The three response shapes IMAP ever sends (RFC 3501 §2.2.2).
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
enum ImapResponseKind
{
    /** `<tag> <status> ...` completes exactly one outstanding command. */
    case Tagged;

    /** `* ...` data or a status update not tied to any one command. */
    case Untagged;

    /** `+ ...` a request for more data (a literal or a SASL challenge). */
    case Continuation;
}
