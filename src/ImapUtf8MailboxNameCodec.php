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
 * A no-op mailbox name codec for IMAP4rev2 and `ENABLE UTF8=ACCEPT` (RFC
 * 6855) connections where mailbox names travel as plain UTF-8 already.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapUtf8MailboxNameCodec implements ImapMailboxNameCodec
{
    public function encode(string $utf8Name): string
    {
        return $utf8Name;
    }

    public function decode(string $wireName): string
    {
        return $wireName;
    }
}
