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
 * The three namespace classes a NAMESPACE response reports (RFC 2342 §5).
 *
 * The response is a fixed triple of parenthesized lists in this order:
 * personal namespaces, other users namespaces, shared namespaces. This
 * enum names the position each list occupies rather than the legacy
 * integer constants (`NS_PERSONAL` = 1 and so on).
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
enum NamespaceType: int
{
    case Personal = 1;
    case Other = 2;
    case Shared = 3;
}
