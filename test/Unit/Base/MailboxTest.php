<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2014-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Base;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Horde_Imap_Client_Base_Mailbox;
use Horde_Imap_Client;

/**
 * Tests for the Horde_Imap_Client_Base_Mailbox object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class MailboxTest extends TestCase
{
    private $ob;

    public function setUp(): void
    {
        $this->ob = new Horde_Imap_Client_Base_Mailbox();
    }

    public function testInitialStatus()
    {
        $this->assertInstanceOf(
            'Horde_Imap_Client_Ids_Map',
            $this->ob->map
        );
    }

    #[DataProvider('basicIntegerStatusPropertiesProvider')]
    public function testBasicIntegerStatusProperties($property)
    {
        $this->assertNull(
            $this->ob->getStatus($property)
        );

        $this->ob->setStatus($property, 1);

        $this->assertSame(
            1,
            $this->ob->getStatus($property)
        );

        $this->ob->setStatus($property, "1");

        $this->assertSame(
            1,
            $this->ob->getStatus($property)
        );
    }

    public static function basicIntegerStatusPropertiesProvider()
    {
        return [
            [Horde_Imap_Client::STATUS_HIGHESTMODSEQ],
            [Horde_Imap_Client::STATUS_MESSAGES],
            [Horde_Imap_Client::STATUS_UIDNEXT],
            [Horde_Imap_Client::STATUS_UIDVALIDITY],
        ];
    }

    #[DataProvider('defaultSyncPropertiesProvider')]
    public function testDefaultSyncProperties($property)
    {
        $this->assertIsArray($this->ob->getStatus($property));
        $this->assertEmpty($this->ob->getStatus($property));
    }

    public static function defaultSyncPropertiesProvider()
    {
        return [
            [Horde_Imap_Client::STATUS_SYNCFLAGUIDS],
            [Horde_Imap_Client::STATUS_SYNCVANISHED],
        ];
    }

    public function testFirstUnseen()
    {
        $this->assertFalse(
            $this->ob->getStatus(Horde_Imap_Client::STATUS_FIRSTUNSEEN)
        );

        $this->ob->setStatus(Horde_Imap_Client::STATUS_MESSAGES, 1);

        $this->assertNull(
            $this->ob->getStatus(Horde_Imap_Client::STATUS_FIRSTUNSEEN)
        );

        $this->ob->setStatus(Horde_Imap_Client::STATUS_FIRSTUNSEEN, 1);

        $this->assertSame(
            1,
            $this->ob->getStatus(Horde_Imap_Client::STATUS_FIRSTUNSEEN)
        );

        $this->ob->setStatus(Horde_Imap_Client::STATUS_FIRSTUNSEEN, "1");

        $this->assertSame(
            1,
            $this->ob->getStatus(Horde_Imap_Client::STATUS_FIRSTUNSEEN)
        );
    }

    public function testDefaultPermFlags()
    {
        $this->assertTrue(
            in_array('\\*', $this->ob->getStatus(Horde_Imap_Client::STATUS_PERMFLAGS))
        );
    }

    public function testUnseen()
    {
        $this->assertEquals(
            0,
            $this->ob->getStatus(Horde_Imap_Client::STATUS_UNSEEN)
        );

        $this->ob->setStatus(Horde_Imap_Client::STATUS_MESSAGES, 1);

        $this->assertNull(
            $this->ob->getStatus(Horde_Imap_Client::STATUS_FIRSTUNSEEN)
        );

        $this->ob->setStatus(Horde_Imap_Client::STATUS_UNSEEN, 1);

        $this->assertSame(
            1,
            $this->ob->getStatus(Horde_Imap_Client::STATUS_UNSEEN)
        );

        $this->ob->setStatus(Horde_Imap_Client::STATUS_UNSEEN, "1");

        $this->assertSame(
            1,
            $this->ob->getStatus(Horde_Imap_Client::STATUS_UNSEEN)
        );
    }

    public function testStatusRecent()
    {
        $this->ob->setStatus(Horde_Imap_Client::STATUS_RECENT, 1);
        $this->ob->setStatus(Horde_Imap_Client::STATUS_RECENT, 1);
        $this->ob->setStatus(Horde_Imap_Client::STATUS_RECENT, 1);

        $this->assertEquals(
            3,
            $this->ob->getStatus(Horde_Imap_Client::STATUS_RECENT_TOTAL)
        );

        $this->ob->setStatus(Horde_Imap_Client::STATUS_RECENT, "1");

        $this->assertEquals(
            4,
            $this->ob->getStatus(Horde_Imap_Client::STATUS_RECENT_TOTAL)
        );
    }

    public function testSyncModseqIsOnlySetOnce()
    {
        $this->ob->setStatus(Horde_Imap_Client::STATUS_SYNCMODSEQ, "1");
        $this->ob->setStatus(Horde_Imap_Client::STATUS_SYNCMODSEQ, 2);

        $this->assertSame(
            1,
            $this->ob->getStatus(Horde_Imap_Client::STATUS_SYNCMODSEQ)
        );
    }

    #[DataProvider('statusEntriesAreAdditiveProvider')]
    public function testStatusEntriesAreAdditive($val)
    {
        $this->ob->setStatus($val, [1]);
        $this->ob->setStatus($val, [2]);

        $this->assertEquals(
            [1, 2],
            $this->ob->getStatus($val)
        );
    }

    public static function statusEntriesAreAdditiveProvider()
    {
        return [
            [Horde_Imap_Client::STATUS_SYNCFLAGUIDS],
            [Horde_Imap_Client::STATUS_SYNCVANISHED],
        ];
    }

    public function testReset()
    {
        $this->ob->map->update(([1 => 2]));
        $this->ob->setStatus(Horde_Imap_Client::STATUS_SYNCMODSEQ, 1);
        $this->ob->setStatus(Horde_Imap_Client::STATUS_RECENT_TOTAL, 1);

        $this->ob->reset();

        $this->assertEquals(
            0,
            count($this->ob->map)
        );
        $this->assertEquals(
            1,
            $this->ob->getStatus(Horde_Imap_Client::STATUS_SYNCMODSEQ)
        );
        $this->assertEquals(
            0,
            $this->ob->getStatus(Horde_Imap_Client::STATUS_RECENT_TOTAL)
        );
    }
}
