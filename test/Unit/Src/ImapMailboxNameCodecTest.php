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

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\ImapModifiedUtf7Codec;
use Horde\Imap\Client\ImapUtf8MailboxNameCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapModifiedUtf7Codec::class)]
#[CoversClass(ImapUtf8MailboxNameCodec::class)]
class ImapMailboxNameCodecTest extends TestCase
{
    public function testAsciiNameRoundTripsUnchanged(): void
    {
        $codec = new ImapModifiedUtf7Codec();

        self::assertSame('INBOX.Sent', $codec->encode('INBOX.Sent'));
        self::assertSame('INBOX.Sent', $codec->decode('INBOX.Sent'));
    }

    public function testNonAsciiNameEncodesToModifiedUtf7(): void
    {
        $codec = new ImapModifiedUtf7Codec();

        // "Postfach" (German for "mailbox") with an umlaut, matching a
        // well-known modified UTF-7 test vector.
        $encoded = $codec->encode('Präsentation');

        self::assertSame('Pr&AOQ-sentation', $encoded);
        self::assertSame('Präsentation', $codec->decode($encoded));
    }

    public function testUtf8CodecIsANoOp(): void
    {
        $codec = new ImapUtf8MailboxNameCodec();

        self::assertSame('Präsentation', $codec->encode('Präsentation'));
        self::assertSame('Präsentation', $codec->decode('Präsentation'));
    }
}
