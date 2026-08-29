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

use Horde\Imap\Client\Exception\WireEncodingException;

/**
 * A value that knows how to represent itself on the IMAP command wire
 * (RFC 3501 §4).
 *
 * `escape()` covers everything that can be sent inline: atoms, quoted
 * strings, `NIL`, and parenthesized lists whose members are all
 * themselves inline. A value that needs a literal cannot produce a
 * single inline string. The `{n}` length has to be announced, the
 * server has to answer with a `+` continuation, and only then can the
 * raw bytes go out. Building that exchange is command assembly's job
 * (the interaction/pipelining layer), not this value's job. This
 * interface only exposes the primitives (`isLiteral()`, `isBinary()`,
 * `length()`, `rawBytes()`) that assembly needs to drive it.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface ImapWireEncodable
{
    /**
     * Must this value be sent as a literal instead of inline?
     */
    public function isLiteral(): bool;

    /**
     * If `isLiteral()`, must it be announced as a literal8 (`~{n}`,
     * RFC 3516) rather than an ordinary literal (`{n}`)?
     */
    public function isBinary(): bool;

    /**
     * Byte length of the raw value, for the `{n}` announcement.
     */
    public function length(): int;

    /**
     * The inline wire representation.
     *
     * @throws WireEncodingException If this value (or, for a list, one
     *                                of its members) requires a literal.
     */
    public function escape(): string;

    /**
     * The raw bytes to send after a literal's `+` continuation.
     *
     * Meaningless (and not guaranteed to mean anything) unless
     * `isLiteral()` is true.
     */
    public function rawBytes(): string;
}
