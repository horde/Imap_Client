<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL).
 * @license http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit;

use Horde\Imap\Client\Test\Stub\BinaryAppendSocket;
use Horde_Imap_Client_Data_Format_List;
use Horde_Imap_Client_Data_Format_String;
use Horde_Imap_Client_Exception;
use Horde_Imap_Client_Exception_ServerResponse;
use Horde_Imap_Client_Fetch_Results;
use Horde_Imap_Client_Interaction_Pipeline;
use Horde_Imap_Client_Interaction_Server;
use Horde_Imap_Client_Tokenize;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class SocketBinaryAppendTest extends TestCase
{
    private function socket(array $capabilities = ['BINARY']): BinaryAppendSocket
    {
        $socket = new BinaryAppendSocket(['username' => 'test', 'password' => 'test']);
        $socket->withCapabilities($capabilities);
        return $socket;
    }

    /** @return list<Horde_Imap_Client_Data_Format_String> */
    private function literals(Horde_Imap_Client_Data_Format_List $list): array
    {
        $result = [];
        foreach ($list as $value) {
            if ($value instanceof Horde_Imap_Client_Data_Format_List) {
                array_push($result, ...$this->literals($value));
            } elseif ($value instanceof Horde_Imap_Client_Data_Format_String && $value->literal()) {
                $result[] = $value;
            }
        }
        return $result;
    }

    private function message(): string
    {
        // Valid CRLF headers; every possible octet and mixed EOLs in the body.
        return "MIME-Version: 1.0\r\nContent-Type: application/octet-stream\r\n"
            . "Content-Transfer-Encoding: binary\r\n\r\n"
            . implode('', array_map(chr(...), range(0, 255)))
            . "\n\r\r\ntrailing\r";
    }

    public function testBinaryStringPreservesEveryOctetAndUsesLiteral8(): void
    {
        $socket = $this->socket();
        $message = $this->message();
        $socket->appendMessages([['data' => $message, 'binary' => true]]);
        $command = $socket->commands[0];
        $literals = $this->literals($command);
        self::assertCount(1, $literals);
        self::assertTrue($command->literal8);
        self::assertFalse($command->literalplus);
        self::assertTrue($literals[0]->binary());
        self::assertSame(strlen($message), $literals[0]->length());
        self::assertSame($message, stream_get_contents($literals[0]->getStream()->stream, -1, 0));
        self::assertSame(
            '1 APPEND INBOX ~{' . strlen($message) . "}\r\n" . $message . "\r\n",
            $socket->renderCommand($command)
        );
        self::assertSame(1, $socket->continuations);
    }

    public function testBinaryStreamIsRewoundAndNotClosed(): void
    {
        $stream = tmpfile();
        self::assertIsResource($stream);
        try {
            fwrite($stream, $this->message());
            $socket = $this->socket();
            $socket->appendMessages([['data' => $stream, 'binary' => true]]);
            $literal = $this->literals($socket->commands[0])[0];
            self::assertSame($this->message(), stream_get_contents($literal->getStream()->stream, -1, 0));
            self::assertIsResource($stream);
        } finally {
            fclose($stream);
        }
    }

    public function testBinaryWithoutNulStillUsesLiteral8(): void
    {
        $socket = $this->socket();
        $socket->appendMessages([['data' => "Header: value\r\n\r\na\nb\r", 'binary' => true]]);
        self::assertTrue($this->literals($socket->commands[0])[0]->binary());
    }

    public function testNonSeekableBinaryStreamIsRejectedBeforeSending(): void
    {
        $stream = fopen('php://output', 'wb');
        try {
            $socket = $this->socket();
            try {
                $socket->appendMessages([['data' => $stream, 'binary' => true]]);
                self::fail('Expected non-seekable stream rejection');
            } catch (\InvalidArgumentException $e) {
                self::assertSame([], $socket->commands);
            }
        } finally {
            fclose($stream);
        }
    }

    public function testExplicitFalseKeepsNormalAppendBehavior(): void
    {
        $socket = $this->socket([]);
        $socket->appendMessages([['data' => "text\n", 'binary' => false]]);
        $literal = $this->literals($socket->commands[0])[0];
        self::assertFalse($literal->binary());
        self::assertSame("text\r\n", stream_get_contents($literal->getStream()->stream, -1, 0));
    }

    public function testDisconnectDoesNotRetryBinaryAppend(): void
    {
        $socket = $this->socket();
        $socket->failure = new Horde_Imap_Client_Exception('Disconnected', Horde_Imap_Client_Exception::DISCONNECT);
        try {
            $socket->appendMessages([['data' => $this->message(), 'binary' => true]]);
            self::fail('Expected disconnect');
        } catch (Horde_Imap_Client_Exception $e) {
            self::assertSame($socket->failure, $e);
            self::assertCount(1, $socket->commands);
        }
    }

    public function testDefaultAppendStillNormalizesEols(): void
    {
        $socket = $this->socket([]);
        $socket->appendMessages([['data' => "Header: value\n\na\rb\nc\r\n"]]);
        $literal = $this->literals($socket->commands[0])[0];
        self::assertFalse($literal->binary());
        self::assertSame("Header: value\r\n\r\na\r\nb\r\nc\r\n", stream_get_contents($literal->getStream()->stream, -1, 0));
    }

    public function testMissingBinaryCapabilityRejectsWholeBatchBeforeSending(): void
    {
        $socket = $this->socket([]);
        try {
            $socket->appendMessages([['data' => 'first'], ['data' => $this->message(), 'binary' => true]]);
            self::fail('Expected a capability error');
        } catch (Horde_Imap_Client_Exception $e) {
            self::assertSame(Horde_Imap_Client_Exception::NOT_SUPPORTED, $e->getCode());
            self::assertSame([], $socket->commands);
        }
    }

    public function testMultiAppendKeepsBinaryModePerMessage(): void
    {
        $socket = $this->socket(['BINARY', 'MULTIAPPEND']);
        $socket->appendMessages([
            ['data' => "text\n"],
            ['data' => $this->message(), 'binary' => true],
        ]);
        self::assertCount(1, $socket->commands);
        $literals = $this->literals($socket->commands[0]);
        self::assertFalse($literals[0]->binary());
        self::assertSame("text\r\n", stream_get_contents($literals[0]->getStream()->stream, -1, 0));
        self::assertTrue($literals[1]->binary());
        self::assertSame($this->message(), stream_get_contents($literals[1]->getStream()->stream, -1, 0));
    }

    public function testWithoutMultiAppendPreservesBinaryMode(): void
    {
        $socket = $this->socket();
        $socket->appendMessages([
            ['data' => $this->message(), 'binary' => true],
            ['data' => $this->message(), 'binary' => true],
        ]);
        self::assertCount(2, $socket->commands);
        foreach ($socket->commands as $command) {
            self::assertTrue($this->literals($command)[0]->binary());
        }
    }

    public function testBinaryCatenateTextPartsPreserveTheirBytes(): void
    {
        $socket = $this->socket(['BINARY', 'CATENATE']);
        $socket->appendMessages([['binary' => true, 'data' => [
            ['t' => 'text', 'v' => "header\r\n\r\n"],
            ['t' => 'text', 'v' => "\0\n\r\xff"],
        ]]]);
        $literals = $this->literals($socket->commands[0]);
        self::assertCount(2, $literals);
        self::assertTrue($literals[0]->binary());
        self::assertTrue($literals[1]->binary());
        self::assertSame("header\r\n\r\n", stream_get_contents($literals[0]->getStream()->stream, -1, 0));
        self::assertSame("\0\n\r\xff", stream_get_contents($literals[1]->getStream()->stream, -1, 0));
    }

    public function testCatenateFallbackAssemblesWithoutNormalizingBinaryParts(): void
    {
        $socket = $this->socket();
        $socket->appendMessages([['binary' => true, 'data' => [
            ['t' => 'text', 'v' => "header\r\n\r\n"],
            ['t' => 'text', 'v' => "\0\n\r\xff"],
        ]]]);
        $literal = $this->literals($socket->commands[0])[0];
        self::assertTrue($literal->binary());
        self::assertSame("header\r\n\r\n\0\n\r\xff", stream_get_contents($literal->getStream()->stream, -1, 0));
    }

    public function testServerRejectionDoesNotDowngradeBinaryAppend(): void
    {
        $socket = $this->socket();
        $server = Horde_Imap_Client_Interaction_Server::create(new Horde_Imap_Client_Tokenize('1 NO [UNKNOWN-CTE] Cannot store binary'));
        $socket->failure = new Horde_Imap_Client_Exception_ServerResponse(
            'Cannot store binary',
            0,
            $server,
            new Horde_Imap_Client_Interaction_Pipeline(new Horde_Imap_Client_Fetch_Results())
        );
        try {
            $socket->appendMessages([['data' => $this->message(), 'binary' => true]]);
            self::fail('Expected server rejection');
        } catch (Horde_Imap_Client_Exception_ServerResponse $e) {
            self::assertSame($socket->failure, $e);
            self::assertCount(1, $socket->commands);
            self::assertTrue($socket->capability->query('BINARY'));
        }
    }
}
