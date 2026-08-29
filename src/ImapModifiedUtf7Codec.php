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
 * Modified UTF-7 mailbox name encoding (RFC 3501 §5.1.3)
 * This is the only encoding an IMAP4rev1 server without `UTF8=ACCEPT` will understand.
 *
 * Delegates to `ext-mbstring`'s built-in `UTF7-IMAP` encoding,
 * already a required dependency of this package. This avoids carrying a
 * hand-rolled bit-shuffling implementation for an encoding PHP already
 * speaks natively.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapModifiedUtf7Codec implements ImapMailboxNameCodec
{
    public function encode(string $utf8Name): string
    {
        return mb_convert_encoding($utf8Name, 'UTF7-IMAP', 'UTF-8');
    }

    public function decode(string $wireName): string
    {
        return mb_convert_encoding($wireName, 'UTF-8', 'UTF7-IMAP');
    }
}
