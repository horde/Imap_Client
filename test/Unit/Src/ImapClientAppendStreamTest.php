<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL).
 * @license http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Src;

use DateTimeImmutable;
use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\Exception\CapabilityNotSupportedException;
use Horde\Imap\Client\Exception\ServerResponseException;
use Horde\Imap\Client\Exception\WireEncodingException;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\SystemFlag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapClient::class)]
class ImapClientAppendStreamTest extends TestCase
{
    private array $streams = [];

    private function stream(string $bytes)
    {
        $stream = tmpfile();
        fwrite($stream, $bytes);
        rewind($stream);
        $this->streams[] = $stream;
        return $stream;
    }

    private function socket(string $caps, string ...$responses): InMemoryImapSocket
    {
        return InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* OK [CAPABILITY IMAP4rev1 ' . $caps . '] Ready'),
            ...array_map(InMemoryImapSocket::line(...), $responses)
        );
    }

    private function client(InMemoryImapSocket $socket): ImapClient
    {
        return new ImapClient(new ConnectionConfig(hostspec: 'imap.example.test'), client: $socket);
    }

    protected function tearDown(): void
    {
        foreach ($this->streams as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testBinaryAppendEmitsExactPayloadAndReturnsUid(): void
    {
        $socket = $this->socket('BINARY UIDPLUS', '+ ready', 'A1 OK [APPENDUID 1 7] done');
        $body = "Content-Type: application/octet-stream\r\nContent-Transfer-Encoding: binary\r\n\r\n"
            . implode('', array_map(chr(...), range(0, 255))) . "\r\n\r";
        $stream = $this->stream($body);
        $ids = $this->client($socket)->append('INBOX', [['data' => $stream]]);
        self::assertSame([7], $ids->toArray());
        self::assertSame('A1 APPEND INBOX ~{' . strlen($body) . "}\r\n" . $body . "\r\n", implode('', $socket->rawWritten));
        self::assertSame(0, ftell($stream));
        self::assertIsResource($stream);
    }

    public function testPlainStreamUsesLiteralEvenForAtomSafeContent(): void
    {
        $socket = $this->socket('UIDPLUS', '+ ready', 'A1 OK done');
        $stream = $this->stream('skiphello');
        fseek($stream, 4);
        $this->client($socket)->append('INBOX', [['data' => $stream]]);
        self::assertSame("A1 APPEND INBOX {5}\r\nhello\r\n", implode('', $socket->rawWritten));
        self::assertSame(4, ftell($stream));
    }

    public function testLargeStreamIsWrittenInBoundedChunks(): void
    {
        $socket = $this->socket('UIDPLUS', '+ ready', 'A1 OK done');
        $body = str_repeat('a', 200000) . "\r\n";
        $this->client($socket)->append('INBOX', [['data' => $this->stream($body)]]);
        self::assertSame([65536, 65536, 65536, 3394], array_map(strlen(...), array_slice($socket->rawWritten, 1, -1)));
        self::assertSame("A1 APPEND INBOX {200002}\r\n" . $body . "\r\n", implode('', $socket->rawWritten));
    }

    public function testEmptyStreamUsesZeroLengthLiteral(): void
    {
        $socket = $this->socket('UIDPLUS', '+ ready', 'A1 OK done');
        $this->client($socket)->append('INBOX', [['data' => $this->stream('')]]);
        self::assertSame("A1 APPEND INBOX {0}\r\n\r\n", implode('', $socket->rawWritten));
    }

    public function testMixedMultiAppendKeepsStringsAndStreamsInOneCommand(): void
    {
        $socket = $this->socket('BINARY MULTIAPPEND UIDPLUS', '+ ready', 'A1 OK [APPENDUID 1 7:8] done');
        $ids = $this->client($socket)->append('INBOX', [['data' => 'one'], ['data' => $this->stream("a\0b")]]);
        self::assertSame([7, 8], $ids->toArray());
        self::assertSame("A1 APPEND INBOX one ~{3}\r\na\0b\r\n", implode('', $socket->rawWritten));
    }

    public function testSeparateAppendReplaysRepeatedStreamReference(): void
    {
        $socket = $this->socket('UIDPLUS', '+ first', 'A1 OK done', '+ second', 'A2 OK done');
        $stream = $this->stream('one');
        $this->client($socket)->append('INBOX', [['data' => $stream], ['data' => $stream]]);
        self::assertSame("A1 APPEND INBOX {3}\r\none\r\nA2 APPEND INBOX {3}\r\none\r\n", implode('', $socket->rawWritten));
        self::assertSame(0, ftell($stream));
    }

    public function testMissingBinaryCapabilityRejectsBatchBeforeAnyAppend(): void
    {
        $socket = $this->socket('UIDPLUS');
        try {
            $this->client($socket)->append('INBOX', [['data' => 'one'], ['data' => $this->stream("a\0b")]]);
            self::fail('Expected unsupported binary stream');
        } catch (CapabilityNotSupportedException) {
            self::assertSame([], $socket->rawWritten);
        }
    }

    public function testNonSeekableStreamRejectsBatchBeforeAnyAppend(): void
    {
        $socket = $this->socket('UIDPLUS');
        $stream = fopen('php://output', 'wb');
        $this->streams[] = $stream;
        try {
            $this->client($socket)->append('INBOX', [['data' => 'one'], ['data' => $stream]]);
            self::fail('Expected unsupported non-seekable stream');
        } catch (WireEncodingException) {
            self::assertSame([], $socket->rawWritten);
        }
    }

    public function testTryCreateBeforeContinuationReplaysStream(): void
    {
        $socket = $this->socket('UIDPLUS', 'A1 NO [TRYCREATE] missing', 'A2 OK created', '+ retry', 'A3 OK [APPENDUID 1 9] done');
        $stream = $this->stream('hello');
        $ids = $this->client($socket)->append('INBOX', [['data' => $stream]], ['create' => true]);
        self::assertSame([9], $ids->toArray());
        self::assertSame("A1 APPEND INBOX {5}\r\nA2 CREATE INBOX\r\nA3 APPEND INBOX {5}\r\nhello\r\n", implode('', $socket->rawWritten));
        self::assertSame(0, ftell($stream));
    }

    public function testTryCreateAfterPayloadReplaysStream(): void
    {
        $socket = $this->socket('UIDPLUS', '+ first', 'A1 NO [TRYCREATE] missing', 'A2 OK created', '+ retry', 'A3 OK done');
        $stream = $this->stream('hello');
        $this->client($socket)->append('INBOX', [['data' => $stream]], ['create' => true]);
        self::assertSame("A1 APPEND INBOX {5}\r\nhello\r\nA2 CREATE INBOX\r\nA3 APPEND INBOX {5}\r\nhello\r\n", implode('', $socket->rawWritten));
        self::assertSame(0, ftell($stream));
    }

    public function testRejectedLiteralDoesNotSendPayloadOrMoveStream(): void
    {
        $socket = $this->socket('UIDPLUS', 'A1 NO too large');
        $stream = $this->stream('hello');
        try {
            $this->client($socket)->append('INBOX', [['data' => $stream]]);
            self::fail('Expected rejection');
        } catch (ServerResponseException) {
            self::assertSame(["A1 APPEND INBOX {5}\r\n"], $socket->rawWritten);
            self::assertSame(0, ftell($stream));
            self::assertIsResource($stream);
        }
    }

    public function testUtf8StreamUsesCorrectLiteral8Wrapper(): void
    {
        $socket = $this->socket('UIDPLUS', '+ ready', 'A1 OK done');
        $client = $this->client($socket);
        $client->getCapability()->enable('UTF8=ACCEPT');
        $client->append('INBOX', [['data' => $this->stream('hello')]]);
        self::assertSame("A1 APPEND INBOX UTF8 (~{5}\r\nhello)\r\n", implode('', $socket->rawWritten));
    }

    public function testStreamWorksAlongsideFlagsAndInternalDate(): void
    {
        $socket = $this->socket('UIDPLUS', '+ ready', 'A1 OK done');
        $this->client($socket)->append('INBOX', [[
            'data' => $this->stream('hello'), 'flags' => [SystemFlag::Seen],
            'internaldate' => new DateTimeImmutable('2026-10-24 12:00:00 +0000'),
        ]]);
        self::assertSame("A1 APPEND INBOX (\\seen) \"24-Oct-2026 12:00:00 +0000\" {5}\r\nhello\r\n", implode('', $socket->rawWritten));
    }

    public function testBinaryOptionForcesLiteral8ForStreamWithoutNul(): void
    {
        $socket = $this->socket('BINARY UIDPLUS', '+ ready', 'A1 OK done');
        $this->client($socket)->append('INBOX', [['data' => $this->stream("a\nb\n"), 'binary' => true]]);
        self::assertSame("A1 APPEND INBOX ~{4}\r\na\nb\n\r\n", implode('', $socket->rawWritten));
    }

    public function testBinaryOptionForcesLiteral8ForString(): void
    {
        $socket = $this->socket('BINARY UIDPLUS', '+ ready', 'A1 OK done');
        $this->client($socket)->append('INBOX', [['data' => "a\nb", 'binary' => true]]);
        self::assertSame("A1 APPEND INBOX ~{3}\r\na\nb\r\n", implode('', $socket->rawWritten));
    }

    public function testBinaryOptionWithoutBinaryCapabilityRejectsBatchBeforeAnyAppend(): void
    {
        $socket = $this->socket('UIDPLUS');
        try {
            $this->client($socket)->append('INBOX', [['data' => $this->stream('hello'), 'binary' => true]]);
            self::fail('Expected missing BINARY rejection');
        } catch (CapabilityNotSupportedException) {
            self::assertSame([], $socket->rawWritten);
        }
    }

    public function testStreamWithoutBinaryOptionStaysAnOrdinaryLiteral(): void
    {
        $socket = $this->socket('BINARY UIDPLUS', '+ ready', 'A1 OK done');
        $this->client($socket)->append('INBOX', [['data' => $this->stream("a\nb")]]);
        self::assertSame("A1 APPEND INBOX {3}\r\na\nb\r\n", implode('', $socket->rawWritten));
    }
}
