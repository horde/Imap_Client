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
 * IMAP ACL rights (RFC 4314 section 2.1).
 *
 * Deprecated RFC 2086 rights 'c' and 'd' are deliberately omitted.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
enum AclRight: string
{
    case Lookup = 'l';
    case Read = 'r';
    case Seen = 's';
    case Write = 'w';
    case Insert = 'i';
    case Post = 'p';
    case CreateMbox = 'k';
    case DeleteMbox = 'x';
    case DeleteMsgs = 't';
    case Expunge = 'e';
    case Administer = 'a';
}
