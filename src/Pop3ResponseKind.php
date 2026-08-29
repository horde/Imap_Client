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
 * The three status-line shapes POP3 ever sends (RFC 1939 §3, RFC 5034).
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
enum Pop3ResponseKind
{
    /** `+OK <text>`: a successful, final result. */
    case Ok;

    /** `-ERR <text>`: a failed, final result. */
    case Error;

    /** `+ <text>`: a SASL continuation challenge (RFC 5034). */
    case Continuation;
}
