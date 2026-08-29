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

use Horde\Mail\Rfc822\AddressList;

/**
 * A parsed IMAP `ENVELOPE`.
 *
 * See RFC 3501 §7.4.2
 *
 * The wire sends ten positional fields in this exact order: Date,
 * Subject, From, Sender, Reply-To, To, Cc, Bcc, In-Reply-To, Message-ID.
 * Per RFC 3501 §7.4.2 the address groups a message header may legitimately omit
 * (`sender`, `reply-to`) default to the same value
 * as `from` when the server sends `NIL` for them. {@see ImapFetchParser}
 *
 * Addresses reuse the modern {@see \Horde\Mail\Rfc822\Address}/`Group`/
 * `AddressList` value objects rather than a bespoke IMAP address type.
 * `horde/mail` is already a dependency and its address model is exactly
 * the envelope's (personal name, mailbox, host, plus RFC 3501 group
 * start/end markers).
 *
 * `date` is kept as the raw header string the server returned instead of a
 * parsed `DateTimeImmutable`: The envelope date is whatever was in the
 * `Date:` header. This is frequently malformed in the wild and callers
 * who need a real date should parse it themselves (or use
 * `INTERNALDATE` via {@see MessageMetadata::getImapDate()} which is server-authoritative).
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final readonly class ImapEnvelope
{
    public function __construct(
        public string $date = '',
        public string $subject = '',
        public AddressList $from = new AddressList(),
        public AddressList $sender = new AddressList(),
        public AddressList $replyTo = new AddressList(),
        public AddressList $to = new AddressList(),
        public AddressList $cc = new AddressList(),
        public AddressList $bcc = new AddressList(),
        public string $inReplyTo = '',
        public string $messageId = '',
    ) {}
}
