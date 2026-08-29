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
use Horde\Imap\Client\Exception\ImapProtocolException;
use Horde\Imap\Client\Exception\ServerResponseException;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapSearchParser;
use Horde\Imap\Client\ImapSearchQuery;
use Horde\Imap\Client\ImapSearchResult;
use Horde\Imap\Client\ImapTokenizer;
use Horde\Imap\Client\ImapResponseParser;
use Horde\Imap\Client\SearchResultType;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * SEARCH/ESEARCH parsing and {@see ImapClient::search()}.
 */
#[CoversClass(ImapClient::class)]
#[CoversClass(ImapSearchParser::class)]
#[CoversClass(ImapSearchResult::class)]
class ImapClientSearchTest extends TestCase
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

    /**
     * Tokenize/parse a single wire line into an ImapResponse for the
     * parser-only tests.
     */
    private function response(string $line): \Horde\Imap\Client\ImapResponse
    {
        $tokens = (new ImapTokenizer($this->socket($line)))->readLine();

        return ImapResponseParser::parse($tokens);
    }

    public function testParseClassicSearchDerivesCountMinMax(): void
    {
        $result = ImapSearchParser::parse([$this->response('* SEARCH 3 7 11')], false);

        self::assertSame([3, 7, 11], $result->match->toArray());
        self::assertSame(3, $result->count);
        self::assertSame(3, $result->min);
        self::assertSame(11, $result->max);
    }

    public function testParseClassicSearchEmpty(): void
    {
        $result = ImapSearchParser::parse([$this->response('* SEARCH')], false);

        self::assertSame([], $result->match->toArray());
        self::assertSame(0, $result->count);
        self::assertNull($result->min);
        self::assertNull($result->max);
    }

    public function testParseClassicSearchAcrossMultipleLines(): void
    {
        $result = ImapSearchParser::parse(
            [$this->response('* SEARCH 1 2'), $this->response('* SEARCH 5 6')],
            false,
        );

        self::assertSame([1, 2, 5, 6], $result->match->toArray());
    }

    public function testParseEsearchWithCountMinMaxAll(): void
    {
        $result = ImapSearchParser::parse(
            [$this->response('* ESEARCH (TAG "A1") UID COUNT 4 MIN 2 MAX 28 ALL 2,4:6,28')],
            false,
        );

        self::assertSame([2, 4, 5, 6, 28], $result->match->toArray());
        self::assertSame(4, $result->count);
        self::assertSame(2, $result->min);
        self::assertSame(28, $result->max);
    }

    public function testParseEsearchCountOnly(): void
    {
        $result = ImapSearchParser::parse(
            [$this->response('* ESEARCH (TAG "A1") UID COUNT 9')],
            false,
        );

        self::assertSame(9, $result->count);
        self::assertSame([], $result->match->toArray());
        self::assertNull($result->min);
    }

    public function testSearchUsesUidSearchByDefault(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* SEARCH 1 2 3',
            'A1 OK SEARCH completed.',
        );
        $client = $this->client($socket);

        $result = $client->search('INBOX', (new ImapSearchQuery())->flag('\\Seen'));

        self::assertSame(['A1 UID SEARCH SEEN'], $socket->written);
        self::assertSame([1, 2, 3], $result->match->toArray());
    }

    public function testSearchSequenceMode(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* SEARCH 4 5',
            'A1 OK SEARCH completed.',
        );
        $client = $this->client($socket);

        $result = $client->search('INBOX', (new ImapSearchQuery())->flag('\\Seen'), ['sequence' => true]);

        self::assertSame(['A1 SEARCH SEEN'], $socket->written);
        self::assertTrue($result->match->isSequence());
    }

    public function testSearchEmptyQuerySendsAll(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* SEARCH 1',
            'A1 OK SEARCH completed.',
        );
        $client = $this->client($socket);

        $client->search('INBOX', new ImapSearchQuery());

        self::assertSame(['A1 UID SEARCH ALL'], $socket->written);
    }

    public function testSearchUsesEsearchReturnWhenAvailable(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 ESEARCH] Server ready.',
            '* ESEARCH (TAG "A1") UID COUNT 2 ALL 3,7',
            'A1 OK SEARCH completed.',
        );
        $client = $this->client($socket);

        $result = $client->search(
            'INBOX',
            (new ImapSearchQuery())->flag('\\Seen'),
            ['results' => [SearchResultType::Count, SearchResultType::Match]],
        );

        self::assertSame(['A1 UID SEARCH RETURN (COUNT ALL) SEEN'], $socket->written);
        self::assertSame(2, $result->count);
        self::assertSame([3, 7], $result->match->toArray());
    }

    public function testSearchEmitsCharsetWhenQueryDeclaresOne(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* SEARCH',
            'A1 OK SEARCH completed.',
        );
        $client = $this->client($socket);

        // An explicit charset with an ASCII value keeps the value inline
        // (no literal / continuation), so the whole command is one line.
        $query = (new ImapSearchQuery())->charset('UTF-8')->text('plain');
        $client->search('INBOX', $query);

        self::assertSame(['A1 UID SEARCH CHARSET UTF-8 BODY plain'], $socket->written);
    }

    public function testSearchOmitsCharsetOnceUtf8AcceptEnabled(): void
    {
        // Server negotiates IMAP4rev2 (UTF-8 mailbox/search implied), so
        // RFC 6855 §3 forbids sending a CHARSET argument.
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev2] Server ready.',
            '* ENABLED IMAP4rev2',
            '* SEARCH',
            'A1 OK SEARCH completed.',
        );
        $client = $this->client($socket);
        // Prime the enabled state the way login() would.
        $client->getCapability()->enable('IMAP4REV2');

        $query = (new ImapSearchQuery())->charset('UTF-8')->text('plain');
        $client->search('INBOX', $query);

        self::assertSame(['A1 UID SEARCH BODY plain'], $socket->written);
    }

    public function testSearchRejectsNonSearchQuery(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1] Server ready.');
        $client = $this->client($socket);

        $this->expectException(ImapProtocolException::class);

        $client->search('INBOX', new \stdClass());
    }

    public function testSearchWrapsServerRejection(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            'A1 NO Invalid search criteria.',
        );
        $client = $this->client($socket);

        $this->expectException(ServerResponseException::class);

        $client->search('INBOX', (new ImapSearchQuery())->flag('\\Seen'));
    }
}
