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

use Horde\Imap\Client\Exception\Pop3ProtocolException;
use Horde\Imap\Client\Pop3AuthChannel;
use Horde\Imap\Client\Pop3Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pop3AuthChannel::class)]
class Pop3AuthChannelTest extends TestCase
{
    public function testSendAuthenticateWithoutInitialResponse(): void
    {
        $socket = new InMemoryPop3Socket();
        $channel = new Pop3AuthChannel(new Pop3Connection($socket));

        $channel->sendAuthenticate('CRAM-MD5', null);

        self::assertSame(['AUTH CRAM-MD5'], $socket->written);
    }

    public function testSendAuthenticateWithInitialResponseBase64Encodes(): void
    {
        $socket = new InMemoryPop3Socket();
        $channel = new Pop3AuthChannel(new Pop3Connection($socket));

        $channel->sendAuthenticate('PLAIN', "\0user\0pass");

        self::assertSame(['AUTH PLAIN ' . base64_encode("\0user\0pass")], $socket->written);
    }

    public function testSendAuthenticateWithEmptyInitialResponseUsesEqualsShorthand(): void
    {
        $socket = new InMemoryPop3Socket();
        $channel = new Pop3AuthChannel(new Pop3Connection($socket));

        $channel->sendAuthenticate('EXTERNAL', '');

        self::assertSame(['AUTH EXTERNAL ='], $socket->written);
    }

    public function testNextEventDecodesContinuationChallenge(): void
    {
        $socket = new InMemoryPop3Socket(['+ ' . base64_encode('challenge-bytes')]);
        $channel = new Pop3AuthChannel(new Pop3Connection($socket));

        $event = $channel->nextEvent();

        self::assertTrue($event->isChallenge());
        self::assertSame('challenge-bytes', $event->payload());
    }

    public function testNextEventReportsSuccess(): void
    {
        $socket = new InMemoryPop3Socket(['+OK Maildrop locked and ready']);
        $channel = new Pop3AuthChannel(new Pop3Connection($socket));

        $event = $channel->nextEvent();

        self::assertTrue($event->isOutcome());
        self::assertTrue($event->isSuccess());
        self::assertSame('Maildrop locked and ready', $event->text());
    }

    public function testNextEventReportsFailureWithoutThrowing(): void
    {
        $socket = new InMemoryPop3Socket(['-ERR authentication failed']);
        $channel = new Pop3AuthChannel(new Pop3Connection($socket));

        $event = $channel->nextEvent();

        self::assertTrue($event->isOutcome());
        self::assertFalse($event->isSuccess());
        self::assertSame('authentication failed', $event->text());
    }

    public function testNextEventThrowsOnMalformedBase64(): void
    {
        $socket = new InMemoryPop3Socket(['+ not-valid-base64!!!']);
        $channel = new Pop3AuthChannel(new Pop3Connection($socket));

        $this->expectException(Pop3ProtocolException::class);

        $channel->nextEvent();
    }

    public function testSendResponseBase64EncodesPayload(): void
    {
        $socket = new InMemoryPop3Socket();
        $channel = new Pop3AuthChannel(new Pop3Connection($socket));

        $channel->sendResponse('response-bytes');

        self::assertSame([base64_encode('response-bytes')], $socket->written);
    }

    public function testSendResponseWithEmptyPayloadSendsBlankLine(): void
    {
        $socket = new InMemoryPop3Socket();
        $channel = new Pop3AuthChannel(new Pop3Connection($socket));

        $channel->sendResponse('');

        self::assertSame([''], $socket->written);
    }

    public function testCancelSendsAsterisk(): void
    {
        $socket = new InMemoryPop3Socket();
        $channel = new Pop3AuthChannel(new Pop3Connection($socket));

        $channel->cancel();

        self::assertSame(['*'], $socket->written);
    }
}
