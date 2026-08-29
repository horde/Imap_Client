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

use Horde\Imap\Client\Exception\ServerResponseException;
use Horde\Imap\Client\ImapCommand;
use Horde\Imap\Client\ImapConnection;
use Horde\Imap\Client\ImapWireString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapConnection::class)]
class ImapConnectionTest extends TestCase
{
    public function testSendCommandWithoutLiteralWritesOneLine(): void
    {
        $socket = InMemoryImapSocket::fromParts('');
        $connection = new ImapConnection($socket);

        $untagged = $connection->sendCommand(new ImapCommand('A1', 'SELECT', ['INBOX']));

        self::assertSame([], $untagged);
        self::assertSame(["A1 SELECT INBOX"], $socket->written);
    }

    public function testSendCommandWithLiteralWaitsForContinuation(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('+ Ready for literal data'),
        );
        $connection = new ImapConnection($socket);

        $command = new ImapCommand('A2', 'LOGIN', [
            new ImapWireString('admin'),
            new ImapWireString("multi\r\nline"),
        ]);
        $untagged = $connection->sendCommand($command);

        self::assertSame([], $untagged);
        self::assertSame(
            ["A2 LOGIN admin {11}", "multi\r\nline", ''],
            $socket->written,
        );
    }

    public function testSendCommandCollectsUntaggedResponsesWhileAwaitingContinuation(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* OK [ALERT] System going down soon.'),
            InMemoryImapSocket::line('+ Ready'),
        );
        $connection = new ImapConnection($socket);

        $command = new ImapCommand('A3', 'LOGIN', [
            new ImapWireString('admin'),
            new ImapWireString("multi\r\nline"),
        ]);
        $untagged = $connection->sendCommand($command);

        self::assertCount(1, $untagged);
        self::assertTrue($untagged[0]->isUntagged());
        self::assertSame('ALERT', $untagged[0]->responseCode->name);
    }

    public function testSendCommandThrowsWhenLiteralIsRejected(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('A4 NO literal too large'),
        );
        $connection = new ImapConnection($socket);

        $command = new ImapCommand('A4', 'LOGIN', [
            new ImapWireString('admin'),
            new ImapWireString("multi\r\nline"),
        ]);

        $this->expectException(ServerResponseException::class);

        $connection->sendCommand($command);
    }

    public function testSendCommandSendsNonSynchronizingLiteralWithoutWaiting(): void
    {
        $socket = InMemoryImapSocket::fromParts('');
        $connection = new ImapConnection($socket);

        $command = new ImapCommand('A5', 'LOGIN', [
            new ImapWireString('admin'),
            new ImapWireString("multi\r\nline"),
        ]);
        $untagged = $connection->sendCommand($command, nonSynchronizingLiterals: true);

        self::assertSame([], $untagged);
        self::assertSame(
            ["A5 LOGIN admin {11+}", "multi\r\nline", ''],
            $socket->written,
        );
    }

    public function testSendCommandAnnouncesBinaryLiteralWithTilde(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('+ Ready'),
        );
        $connection = new ImapConnection($socket);

        $command = new ImapCommand('A6', 'APPEND', [
            'INBOX',
            new ImapWireString("bin\x00ary"),
        ]);
        $connection->sendCommand($command);

        self::assertSame(
            ["A6 APPEND INBOX ~{7}", "bin\x00ary", ''],
            $socket->written,
        );
    }

    public function testReadResponseParsesTaggedLine(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('A7 OK LOGIN completed.'),
        );
        $connection = new ImapConnection($socket);

        $response = $connection->readResponse();

        self::assertTrue($response->isTagged());
        self::assertSame('A7', $response->tag);
        self::assertTrue($response->isOk());
    }

    public function testWriteLineWritesBareLineWithoutTagOrCommand(): void
    {
        $socket = InMemoryImapSocket::fromParts('');
        $connection = new ImapConnection($socket);

        $connection->writeLine(base64_encode('response-bytes'));

        self::assertSame([base64_encode('response-bytes')], $socket->written);
    }

    public function testWriteLineAcceptsEmptyLine(): void
    {
        $socket = InMemoryImapSocket::fromParts('');
        $connection = new ImapConnection($socket);

        $connection->writeLine('');

        self::assertSame([''], $socket->written);
    }
}
