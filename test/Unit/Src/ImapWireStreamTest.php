<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL).
 * @license http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\Exception\WireEncodingException;
use Horde\Imap\Client\Exception\ServerResponseException;
use Horde\Imap\Client\ImapCommand;
use Horde\Imap\Client\ImapConnection;
use Horde\Imap\Client\ImapInteraction;
use Horde\Imap\Client\ImapPipeline;
use Horde\Imap\Client\ImapWireStream;
use Horde\Socket\Client\ClientInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ImapWireStream::class)]
class ImapWireStreamTest extends TestCase
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

    protected function tearDown(): void
    {
        foreach ($this->streams as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testCapturesCurrentPositionAndReplaysExactBytes(): void
    {
        $body = "a\0b\rc\nd\r\n\xff\r\n";
        $stream = $this->stream('skip' . $body);
        fseek($stream, 4);
        $literal = new ImapWireStream($stream);
        self::assertSame(4, ftell($stream));
        self::assertSame(strlen($body), $literal->length());
        self::assertTrue($literal->isLiteral());
        self::assertTrue($literal->isBinary());
        self::assertSame($body, implode('', iterator_to_array($literal->chunks())));
        self::assertSame($body, implode('', iterator_to_array($literal->chunks())));
        self::assertSame(4, ftell($stream));
        self::assertIsResource($stream);
    }

    public function testNulInSkippedPrefixDoesNotRequireBinary(): void
    {
        $stream = $this->stream("\0plain");
        fseek($stream, 1);
        self::assertFalse((new ImapWireStream($stream))->isBinary());
    }

    public function testNulBeyondFirstChunkIsDetected(): void
    {
        $stream = $this->stream(str_repeat('a', 65536) . "\0");
        self::assertTrue((new ImapWireStream($stream))->isBinary());
        self::assertSame(0, ftell($stream));
    }

    public function testChunksAreBoundedAndPreserveTail(): void
    {
        $bytes = str_repeat('a', 131072) . "\r\n";
        $literal = new ImapWireStream($this->stream($bytes));
        $chunks = iterator_to_array($literal->chunks());
        self::assertSame([65536, 65536, 2], array_map(strlen(...), $chunks));
        self::assertSame($bytes, implode('', $chunks));
    }

    public function testEmptyStreamIsZeroLengthLiteral(): void
    {
        $literal = new ImapWireStream($this->stream(''));
        self::assertSame(0, $literal->length());
        self::assertSame([], iterator_to_array($literal->chunks()));
        self::assertFalse($literal->isBinary());
    }

    public function testForceBinarySupportsUtf8Literal8WithoutNul(): void
    {
        $literal = new ImapWireStream($this->stream('plain'), true);
        self::assertTrue($literal->isBinary());
        self::assertFalse($literal->containsNul());
    }

    public function testNonSeekableStreamIsRejected(): void
    {
        $stream = fopen('php://output', 'wb');
        $this->streams[] = $stream;
        $this->expectException(WireEncodingException::class);
        new ImapWireStream($stream);
    }

    public function testClosedStreamIsRejected(): void
    {
        $stream = $this->stream('plain');
        fclose($stream);
        $this->expectException(WireEncodingException::class);
        new ImapWireStream($stream);
    }

    public function testRawBytesDoesNotMaterializeTheStream(): void
    {
        $literal = new ImapWireStream($this->stream('plain'));
        $this->expectException(WireEncodingException::class);
        $literal->rawBytes();
    }

    public function testLiteralCannotBeEscapedInline(): void
    {
        $literal = new ImapWireStream($this->stream('plain'));
        $this->expectException(WireEncodingException::class);
        $literal->escape();
    }

    public function testTruncatedInputClosesConnectionAndRestoresPosition(): void
    {
        $stream = $this->stream('payload');
        $literal = new ImapWireStream($stream);
        ftruncate($stream, 0);
        $socket = InMemoryImapSocket::fromParts("+ ready\r\n");
        try {
            (new ImapConnection($socket))->sendCommand(new ImapCommand('A1', 'APPEND', ['INBOX', $literal]));
            self::fail('Expected a truncated source error');
        } catch (WireEncodingException) {
            self::assertTrue($socket->closed);
            self::assertSame(["A1 APPEND INBOX {7}\r\n"], $socket->rawWritten);
            self::assertSame(0, ftell($stream));
        }
    }

    public function testNonSynchronizingStreamPreservesPayloadFraming(): void
    {
        $socket = InMemoryImapSocket::fromParts('');
        $literal = new ImapWireStream($this->stream("a\0b\r\n"));
        (new ImapConnection($socket))->sendCommand(new ImapCommand('A1', 'APPEND', ['INBOX', $literal]), true);
        self::assertSame("A1 APPEND INBOX ~{5+}\r\na\0b\r\n\r\n", implode('', $socket->rawWritten));
    }

    public function testWriteFailureClosesConnectionAndRestoresBorrowedStream(): void
    {
        $stream = $this->stream('skiphello');
        fseek($stream, 4);
        $literal = new ImapWireStream($stream);
        $socket = $this->createMock(ClientInterface::class);
        $socket->method('gets')->willReturn("+ ready\r\n");
        $writes = 0;
        $socket->expects(self::exactly(2))->method('write')->willReturnCallback(
            static function (string $data) use (&$writes): void {
                if (++$writes === 2) {
                    throw new RuntimeException('Injected write failure');
                }
            }
        );
        $socket->expects(self::once())->method('close');
        try {
            (new ImapConnection($socket))->sendCommand(new ImapCommand('A1', 'APPEND', ['INBOX', $literal]));
            self::fail('Expected write error');
        } catch (RuntimeException $e) {
            self::assertSame('Injected write failure', $e->getMessage());
            self::assertSame(4, ftell($stream));
            self::assertIsResource($stream);
        }
    }

    public function testClosedSourceCannotSendAnIncompleteLiteralThenContinue(): void
    {
        $stream = $this->stream('payload');
        $literal = new ImapWireStream($stream);
        fclose($stream);
        $socket = InMemoryImapSocket::fromParts("+ ready\r\n");
        try {
            (new ImapConnection($socket))->sendCommand(new ImapCommand('A1', 'APPEND', ['INBOX', $literal]));
            self::fail('Expected closed source error');
        } catch (WireEncodingException) {
            self::assertTrue($socket->closed);
            self::assertSame(["A1 APPEND INBOX {7}\r\n"], $socket->rawWritten);
        }
    }

    public function testExplicitLengthConsumesNonSeekableSourceOnceAndLeavesTail(): void
    {
        [$stream, $writer] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($writer, "a\0bTAIL");
        fclose($writer);
        $this->streams[] = $stream;
        self::assertFalse(stream_get_meta_data($stream)['seekable']);
        $literal = new ImapWireStream($stream, forceBinary: true, length: 3);
        self::assertTrue($literal->canReplay());
        self::assertSame(3, $literal->length());
        self::assertTrue($literal->isBinary());
        self::assertSame("a\0b", implode('', iterator_to_array($literal->chunks())));
        self::assertFalse($literal->canReplay());
        self::assertSame('TAIL', stream_get_contents($stream));
        self::assertIsResource($stream);
        $this->expectException(WireEncodingException::class);
        iterator_to_array($literal->chunks());
    }

    public function testExplicitLengthMakesNoPreparatoryReadsOrSeeks(): void
    {
        $stream = $this->stream('skip' . str_repeat('x', 131073) . 'tail');
        fseek($stream, 4);
        $literal = new ImapWireStream($stream, forceBinary: true, length: 131073);
        self::assertSame(4, ftell($stream));
        self::assertSame([65536, 65536, 1], array_map(strlen(...), iterator_to_array($literal->chunks())));
        self::assertSame(131077, ftell($stream), 'Explicit-length mode must not restore the source');
        self::assertSame('tail', stream_get_contents($stream));
    }

    public function testExplicitZeroLengthDoesNotReadSource(): void
    {
        $stream = $this->stream('untouched');
        $literal = new ImapWireStream($stream, forceBinary: true, length: 0);
        self::assertSame([], iterator_to_array($literal->chunks()));
        self::assertSame(0, ftell($stream));
    }

    public function testExplicitLengthRequiresBinaryMode(): void
    {
        $this->expectException(WireEncodingException::class);
        new ImapWireStream($this->stream('abc'), length: 3);
    }

    public function testNegativeExplicitLengthIsRejected(): void
    {
        $this->expectException(WireEncodingException::class);
        new ImapWireStream($this->stream('abc'), forceBinary: true, length: -1);
    }

    public function testShortForwardOnlySourceClosesConnectionWithoutTerminator(): void
    {
        $source = $this->stream('ab');
        $literal = new ImapWireStream($source, forceBinary: true, length: 3);
        $socket = InMemoryImapSocket::fromParts("+ ready\r\n");
        try {
            (new ImapConnection($socket))->sendCommand(new ImapCommand('A1', 'APPEND', ['INBOX', $literal]));
            self::fail('Expected short source rejection');
        } catch (WireEncodingException) {
            self::assertTrue($socket->closed);
            self::assertSame(["A1 APPEND INBOX ~{3}\r\n", 'ab'], $socket->rawWritten);
            self::assertIsResource($source);
        }
    }

    public function testRejectedStreamAnnouncementReleasesPendingCommand(): void
    {
        $socket = InMemoryImapSocket::fromParts("A1 NO [TRYCREATE] Missing mailbox\r\n");
        $pipeline = new ImapPipeline();
        $interaction = new ImapInteraction(new ImapConnection($socket), pipeline: $pipeline);
        try {
            $interaction->send('APPEND', ['INBOX', new ImapWireStream($this->stream('hello'))]);
            self::fail('Expected rejection');
        } catch (ServerResponseException $e) {
            self::assertSame('TRYCREATE', $e->responseCode?->name);
            self::assertTrue($pipeline->isEmpty());
        }
    }
}
