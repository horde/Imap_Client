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

use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\Exception\CapabilityNotSupportedException;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapResponseParser;
use Horde\Imap\Client\ImapSearchQuery;
use Horde\Imap\Client\ImapThreadParser;
use Horde\Imap\Client\ImapThreadResult;
use Horde\Imap\Client\ImapTokenizer;
use Horde\Imap\Client\SortCriteria;
use Horde\Imap\Client\ThreadAlgorithm;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Thread parsing/result and the sort search option.
 */
#[CoversClass(ImapClient::class)]
#[CoversClass(ImapThreadParser::class)]
#[CoversClass(ImapThreadResult::class)]
class ImapClientThreadSortTest extends TestCase
{
    private function config(): ConnectionConfig
    {
        return new ConnectionConfig(
            hostspec: 'imap.example.test',
            saslPolicy: SaslPolicy::legacyCompatible(),
        );
    }

    private function client(InMemoryImapSocket $socket): ImapClient
    {
        return new ImapClient($this->config(), null, null, $socket);
    }

    private function socket(string ...$lines): InMemoryImapSocket
    {
        return InMemoryImapSocket::fromParts(
            ...array_map(InMemoryImapSocket::line(...), $lines),
        );
    }

    private function threadResponse(string $line): \Horde\Imap\Client\ImapResponse
    {
        $tokens = (new ImapTokenizer($this->socket($line)))->readLine();

        return ImapResponseParser::parse($tokens);
    }

    public function testParseFlatThreads(): void
    {
        $result = ImapThreadParser::parse([$this->threadResponse('* THREAD (2)(3)(6)')], false);

        self::assertCount(3, $result);
        self::assertSame([2, 3, 6], $result->messageList()->toArray());
    }

    public function testParseNestedThreadLevels(): void
    {
        // (3 6 (4 23)(44 7 96)): 3->0 6->1, then siblings share level 2.
        $result = ImapThreadParser::parse(
            [$this->threadResponse('* THREAD (3 6 (4 23)(44 7 96))')],
            false,
        );

        self::assertCount(1, $result->getThreads());

        $thread = $result->getThread(3);
        self::assertSame(3, $thread[3]->base);
        self::assertSame(0, $thread[3]->level);
        self::assertSame(1, $thread[6]->level);
        self::assertSame(2, $thread[4]->level);
        self::assertSame(3, $thread[23]->level);
        // The second sub-thread starts back at level 2, not continuing.
        self::assertSame(2, $thread[44]->level);
        self::assertSame(3, $thread[7]->level);
        self::assertSame(4, $thread[96]->level);
    }

    public function testLoneMessageThreadHasNullBase(): void
    {
        $result = ImapThreadParser::parse([$this->threadResponse('* THREAD (5)')], false);

        $thread = $result->getThread(5);
        self::assertNull($thread[5]->base);
        self::assertTrue($thread[5]->last);
    }

    public function testGetThreadUnknownIndexIsEmpty(): void
    {
        $result = ImapThreadParser::parse([$this->threadResponse('* THREAD (1)(2)')], false);

        self::assertSame([], $result->getThread(99));
    }

    public function testGetThreadsReturnsAll(): void
    {
        $result = ImapThreadParser::parse(
            [$this->threadResponse('* THREAD (1 2)(3)')],
            false,
        );

        $threads = $result->getThreads();
        self::assertCount(2, $threads);
        self::assertSame(1, $threads[0][1]->base);
        self::assertNull($threads[1][3]->base);
    }

    public function testThreadSendsUidThreadOrderedSubjectAll(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 THREAD=ORDEREDSUBJECT] Server ready.',
            '* THREAD (1)(2 3)',
            'A1 OK THREAD completed.',
        );
        $client = $this->client($socket);

        $result = $client->thread('INBOX');

        self::assertSame(['A1 UID THREAD ORDEREDSUBJECT US-ASCII ALL'], $socket->written);
        self::assertCount(3, $result);
        self::assertFalse($result->isSequence());
    }

    public function testThreadReferencesWithSearchCriteria(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 THREAD=REFERENCES] Server ready.',
            '* THREAD (1)',
            'A1 OK THREAD completed.',
        );
        $client = $this->client($socket);

        $client->thread('INBOX', [
            'criteria' => ThreadAlgorithm::References,
            'search' => (new ImapSearchQuery())->flag('\\Seen'),
            'sequence' => true,
        ]);

        self::assertSame(['A1 THREAD REFERENCES US-ASCII SEEN'], $socket->written);
    }

    public function testThreadThrowsWhenAlgorithmUnsupported(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1 THREAD=ORDEREDSUBJECT] Server ready.');
        $client = $this->client($socket);

        $this->expectException(CapabilityNotSupportedException::class);

        $client->thread('INBOX', ['criteria' => ThreadAlgorithm::References]);
    }

    public function testSearchSortSendsUidSortAndPreservesOrder(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 SORT] Server ready.',
            '* SORT 5 3 4 1 2',
            'A1 OK SORT completed.',
        );
        $client = $this->client($socket);

        $result = $client->search('INBOX', (new ImapSearchQuery())->flag('\\Seen'), [
            'sort' => [SortCriteria::Date],
        ]);

        self::assertSame(['A1 UID SORT (DATE) US-ASCII SEEN'], $socket->written);
        self::assertSame([5, 3, 4, 1, 2], $result->match->toArray());
    }

    public function testSearchSortReverseAndMultipleCriteria(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 SORT] Server ready.',
            '* SORT 1',
            'A1 OK SORT completed.',
        );
        $client = $this->client($socket);

        $client->search('INBOX', new ImapSearchQuery(), [
            'sort' => [SortCriteria::Reverse, SortCriteria::Date, SortCriteria::Subject],
        ]);

        self::assertSame(['A1 UID SORT (REVERSE DATE SUBJECT) US-ASCII ALL'], $socket->written);
    }

    public function testSearchSortUsesEsortReturnWhenAvailable(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 SORT ESORT] Server ready.',
            '* ESEARCH (TAG "A1") UID COUNT 2 ALL 3,7',
            'A1 OK SORT completed.',
        );
        $client = $this->client($socket);

        $result = $client->search('INBOX', new ImapSearchQuery(), [
            'sort' => [SortCriteria::Arrival],
        ]);

        self::assertSame(['A1 UID SORT RETURN (ALL) (ARRIVAL) US-ASCII ALL'], $socket->written);
        self::assertSame([3, 7], $result->match->toArray());
    }

    public function testSearchSortThrowsWhenSortUnsupported(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1] Server ready.');
        $client = $this->client($socket);

        $this->expectException(CapabilityNotSupportedException::class);

        $client->search('INBOX', new ImapSearchQuery(), ['sort' => [SortCriteria::Date]]);
    }
}
