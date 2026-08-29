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
 * Converts mailbox names between UTF-8 and whatever encoding the wire actually needs.
 * This library always represents them to callers in UTF-8.
 *
 * IMAP4rev1 (RFC 3501 §5.1.3) mandates modified UTF-7 for mailbox names.
 * IMAP4rev2 (RFC 9051 §5.1) and `ENABLE UTF8=ACCEPT` (RFC 6855) under
 * rev1 both switch this to plain UTF-8. Which codec applies is a
 * capability-negotiation outcome. Callers select the
 * implementation instead of this being decided here.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface ImapMailboxNameCodec
{
    /**
     * Encode a UTF-8 mailbox name for the wire.
     */
    public function encode(string $utf8Name): string;

    /**
     * Decode a wire mailbox name back to UTF-8.
     */
    public function decode(string $wireName): string;
}
