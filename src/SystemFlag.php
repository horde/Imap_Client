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
 * IMAP system flags and well-known keywords.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
enum SystemFlag: string
{
    /** RFC 3501 section 2.3.2 */
    case Answered = '\\answered';
    case Deleted = '\\deleted';
    case Draft = '\\draft';
    case Flagged = '\\flagged';
    case Recent = '\\recent';
    case Seen = '\\seen';

    /** RFC 3503 section 3.3 */
    case MdnSent = '$mdnsent';

    /** RFC 5550 section 2.8 */
    case Forwarded = '$forwarded';

    /** RFC 5788 registered keywords */
    case Junk = '$junk';
    case NotJunk = '$notjunk';
}
