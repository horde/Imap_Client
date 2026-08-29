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

use Horde\Imap\Client\Exception\Pop3ProtocolException;
use Horde\Imap\Client\Pop3Connection;
use Horde\Imap\Client\Pop3ResponseKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pop3Connection::class)]
class Pop3ConnectionTest extends TestCase
{
    public function testSendLineAppendsCrlf(): void
    {
        $socket = new InMemoryPop3Socket();
        $connection = new Pop3Connection($socket);

        $connection->sendLine('NOOP');

        self::assertSame(['NOOP'], $socket->written);
    }

    public function testReadStatusLineParsesOk(): void
    {
        $socket = new InMemoryPop3Socket(['+OK 2 messages']);
        $connection = new Pop3Connection($socket);

        $line = $connection->readStatusLine();

        self::assertSame(Pop3ResponseKind::Ok, $line->kind);
        self::assertTrue($line->isOk());
        self::assertSame('2 messages', $line->text);
    }

    public function testReadStatusLineParsesOkWithNoText(): void
    {
        $socket = new InMemoryPop3Socket(['+OK']);
        $connection = new Pop3Connection($socket);

        $line = $connection->readStatusLine();

        self::assertTrue($line->isOk());
        self::assertSame('', $line->text);
    }

    public function testReadStatusLineParsesErrorWithoutThrowing(): void
    {
        $socket = new InMemoryPop3Socket(['-ERR invalid command']);
        $connection = new Pop3Connection($socket);

        $line = $connection->readStatusLine();

        self::assertTrue($line->isError());
        self::assertSame('invalid command', $line->text);
    }

    public function testReadStatusLineParsesContinuation(): void
    {
        $socket = new InMemoryPop3Socket(['+ PLBASE64CHALLENGE==']);
        $connection = new Pop3Connection($socket);

        $line = $connection->readStatusLine();

        self::assertTrue($line->isContinuation());
        self::assertSame('PLBASE64CHALLENGE==', $line->text);
    }

    public function testReadStatusLineRejectsUnknownIndicator(): void
    {
        $socket = new InMemoryPop3Socket(['GARBAGE']);
        $connection = new Pop3Connection($socket);

        $this->expectException(Pop3ProtocolException::class);

        $connection->readStatusLine();
    }

    public function testExpectOkReturnsOkLine(): void
    {
        $socket = new InMemoryPop3Socket(['+OK done']);
        $connection = new Pop3Connection($socket);

        $line = $connection->expectOk();

        self::assertSame('done', $line->text);
    }

    public function testExpectOkThrowsOnError(): void
    {
        $socket = new InMemoryPop3Socket(['-ERR permission denied']);
        $connection = new Pop3Connection($socket);

        $this->expectException(Pop3ProtocolException::class);
        $this->expectExceptionMessage('permission denied');

        $connection->expectOk();
    }

    public function testExpectOkThrowsWithDefaultMessageWhenNoText(): void
    {
        $socket = new InMemoryPop3Socket(['-ERR']);
        $connection = new Pop3Connection($socket);

        $this->expectException(Pop3ProtocolException::class);
        $this->expectExceptionMessage('POP3 error reported by server.');

        $connection->expectOk();
    }

    public function testReadMultilineYieldsLinesUntilTerminator(): void
    {
        $socket = new InMemoryPop3Socket(['first', 'second', '.']);
        $connection = new Pop3Connection($socket);

        self::assertSame(['first', 'second'], iterator_to_array($connection->readMultiline()));
    }

    public function testReadMultilineUnstuffsLeadingDot(): void
    {
        $socket = new InMemoryPop3Socket(['..leading dot line', 'plain', '.']);
        $connection = new Pop3Connection($socket);

        self::assertSame(['.leading dot line', 'plain'], iterator_to_array($connection->readMultiline()));
    }

    public function testReadMultilineHandlesEmptyBlock(): void
    {
        $socket = new InMemoryPop3Socket(['.']);
        $connection = new Pop3Connection($socket);

        self::assertSame([], iterator_to_array($connection->readMultiline()));
    }
}
