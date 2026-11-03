<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL).
 * @license http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapFetchQuery;
use Horde\Imap\Client\ImapIdSet;
use Horde\Socket\Client\ClientInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validation test for #54
 */
#[CoversClass(ImapClient::class)]
class ImapClientFullMessageTest extends TestCase
{
    public static function fullMessageQueries(): iterable
    {
        yield 'fullMsg only' => [false];
        yield 'fullMsg with structure' => [true];
    }

    #[DataProvider('fullMessageQueries')]
    public function testIssue54FetchesCompleteLargeMessagesAcrossShortReads(bool $withStructure): void
    {
        $attachment = str_repeat(str_repeat('A', 76) . "\r\n", 67216);
        $structure = ' BODYSTRUCTURE (("text" "plain" ("charset" "US-ASCII") '
            . 'NIL NIL "7bit" 5 1) ("application" "octet-stream" '
            . '("name" "large.bin") NIL NIL "base64" ' . strlen($attachment)
            . ' NIL ("attachment" ("filename" "large.bin"))) "mixed" '
            . '("boundary" "issue54"))';
        $messages = [];
        $wire = "* OK [CAPABILITY IMAP4rev1] ready\r\n";
        foreach (range(1, 4) as $uid) {
            $messages[$uid] = "Subject: Issue 54 message $uid\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: multipart/mixed; boundary=issue54\r\n\r\n"
                . "--issue54\r\nContent-Type: text/plain; charset=US-ASCII\r\n\r\n"
                . "hello\r\n--issue54\r\n"
                . "Content-Type: application/octet-stream; name=large.bin\r\n"
                . "Content-Disposition: attachment; filename=large.bin\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . $attachment . "--issue54--\r\n";
            $wire .= "* $uid FETCH (UID $uid BODY[] {" . strlen($messages[$uid])
                . "}\r\n" . $messages[$uid]
                . ($withStructure ? $structure : '')
                . ")\r\nA$uid OK FETCH completed\r\n";
        }

        $script = new InMemoryImapSocket($wire);
        $socket = $this->createMock(ClientInterface::class);
        $socket->method('gets')->willReturnCallback($script->gets(...));
        $socket->method('write')->willReturnCallback($script->write(...));
        $socket->expects(self::never())->method('close');
        $reads = 0;
        $socket->method('read')->willReturnCallback(
            static function (int $size) use ($script, &$reads): string {
                // Model the reporter's 8-12 KiB socket reads, not exact-length reads.
                $limit = (++$reads % 2 === 1) ? 8192 : 12288;
                return $script->read(min($size, $limit));
            },
        );
        $client = new ImapClient(
            new ConnectionConfig(hostspec: 'imap.example.test'),
            client: $socket,
        );
        $query = (new ImapFetchQuery())->fullMsg(null, null, false);
        if ($withStructure) {
            $query->structure();
        }

        foreach ($messages as $uid => $expected) {
            $results = iterator_to_array($client->fetch('INBOX', new ImapIdSet([$uid]), $query));
            self::assertSame([$uid], array_keys($results));
            $actual = $results[$uid]->getFullMsg()->substring();
            self::assertSame(strlen($expected), strlen($actual), "Truncated message UID $uid");
            self::assertSame(hash('sha256', $expected), hash('sha256', $actual));
            if ($withStructure) {
                self::assertSame(
                    'application/octet-stream',
                    iterator_to_array($results[$uid]->getParts())['2']->fullType(),
                );
            }
        }

        self::assertSame(
            array_map(
                static fn(int $uid): string => "A$uid UID FETCH $uid (BODY[]"
                    . ($withStructure ? ' BODYSTRUCTURE' : '') . ')',
                range(1, 4),
            ),
            $script->written,
        );
    }
}
