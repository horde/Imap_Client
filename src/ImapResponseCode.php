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
 * A response text code (RFC 3501 §7.1): The bracketed part of a status
 * response, such as `[ALERT]`, `[UIDVALIDITY 1234567]`, or
 * `[PERMANENTFLAGS (\Deleted \Seen \*)]`.
 *
 * This only captures the generic shape. A name plus whatever tokens
 * followed it inside the brackets.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapResponseCode
{
    /**
     * @param list<mixed> $data Tokens that followed the code name inside
     *                          the brackets (strings, or nested arrays
     *                          for a parenthesized list such as
     *                          `PERMANENTFLAGS`).
     */
    public function __construct(
        public readonly string $name,
        public readonly array $data = [],
    ) {}
}
