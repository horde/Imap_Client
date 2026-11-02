<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 * See the enclosed file LICENSE for license information (LGPL).
 * @license http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\Exception\CapabilityNotSupportedException;
use Horde\Imap\Client\Exception\ImapProtocolException;
use Horde\Imap\Client\Exception\ServerResponseException;
use Horde\Imap\Client\ImapCacheStore;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapFetchParser;
use Horde\Imap\Client\ImapFetchQuery;
use Horde\Imap\Client\ImapFetchResult;
use Horde\Imap\Client\ImapIdSet;
use Horde\Imap\Client\ImapResponseParser;
use Horde\Imap\Client\ImapTokenizer;
use Horde\Imap\Client\OpenMode;
use Horde\Socket\Client\ClientInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapFetchQuery::class)]
#[CoversClass(ImapFetchParser::class)]
#[CoversClass(ImapFetchResult::class)]
#[CoversClass(ImapClient::class)]
class ImapBinaryFetchTest extends TestCase
{
    private function parse(string $wire): ImapFetchResult
    {
        $response = ImapResponseParser::parse((new ImapTokenizer(new InMemoryImapSocket($wire . "\r\n")))->readLine());
        $parsed = ImapFetchParser::parse($response, new ImapFetchQuery());
        self::assertNotNull($parsed);
        return $parsed['result'];
    }

    private function socket(string $caps, string ...$lines): InMemoryImapSocket
    {
        return InMemoryImapSocket::fromParts(
            '* OK [CAPABILITY IMAP4rev1 ' . $caps . "] ready\r\n",
            ...array_map(InMemoryImapSocket::line(...), $lines)
        );
    }

    private function client(InMemoryImapSocket $socket, ?ImapCacheStore $cache = null): ImapClient
    {
        return new ImapClient(new ConnectionConfig(hostspec: 'imap.example.test'), client: $socket, cache: $cache);
    }

    public function testQueryBuildsPeekDecodedRangesAndSizes(): void
    {
        $query = (new ImapFetchQuery())->binaryBodyPart('1.2', 4, 8)->binaryBodyPartSize('1.2');
        self::assertSame(['BINARY.PEEK[1.2]<4.8>', 'BINARY.SIZE[1.2]'], $query->wireItems());
        self::assertTrue($query->wantsBinary());
        self::assertTrue($query->wantsStreamContent());
    }

    public function testWholeSectionAndImplicitZeroOffset(): void
    {
        $query = (new ImapFetchQuery())->binaryBodyPart('', length: 32)->binaryBodyPartSize('');
        self::assertSame(['BINARY.PEEK[]<0.32>', 'BINARY.SIZE[]'], $query->wireItems());
    }

    public function testExplicitNonPeekAndDeduplication(): void
    {
        $query = (new ImapFetchQuery())->binaryBodyPart(1, peek: false)->binaryBodyPart(1, peek: false);
        self::assertSame(['BINARY[1]'], $query->wireItems());
    }

    public function testSizeOnlyIsNotStreamContentButRequiresBinary(): void
    {
        $query = (new ImapFetchQuery())->binaryBodyPartSize(1);
        self::assertFalse($query->wantsStreamContent());
        self::assertTrue($query->wantsBinary());
        self::assertFalse((new ImapFetchQuery())->wantsBinary());
    }

    public function testInvalidSectionsCannotInjectFetchItems(): void
    {
        foreach (['HEADER', '1.TEXT', '1] FLAGS BODY[1', '-1', '0', '1.0', '1..2'] as $section) {
            foreach (['binaryBodyPart', 'binaryBodyPartSize'] as $method) {
                try {
                    (new ImapFetchQuery())->$method($section);
                    self::fail('Expected invalid section rejection');
                } catch (\InvalidArgumentException) {
                    self::assertTrue(true);
                }
            }
        }
    }

