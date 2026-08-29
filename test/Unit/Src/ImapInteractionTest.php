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
use Horde\Imap\Client\Exception\ServerResponseException;
use Horde\Imap\Client\ImapCommand;
use Horde\Imap\Client\ImapConnection;
use Horde\Imap\Client\ImapInteraction;
use Horde\Imap\Client\ImapPipeline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapInteraction::class)]
class ImapInteractionTest extends TestCase
{
    public function testSendReturnsResultOnTaggedOk(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('A1 OK LOGIN completed.'),
        );
        $interaction = new ImapInteraction(new ImapConnection($socket));

        $result = $interaction->send('LOGIN', ['admin', 'sw0rdfish']);

        self::assertTrue($result->tagged->isOk());
        self::assertSame([], $result->untagged);
    }

    public function testSendCollectsUntaggedResponsesBeforeTagged(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* 1 EXISTS'),
            InMemoryImapSocket::line('* 0 RECENT'),
            InMemoryImapSocket::line('A1 OK SELECT completed.'),
        );
        $interaction = new ImapInteraction(new ImapConnection($socket));

        $result = $interaction->send('SELECT', ['INBOX']);

        self::assertCount(2, $result->untagged);
        self::assertSame(['1', 'EXISTS'], $result->untagged[0]->data);
        self::assertSame(['0', 'RECENT'], $result->untagged[1]->data);
        self::assertTrue($result->tagged->isOk());
    }

    public function testSendThrowsServerResponseExceptionOnNo(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('A1 NO Mailbox does not exist.'),
        );
        $interaction = new ImapInteraction(new ImapConnection($socket));

        try {
            $interaction->send('SELECT', ['Nonexistent']);
            self::fail('Expected a ServerResponseException.');
        } catch (ServerResponseException $e) {
            self::assertSame('Mailbox does not exist.', $e->getMessage());
            self::assertSame('SELECT', $e->command);
            self::assertSame('NO', $e->status);
        }
    }

    public function testSendThrowsServerResponseExceptionOnBad(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('A1 BAD Command unrecognized.'),
        );
        $interaction = new ImapInteraction(new ImapConnection($socket));

        $this->expectException(ServerResponseException::class);

        $interaction->send('BOGUS');
    }

    public function testEachSendUsesANewTag(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('A1 OK NOOP completed.'),
            InMemoryImapSocket::line('A2 OK NOOP completed.'),
        );
        $interaction = new ImapInteraction(new ImapConnection($socket));

        $interaction->send('NOOP');
        $interaction->send('NOOP');

        self::assertSame(['A1 NOOP', 'A2 NOOP'], $socket->written);
    }

    public function testIgnoresDanglingTaggedResponse(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('STALE OK Leftover from an aborted exchange.'),
            InMemoryImapSocket::line('A1 OK NOOP completed.'),
        );
        $interaction = new ImapInteraction(new ImapConnection($socket));

        $result = $interaction->send('NOOP');

        self::assertTrue($result->tagged->isOk());
        self::assertSame('A1', $result->tagged->tag);
    }

    public function testThrowsOnTaggedResponseForAStillPendingCommand(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('A0 OK Some other in-flight command.'),
        );
        $pipeline = new ImapPipeline();
        $pipeline->enqueue(new ImapCommand('A0', 'OLDCMD'));
        $interaction = new ImapInteraction(new ImapConnection($socket), pipeline: $pipeline);

        $this->expectException(ImapProtocolException::class);

        $interaction->send('NOOP');
    }
}
