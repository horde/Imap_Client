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

use Horde\Imap\Client\Exception\WireEncodingException;
use Horde\Imap\Client\ImapWireAtom;
use Horde\Imap\Client\ImapWireList;
use Horde\Imap\Client\ImapWireMailbox;
use Horde\Imap\Client\ImapWireNil;
use Horde\Imap\Client\ImapWireNstring;
use Horde\Imap\Client\ImapWireNumber;
use Horde\Imap\Client\ImapWireString;
use Horde\Imap\Client\ImapUtf8MailboxNameCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapWireAtom::class)]
#[CoversClass(ImapWireNumber::class)]
#[CoversClass(ImapWireNil::class)]
#[CoversClass(ImapWireString::class)]
#[CoversClass(ImapWireNstring::class)]
#[CoversClass(ImapWireMailbox::class)]
#[CoversClass(ImapWireList::class)]
class ImapWireValueTest extends TestCase
{
    public function testAtomEscapesPlainText(): void
    {
        self::assertSame('LOGIN', (new ImapWireAtom('LOGIN'))->escape());
    }

    public function testEmptyAtomEscapesAsEmptyQuotedString(): void
    {
        self::assertSame('""', (new ImapWireAtom(''))->escape());
    }

    public function testAtomRejectsIllegalCharacters(): void
    {
        $this->expectException(WireEncodingException::class);

        (new ImapWireAtom('has space'))->validate();
    }

    public function testAtomEscapeAllowsLeadingBackslashForFlags(): void
    {
        self::assertSame('\\Seen', (new ImapWireAtom('\\Seen'))->escape());
    }

    public function testNumberEscapesAsDecimal(): void
    {
        self::assertSame('42', (new ImapWireNumber(42))->escape());
    }

    public function testNumberRejectsNegativeValues(): void
    {
        $this->expectException(WireEncodingException::class);

        new ImapWireNumber(-1);
    }

    public function testNilEscapesToNilKeyword(): void
    {
        self::assertSame('NIL', (new ImapWireNil())->escape());
    }

    public function testPlainStringEscapesUnquotedWhenSafe(): void
    {
        self::assertSame('hello', (new ImapWireString('hello'))->escape());
    }

    public function testStringWithSpaceIsQuoted(): void
    {
        self::assertSame('"hello world"', (new ImapWireString('hello world'))->escape());
    }

    public function testStringQuotingEscapesBackslashAndQuote(): void
    {
        self::assertSame('"say \\"hi\\" \\\\ok"', (new ImapWireString('say "hi" \\ok'))->escape());
    }

    public function testAstringQuotesEmptyStringButPlainStringDoesNot(): void
    {
        self::assertSame('""', (new ImapWireString('', isAstring: true))->escape());
        self::assertSame('', (new ImapWireString('', isAstring: false))->escape());
    }

    public function testStringWithEmbeddedNewlineRequiresLiteral(): void
    {
        $string = new ImapWireString("line1\nline2");

        self::assertTrue($string->isLiteral());
        self::assertFalse($string->isBinary());
        self::assertSame("line1\nline2", $string->rawBytes());
    }

    public function testEscapeOnLiteralRequiredStringThrows(): void
    {
        $string = new ImapWireString("line1\nline2");

        $this->expectException(WireEncodingException::class);

        $string->escape();
    }

    public function testStringWithNullByteIsBinaryLiteral(): void
    {
        $string = new ImapWireString("has\x00null");

        self::assertTrue($string->isLiteral());
        self::assertTrue($string->isBinary());
    }

    public function testWildcardsAreQuotedByDefaultButNotForListPatterns(): void
    {
        self::assertSame('"INBOX.*"', (new ImapWireString('INBOX.*'))->escape());
        self::assertSame('INBOX.*', (new ImapWireString('INBOX.*', allowWildcards: true))->escape());
    }

    public function testNstringEscapesNullAsNil(): void
    {
        self::assertSame('NIL', (new ImapWireNstring(null))->escape());
    }

    public function testNstringEscapesStringNormally(): void
    {
        self::assertSame('"hi there"', (new ImapWireNstring('hi there'))->escape());
    }

    public function testMailboxEncodesThroughCodec(): void
    {
        $mailbox = new ImapWireMailbox('Sent', new ImapUtf8MailboxNameCodec());

        self::assertSame('Sent', $mailbox->escape());
    }

    public function testMailboxWithSpaceIsQuoted(): void
    {
        $mailbox = new ImapWireMailbox('My Folder', new ImapUtf8MailboxNameCodec());

        self::assertSame('"My Folder"', $mailbox->escape());
    }

    public function testMailboxRejectsNullByte(): void
    {
        $this->expectException(WireEncodingException::class);

        new ImapWireMailbox("has\x00null", new ImapUtf8MailboxNameCodec());
    }

    public function testListEscapesAtomMembers(): void
    {
        $list = new ImapWireList(['\\Deleted', '\\Seen']);

        self::assertSame('\\Deleted \\Seen', $list->escape());
        self::assertCount(2, $list);
    }

    public function testNestedListEscapesWithParens(): void
    {
        $list = new ImapWireList([
            new ImapWireAtom('UID'),
            new ImapWireList([new ImapWireAtom('FLAGS'), new ImapWireAtom('\\Seen')]),
        ]);

        self::assertSame('UID (FLAGS \\Seen)', $list->escape());
    }

    public function testListEscapeThrowsWhenAMemberRequiresLiteral(): void
    {
        $list = new ImapWireList([new ImapWireString("has\nnewline")]);

        $this->expectException(WireEncodingException::class);

        $list->escape();
    }

    public function testListRawBytesAlwaysThrows(): void
    {
        $this->expectException(WireEncodingException::class);

        (new ImapWireList())->rawBytes();
    }
}
