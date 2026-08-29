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
 * The result of scanning a candidate IMAP string for how it must be sent
 * (RFC 3501 §4.3).
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapStringClassification
{
    public function __construct(
        /** Must be quoted (contains a space, paren, or other atom-special). */
        public readonly bool $quoted,
        /** Must be sent as a literal (contains CR, LF, or a null byte). */
        public readonly bool $literal,
        /** Contains a null byte, so a literal8 (RFC 3516) is required. */
        public readonly bool $binary,
        /** Contains an octet outside printable US-ASCII. */
        public readonly bool $nonAscii,
    ) {}
}
