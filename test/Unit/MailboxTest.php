<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2011-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Horde_Imap_Client_Mailbox;
use Horde_Imap_Client_Data_Format_Mailbox;
use Horde_Imap_Client_Data_Format_Mailbox_Utf8;

/**
 * Tests for the mailbox object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class MailboxTest extends TestCase
{
    public function testMailboxClone()
    {
        $ob = new Horde_Imap_Client_Mailbox('Envoyé');

        $ob2 = clone $ob;

        $this->assertEquals(
            'Envoyé',
            $ob2->utf8
        );
    }

    public function testMailboxSerialize()
    {
        $mailbox = unserialize(
            serialize(new Horde_Imap_Client_Mailbox('Envoyé'))
        );

        $this->assertEquals(
            'Envoyé',
            $mailbox->utf8
        );
        $this->assertEquals(
            'Envoy&AOk-',
            $mailbox->utf7imap
        );
    }

    public function testMailboxGet()
    {
        $a = Horde_Imap_Client_Mailbox::get('B');

        $this->assertEquals(
            'B',
            $a->utf7imap
        );

        $mailbox = new Horde_Imap_Client_Mailbox('A');
        $b = Horde_Imap_Client_Mailbox::get($mailbox);

        $this->assertEquals(
            $mailbox,
            $b
        );
    }

    public function testBug10093()
    {
        $orig = 'Foo&Bar-2011';

        $mailbox = new Horde_Imap_Client_Mailbox($orig);

        $this->assertEquals(
            'Foo&Bar-2011',
            $mailbox->utf8
        );
        $this->assertEquals(
            'Foo&-Bar-2011',
            $mailbox->utf7imap
        );
    }

    #[DataProvider('listEscapeProvider')]
    public function testListEscape($orig, $expected)
    {
        $mailbox = new Horde_Imap_Client_Mailbox($orig);

        $this->assertEquals(
            $expected,
            $mailbox->list_escape
        );
    }

    public static function listEscapeProvider()
    {
        return [
            ['***Foo***', '%Foo%'],
            ['IN.***Foo**.Bar.Test**', 'IN.%Foo%.Bar.Test%'],
        ];
    }

    public function testInboxCaseInsensitive()
    {
        $mailbox = new Horde_Imap_Client_Mailbox('inbox');

        $this->assertEquals(
            'INBOX',
            $mailbox
        );
    }

    public function testBug13825()
    {
        $mbox = 'INBOX.!  Astrid"';
        $mbox_escape = '"INBOX.!  Astrid\""';

        $ob = new Horde_Imap_Client_Mailbox($mbox);

        $this->assertEquals(
            $ob,
            Horde_Imap_Client_Mailbox::get($mbox)
        );
        $this->assertEquals(
            $ob,
            Horde_Imap_Client_Mailbox::get($ob)
        );

        $format = new Horde_Imap_Client_Data_Format_Mailbox($ob);
        $this->assertEquals(
            $mbox_escape,
            $format->escape()
        );

        $format2 = new Horde_Imap_Client_Data_Format_Mailbox_Utf8($ob);
        $this->assertEquals(
            $mbox_escape,
            $format2->escape()
        );
    }

}
