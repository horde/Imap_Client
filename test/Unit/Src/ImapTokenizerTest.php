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

use Horde\Imap\Client\Exception\ImapProtocolException;
use Horde\Imap\Client\ImapTokenizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapTokenizer::class)]
class ImapTokenizerTest extends TestCase
{
    public function testSimpleUntaggedResponse(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* CAPABILITY IMAP4rev1 SASL-IR AUTH=PLAIN'),
        );

        $tokens = (new ImapTokenizer($socket))->readLine();

        self::assertSame(
            ['*', 'CAPABILITY', 'IMAP4rev1', 'SASL-IR', 'AUTH=PLAIN'],
            $tokens,
        );
    }

    public function testTaggedOkCompletion(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('A001 OK LOGIN completed'),
        );

        $tokens = (new ImapTokenizer($socket))->readLine();

        self::assertSame(['A001', 'OK', 'LOGIN', 'completed'], $tokens);
    }

    public function testParenthesizedList(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* FLAGS (\\Answered \\Flagged \\Deleted \\Seen \\Draft)'),
        );

        $tokens = (new ImapTokenizer($socket))->readLine();

        self::assertSame(
            ['*', 'FLAGS', ['\\Answered', '\\Flagged', '\\Deleted', '\\Seen', '\\Draft']],
            $tokens,
        );
    }

    public function testNestedParenthesizedLists(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* SEARCH'),
        );
        $tokenizer = new ImapTokenizer($socket);
        self::assertSame(['*', 'SEARCH'], $tokenizer->readLine());

        $socket2 = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* LIST (\\HasNoChildren (nested)) "/" "INBOX"'),
        );
        $tokens = (new ImapTokenizer($socket2))->readLine();

        self::assertSame(
            ['*', 'LIST', ['\\HasNoChildren', ['nested']], '/', 'INBOX'],
            $tokens,
        );
    }

    public function testQuotedStringWithEscapes(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* OK [ALERT] "System going down for \\"maintenance\\" soon"'),
        );

        $tokens = (new ImapTokenizer($socket))->readLine();

        self::assertSame(
            ['*', 'OK', '[ALERT]', 'System going down for "maintenance" soon'],
            $tokens,
        );
    }

    public function testNilBecomesNull(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* 12 FETCH (INTERNALDATE NIL)'),
        );

        $tokens = (new ImapTokenizer($socket))->readLine();

        self::assertSame(['*', '12', 'FETCH', ['INTERNALDATE', null]], $tokens);
    }

    public function testLiteralPayloadResolvesAndContinuesOnNextLine(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* 1 FETCH (BODY[] {15}'),
            "Hello, world!\r\n)\r\n",
        );

        $tokens = (new ImapTokenizer($socket))->readLine();

        self::assertSame(
            ['*', '1', 'FETCH', ['BODY[]', "Hello, world!\r\n"]],
            $tokens,
        );
    }

    public function testLiteralFollowedByMoreTokensOnSameLogicalResponse(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* 12 FETCH (BODY[] {5}'),
            "hello FLAGS (\\Seen))\r\n",
        );

        $tokens = (new ImapTokenizer($socket))->readLine();

        self::assertSame(
            ['*', '12', 'FETCH', ['BODY[]', 'hello', 'FLAGS', ['\\Seen']]],
            $tokens,
        );
    }

    public function testBinaryLiteral8IsResolvedLikeAnOrdinaryLiteral(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* 1 FETCH (BINARY[] ~{4}'),
            "\x00\x01\x02\x03)",
            "\r\n",
        );

        $tokens = (new ImapTokenizer($socket))->readLine();

        self::assertSame(
            ['*', '1', 'FETCH', ['BINARY[]', "\x00\x01\x02\x03"]],
            $tokens,
        );
    }

    public function testEmptyLiteral(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* 1 FETCH (BODY[] {0}'),
            ")\r\n",
        );

        $tokens = (new ImapTokenizer($socket))->readLine();

        self::assertSame(['*', '1', 'FETCH', ['BODY[]', '']], $tokens);
    }

    public function testConnectionClosedMidResponseThrows(): void
    {
        $socket = InMemoryImapSocket::fromParts('');

        $this->expectException(ImapProtocolException::class);

        (new ImapTokenizer($socket))->readLine();
    }

    public function testAtomWithBracketedSectionSpecifier(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* 3 FETCH (BODY[HEADER.FIELDS (SUBJECT)] {2}'),
            "Hi)",
            "\r\n",
        );

        $tokens = (new ImapTokenizer($socket))->readLine();

        self::assertSame(
            ['*', '3', 'FETCH', ['BODY[HEADER.FIELDS', ['SUBJECT'], ']', 'Hi']],
            $tokens,
        );
    }
}