    public function testInvalidPartialsAreRejected(): void
    {
        foreach ([[-1, 3], [0, 0], [0, -1], [5, null]] as [$start, $length]) {
            try {
                (new ImapFetchQuery())->binaryBodyPart(1, $start, $length);
                self::fail('Expected invalid partial rejection');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testParserPreservesLiteral8OctetsAndPartialOffset(): void
    {
        $bytes = implode('', array_map(chr(...), range(0, 255))) . "\r\n\r";
        $result = $this->parse('* 1 FETCH (UID 7 BINARY[1.2]<32> ~{' . strlen($bytes) . "}\r\n" . $bytes . ')');
        self::assertSame($bytes, $result->getBinaryBodyPart('1.2')?->getString(0));
        self::assertSame($bytes, $result->getBodyPart('1.2')->getString(0));
        self::assertSame(7, $result->getUid());
    }

    public function testQuotedBinaryPayloadIsNotDecodedAgain(): void
    {
        $result = $this->parse('* 1 FETCH (BINARY[1] "AA==")');
        self::assertSame('AA==', $result->getBinaryBodyPart(1)?->getString(0));
    }

    public function testNilAndEmptyDecodedBodyAreDistinct(): void
    {
        $nil = $this->parse('* 1 FETCH (BINARY[1] NIL)');
        $empty = $this->parse('* 1 FETCH (BINARY[1] {0}' . "\r\n" . ')');
        self::assertNull($nil->getBinaryBodyPart(1));
        self::assertNotNull($empty->getBinaryBodyPart(1));
        self::assertSame('', $empty->getBinaryBodyPart(1)?->getString(0));
    }

    public function testEncodedAndDecodedBodiesRemainDistinctInEitherOrder(): void
    {
        foreach (['BODY[1] "aGVsbG8=" BINARY[1] "hello"', 'BINARY[1] "hello" BODY[1] "aGVsbG8="'] as $items) {
            $result = $this->parse('* 1 FETCH (' . $items . ')');
            self::assertSame('aGVsbG8=', $result->getBodyPart('1')->getString(0));
            self::assertSame('hello', $result->getBinaryBodyPart('1')?->getString(0));
        }
    }

    public function testDecodedSizeParsesZeroAndLargeValues(): void
    {
        $result = $this->parse('* 1 FETCH (BINARY.SIZE[1] 0 BINARY.SIZE[1.2] 5368709120)');
        self::assertSame(0, $result->getBodyPartSize(1));
        self::assertSame(5368709120, $result->getBodyPartSize('1.2'));
        self::assertNull($result->getBodyPartSize('2'));
    }

    public function testMalformedDecodedSizeIsRejected(): void
    {
        foreach (['NIL', '-1', '1.5', '9999999999999999999999999999'] as $size) {
            try {
                $this->parse('* 1 FETCH (BINARY.SIZE[1] ' . $size . ')');
                self::fail('Expected invalid size rejection');
            } catch (ImapProtocolException) {
                self::assertTrue(true);
            }
        }
    }

    public function testClientSendsUidPartialPeekAndSize(): void
    {
        $socket = $this->socket('BINARY', '* 1 FETCH (UID 7 BINARY[1]<2> ~{3}' . "\r\na\0b BINARY.SIZE[1] 99)", 'A1 OK done');
        $client = $this->client($socket);
        $query = (new ImapFetchQuery())->binaryBodyPart(1, 2, 3)->binaryBodyPartSize(1);
        $results = iterator_to_array($client->fetch('INBOX', new ImapIdSet([7]), $query));
        self::assertSame(['A1 UID FETCH 7 (BINARY.PEEK[1]<2.3> BINARY.SIZE[1])'], $socket->written);
        self::assertSame("a\0b", $results[7]->getBinaryBodyPart(1)?->getString(0));
        self::assertSame(99, $results[7]->getBodyPartSize(1));
    }

    public function testClientSupportsSequenceModeAndExplicitSeenSideEffect(): void
    {
        $socket = $this->socket('BINARY', '* 1 FETCH (BINARY[1] "hello")', 'A1 OK done');
        $results = iterator_to_array($this->client($socket)->fetch('INBOX', new ImapIdSet([1], true), (new ImapFetchQuery())->binaryBodyPart(1, peek: false)));
        self::assertSame(['A1 FETCH 1 (BINARY[1])'], $socket->written);
        self::assertSame('hello', $results[1]->getBinaryBodyPart(1)?->getString(0));
    }

    public function testMissingCapabilityFailsBeforeFetch(): void
    {
        foreach ([(new ImapFetchQuery())->binaryBodyPart(1), (new ImapFetchQuery())->binaryBodyPartSize(1)] as $query) {
            $socket = $this->socket('');
            try {
                iterator_to_array($this->client($socket)->fetch('INBOX', new ImapIdSet([7]), $query));
                self::fail('Expected capability rejection');
            } catch (CapabilityNotSupportedException) {
                self::assertSame([], $socket->written);
            }
        }
    }

    public function testUnknownCteIsPropagatedWithoutRawFallback(): void
    {
        $socket = $this->socket('BINARY', 'A1 NO [UNKNOWN-CTE] Cannot decode');
        try {
            iterator_to_array($this->client($socket)->fetch('INBOX', new ImapIdSet([7]), (new ImapFetchQuery())->binaryBodyPart(1)));
            self::fail('Expected UNKNOWN-CTE');
        } catch (ServerResponseException $e) {
            self::assertSame('UNKNOWN-CTE', $e->responseCode?->name);
            self::assertCount(1, $socket->written);
        }
    }

    public function testBinaryQueriesCannotBeSatisfiedByMetadataCache(): void
    {
        $socket = $this->socket('BINARY',
            '* OK [UIDVALIDITY 42] valid', 'A1 OK [READ-WRITE] selected',
            '* 1 FETCH (UID 7 FLAGS ())', 'A2 OK done',
            '* 1 FETCH (UID 7 FLAGS () BINARY[1] "hello")', 'A3 OK done',
            '* 1 FETCH (UID 7 FLAGS () BINARY.SIZE[1] 5)', 'A4 OK done'
        );
        $cache = new ImapCacheStore(new ArrayCache(), 'imap.example.test', 993, 'test');
        $client = $this->client($socket, $cache);
        $client->openMailbox('INBOX', OpenMode::ReadWrite);
        iterator_to_array($client->fetch('INBOX', new ImapIdSet([7]), (new ImapFetchQuery())->flags()));
        $body = iterator_to_array($client->fetch('INBOX', new ImapIdSet([7]), (new ImapFetchQuery())->flags()->binaryBodyPart(1)));
        $size = iterator_to_array($client->fetch('INBOX', new ImapIdSet([7]), (new ImapFetchQuery())->flags()->binaryBodyPartSize(1)));
        self::assertSame('hello', $body[7]->getBinaryBodyPart(1)?->getString(0));
        self::assertSame(5, $size[7]->getBodyPartSize(1));
        self::assertCount(4, $socket->written);
    }

    public function testLiteralIsReassembledAcrossShortSocketReads(): void
    {
        foreach (['~', ''] as $marker) {
            $socket = $this->createMock(ClientInterface::class);
            $socket->method('gets')->willReturnOnConsecutiveCalls('* 1 FETCH (BINARY[1] ' . $marker . "{4}\r\n", ")\r\n");
            $socket->expects(self::exactly(4))->method('read')->willReturnOnConsecutiveCalls("\0", 'a', "\r", "\n");
            $socket->expects(self::never())->method('close');
            $response = ImapResponseParser::parse((new ImapTokenizer($socket))->readLine());
            $parsed = ImapFetchParser::parse($response, new ImapFetchQuery());
            self::assertSame("\0a\r\n", $parsed['result']->getBinaryBodyPart(1)?->getString(0));
        }
    }

    public function testTruncatedLiteralFailsAndClosesConnection(): void
    {
        $socket = $this->createMock(ClientInterface::class);
        $socket->method('gets')->willReturn("* 1 FETCH (BINARY[1] ~{4}\r\n");
        $socket->method('read')->willReturnOnConsecutiveCalls("\0a", '');
        $socket->expects(self::once())->method('close');
        $this->expectException(ImapProtocolException::class);
        (new ImapTokenizer($socket))->readLine();
    }
}
