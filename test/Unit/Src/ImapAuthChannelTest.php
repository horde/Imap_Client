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

use Horde\Imap\Client\Auth\ImapAuthChannel;
use Horde\Imap\Client\Exception\ImapProtocolException;
use Horde\Imap\Client\ImapConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapAuthChannel::class)]
class ImapAuthChannelTest extends TestCase
{
    public function testSendAuthenticateWithoutInitialResponse(): void
    {
        $socket = InMemoryImapSocket::fromParts('');
        $channel = new ImapAuthChannel(new ImapConnection($socket));

        $channel->sendAuthenticate('CRAM-MD5', null);

        self::assertSame(['A1 AUTHENTICATE CRAM-MD5'], $socket->written);
    }

    public function testSendAuthenticateWithInitialResponseBase64Encodes(): void
    {
        $socket = InMemoryImapSocket::fromParts('');
        $channel = new ImapAuthChannel(new ImapConnection($socket));

        $channel->sendAuthenticate('PLAIN', "\0user\0pass");

        self::assertSame(['A1 AUTHENTICATE PLAIN ' . base64_encode("\0user\0pass")], $socket->written);
    }

    public function testSendAuthenticateWithEmptyInitialResponseUsesEqualsShorthand(): void
    {
        $socket = InMemoryImapSocket::fromParts('');
        $channel = new ImapAuthChannel(new ImapConnection($socket));

        $channel->sendAuthenticate('EXTERNAL', '');

        self::assertSame(['A1 AUTHENTICATE EXTERNAL ='], $socket->written);
    }

    public function testNextEventDecodesContinuationChallenge(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('+ ' . base64_encode('challenge-bytes')),
        );
        $channel = new ImapAuthChannel(new ImapConnection($socket));

        $event = $channel->nextEvent();

        self::assertTrue($event->isChallenge());
        self::assertSame('challenge-bytes', $event->payload());
    }

    public function testNextEventReportsSuccess(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('A1 OK AUTHENTICATE completed.'),
        );
        $channel = new ImapAuthChannel(new ImapConnection($socket));

        $event = $channel->nextEvent();

        self::assertTrue($event->isOutcome());
        self::assertTrue($event->isSuccess());
        self::assertSame('AUTHENTICATE completed.', $event->text());
    }

    public function testNextEventReportsFailureWithoutThrowing(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('A1 NO [AUTHENTICATIONFAILED] Authentication failed.'),
        );
        $channel = new ImapAuthChannel(new ImapConnection($socket));

        $event = $channel->nextEvent();

        self::assertTrue($event->isOutcome());
        self::assertFalse($event->isSuccess());
        self::assertSame('AUTHENTICATIONFAILED', $event->responseCode());
        self::assertSame('Authentication failed.', $event->text());
    }

    public function testNextEventReportsBadWithoutThrowing(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('A1 BAD Invalid SASL response.'),
        );
        $channel = new ImapAuthChannel(new ImapConnection($socket));

        $event = $channel->nextEvent();

        self::assertTrue($event->isOutcome());
        self::assertFalse($event->isSuccess());
    }

    public function testNextEventSkipsUnsolicitedUntaggedResponses(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* OK [ALERT] System going down soon.'),
            InMemoryImapSocket::line('+ ' . base64_encode('challenge-bytes')),
        );
        $channel = new ImapAuthChannel(new ImapConnection($socket));

        $event = $channel->nextEvent();

        self::assertTrue($event->isChallenge());
        self::assertSame('challenge-bytes', $event->payload());
    }

    public function testNextEventThrowsOnMalformedBase64(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('+ not-valid-base64!!!'),
        );
        $channel = new ImapAuthChannel(new ImapConnection($socket));

        $this->expectException(ImapProtocolException::class);

        $channel->nextEvent();
    }

    public function testSendResponseBase64EncodesPayload(): void
    {
        $socket = InMemoryImapSocket::fromParts('');
        $channel = new ImapAuthChannel(new ImapConnection($socket));

        $channel->sendResponse('response-bytes');

        self::assertSame([base64_encode('response-bytes')], $socket->written);
    }

    public function testSendResponseWithEmptyPayloadSendsBlankLine(): void
    {
        $socket = InMemoryImapSocket::fromParts('');
        $channel = new ImapAuthChannel(new ImapConnection($socket));

        $channel->sendResponse('');

        self::assertSame([''], $socket->written);
    }

    public function testCancelSendsAsterisk(): void
    {
        $socket = InMemoryImapSocket::fromParts('');
        $channel = new ImapAuthChannel(new ImapConnection($socket));

        $channel->cancel();

        self::assertSame(['*'], $socket->written);
    }
}
