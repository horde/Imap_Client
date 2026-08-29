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
use Horde\Imap\Client\ImapResponseKind;
use Horde\Imap\Client\ImapResponseParser;
use Horde\Imap\Client\ImapResponseStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapResponseParser::class)]
class ImapResponseParserTest extends TestCase
{
    public function testParsesTaggedOk(): void
    {
        $response = ImapResponseParser::parse(['A1', 'OK', 'LOGIN', 'completed.']);

        self::assertTrue($response->isTagged());
        self::assertSame('A1', $response->tag);
        self::assertTrue($response->isOk());
        self::assertSame('LOGIN completed.', $response->text);
        self::assertNull($response->responseCode);
    }

    public function testParsesTaggedNo(): void
    {
        $response = ImapResponseParser::parse(['A2', 'NO', 'Mailbox', 'does', 'not', 'exist.']);

        self::assertTrue($response->isNo());
        self::assertFalse($response->isOk());
        self::assertSame('Mailbox does not exist.', $response->text);
    }

    public function testParsesTaggedBad(): void
    {
        $response = ImapResponseParser::parse(['A3', 'BAD', 'Command', 'unrecognized.']);

        self::assertTrue($response->isBad());
    }

    public function testTaggedWithoutStatusThrows(): void
    {
        $this->expectException(ImapProtocolException::class);

        ImapResponseParser::parse(['A4', 'garbage']);
    }

    public function testEmptyResponseThrows(): void
    {
        $this->expectException(ImapProtocolException::class);

        ImapResponseParser::parse([]);
    }

    public function testParsesContinuation(): void
    {
        $response = ImapResponseParser::parse(['+', 'PLBASE64CHALLENGE==']);

        self::assertTrue($response->isContinuation());
        self::assertSame('PLBASE64CHALLENGE==', $response->text);
    }

    public function testParsesBareContinuation(): void
    {
        $response = ImapResponseParser::parse(['+']);

        self::assertTrue($response->isContinuation());
        self::assertSame('', $response->text);
    }

    public function testParsesUntaggedStatus(): void
    {
        $response = ImapResponseParser::parse(['*', 'OK', 'IMAP4rev2', 'Server', 'ready.']);

        self::assertTrue($response->isUntagged());
        self::assertTrue($response->isOk());
        self::assertSame('IMAP4rev2 Server ready.', $response->text);
    }

    public function testParsesUntaggedBye(): void
    {
        $response = ImapResponseParser::parse(['*', 'BYE', 'Logging', 'out.']);

        self::assertTrue($response->isBye());
    }

    public function testParsesUntaggedPreAuth(): void
    {
        $response = ImapResponseParser::parse(['*', 'PREAUTH', 'Already', 'authenticated.']);

        self::assertSame(ImapResponseStatus::PreAuth, $response->status);
    }

    public function testParsesUntaggedDataWithoutStatus(): void
    {
        $response = ImapResponseParser::parse(['*', '1', 'EXISTS']);

        self::assertTrue($response->isUntagged());
        self::assertNull($response->status);
        self::assertSame(['1', 'EXISTS'], $response->data);
        self::assertSame('1 EXISTS', $response->text);
    }

    public function testParsesUntaggedDataWithNestedList(): void
    {
        $response = ImapResponseParser::parse(['*', 'FLAGS', ['\\Seen', '\\Deleted']]);

        self::assertSame(['FLAGS', ['\\Seen', '\\Deleted']], $response->data);
        self::assertSame('FLAGS (\\Seen \\Deleted)', $response->text);
    }

    public function testParsesBracketedResponseCodeInOneToken(): void
    {
        $response = ImapResponseParser::parse(['A5', 'OK', '[READ-WRITE]', 'SELECT', 'completed.']);

        self::assertNotNull($response->responseCode);
        self::assertSame('READ-WRITE', $response->responseCode->name);
        self::assertSame([], $response->responseCode->data);
        self::assertSame('SELECT completed.', $response->text);
    }

    public function testParsesBracketedResponseCodeSpanningTokens(): void
    {
        $response = ImapResponseParser::parse(['A6', 'OK', '[UIDVALIDITY', '3857529045]', 'UIDs', 'valid.']);

        self::assertSame('UIDVALIDITY', $response->responseCode->name);
        self::assertSame(['3857529045'], $response->responseCode->data);
        self::assertSame('UIDs valid.', $response->text);
    }

    public function testParsesBracketedResponseCodeWithNestedList(): void
    {
        $response = ImapResponseParser::parse([
            'A7',
            'OK',
            '[PERMANENTFLAGS',
            ['\\Deleted', '\\Seen', '\\*'],
            ']',
        ]);

        self::assertSame('PERMANENTFLAGS', $response->responseCode->name);
        self::assertSame([['\\Deleted', '\\Seen', '\\*']], $response->responseCode->data);
        self::assertSame([], $response->data);
    }

    public function testUntaggedStatusWithoutResponseCode(): void
    {
        $response = ImapResponseParser::parse(['*', 'NO', 'Disk', 'quota', 'exceeded.']);

        self::assertNull($response->responseCode);
        self::assertSame('Disk quota exceeded.', $response->text);
    }

    public function testResponseKindEnumValues(): void
    {
        self::assertNotSame(ImapResponseKind::Tagged, ImapResponseKind::Untagged);
        self::assertNotSame(ImapResponseKind::Untagged, ImapResponseKind::Continuation);
    }
}
