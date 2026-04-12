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
 * Sort criteria for search() results.
 *
 * Values match Horde_Imap_Client::SORT_* constants for migration.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2008-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
enum SortCriteria: int
{
    case Arrival = 1;
    case Cc = 2;
    case Date = 3;
    case From = 4;
    case Reverse = 5;
    case Size = 6;
    case Subject = 7;
    case To = 8;
    case Thread = 9;
    /** RFC 5957 */
    case DisplayFrom = 10;
    /** RFC 5957 */
    case DisplayTo = 11;
    case Sequence = 12;
    /** RFC 6203 */
    case Relevancy = 13;
    case DisplayFromFallback = 14;
    case DisplayToFallback = 15;
}
