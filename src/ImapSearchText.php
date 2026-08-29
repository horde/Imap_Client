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
 * A search text value held as raw UTF-8 until {@see ImapSearchQuery::build()}
 * resolves it into an {@see ImapWireString} in the search's chosen charset.
 *
 * Deferring the encoding is what lets a search be retried in a different
 * charset after a `NO [BADCHARSET (...)]` rejection (RFC 3501 §6.4.4): the
 * query keeps the original UTF-8 text and re-encodes it into a
 * server-accepted charset on the retry, rather than having baked the bytes
 * in at add() time.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final readonly class ImapSearchText
{
    public function __construct(public string $utf8Value) {}

    /**
     * Materialize as an {@see ImapWireString} in `$charset`. UTF-8 (and
     * the null "no charset chosen" case, which only happens for pure-ASCII
     * text) passes through unconverted; any other charset re-encodes from
     * UTF-8 via ext-mbstring (already a hard dependency of this library).
     */
    public function encode(?string $charset): ImapWireString
    {
        $value = $this->utf8Value;

        if ($charset !== null && strtoupper($charset) !== 'UTF-8' && $value !== '') {
            $value = mb_convert_encoding($value, $charset, 'UTF-8');
        }

        return new ImapWireString($value, isAstring: true);
    }
}
