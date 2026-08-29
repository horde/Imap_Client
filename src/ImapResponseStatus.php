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
 * The five status words IMAP defines for a `resp-cond-state` /
 * `resp-cond-bye` / `resp-cond-auth` response (RFC 3501 §7.1).
 *
 * `Bye` and `PreAuth` only ever appear on untagged responses. A tagged
 * response is always `Ok`, `No`, or `Bad`. {@see ImapResponseParser}
 * does not enforce that distinction itself.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
enum ImapResponseStatus
{
    /** The preceding command (or the connection as a whole) succeeded. */
    case Ok;

    /** A warning or a failure not directly tied to any one command. */
    case No;

    /** The tagged command was rejected, or the server reports a fatal error. */
    case Bad;

    /** The server is closing the connection. */
    case Bye;

    /** The connection arrived already authenticated (pre-authenticated). */
    case PreAuth;

    /**
     * The wire word this status was parsed from (`OK`, `NO`, ...).
     */
    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::No => 'NO',
            self::Bad => 'BAD',
            self::Bye => 'BYE',
            self::PreAuth => 'PREAUTH',
        };
    }
}
