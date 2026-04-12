<?php

declare(strict_types=1);

/**
 * Copyright 2008-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2008-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

/**
 * Special-use mailbox attributes (RFC 6154 section 2).
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2008-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
enum SpecialUse: string
{
    case All = '\\All';
    case Archive = '\\Archive';
    case Drafts = '\\Drafts';
    case Flagged = '\\Flagged';
    case Junk = '\\Junk';
    case Sent = '\\Sent';
    case Trash = '\\Trash';
}
