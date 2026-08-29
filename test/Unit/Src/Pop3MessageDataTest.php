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

use DateTimeImmutable;
use Horde\Imap\Client\Pop3MessageData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pop3MessageData::class)]
class Pop3MessageDataTest extends TestCase
{
    public function testUidAndSeqAreConstructorSupplied(): void
    {
        $data = new Pop3MessageData('uidl-1', 3);

        self::assertSame('uidl-1', $data->getUid());
        self::assertSame(3, $data->getSeq());
    }

    public function testFlagsAreAlwaysEmpty(): void
    {
        $data = new Pop3MessageData('uidl-1');

        self::assertSame([], $data->getFlags());
    }

    public function testModSeqIsAlwaysNull(): void
    {
        $data = new Pop3MessageData('uidl-1');

        self::assertNull($data->getModSeq());
    }

    public function testSizeDefaultsToZeroAndIsSettable(): void
    {
        $data = new Pop3MessageData('uidl-1');

        self::assertSame(0, $data->getSize());

        $data->setSize(1234);

        self::assertSame(1234, $data->getSize());
    }

    public function testImapDateDefaultsToEpochAndIsSettable(): void
    {
        $data = new Pop3MessageData('uidl-1');

        self::assertSame(0, $data->getImapDate()->getTimestamp());

        $date = new DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $data->setImapDate($date);

        self::assertSame($date, $data->getImapDate());
    }

    public function testFullMsgRoundTrips(): void
    {
        $data = new Pop3MessageData('uidl-1');
        $data->setFullMsg("Subject: test\r\n\r\nBody text");

        self::assertSame("Subject: test\r\n\r\nBody text", (string) $data->getFullMsg());
    }

    public function testFullMsgDefaultsToEmptyStream(): void
    {
        $data = new Pop3MessageData('uidl-1');

        self::assertSame('', (string) $data->getFullMsg());
    }

    public function testHeaderTextIsKeyedById(): void
    {
        $data = new Pop3MessageData('uidl-1');
        $data->setHeaderText(0, 'Subject: test');

        self::assertSame('Subject: test', (string) $data->getHeaderText());
        self::assertSame('', (string) $data->getHeaderText(1));
    }

    public function testBodyTextIsKeyedById(): void
    {
        $data = new Pop3MessageData('uidl-1');
        $data->setBodyText(0, 'Body text');

        self::assertSame('Body text', (string) $data->getBodyText());
        self::assertSame('', (string) $data->getBodyText(1));
    }
}
