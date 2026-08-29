<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Integration;

use Exception;
use Horde_Cache;
use Horde_Cache_Storage_Mock;
use Horde_Imap_Client;
use Horde_Imap_Client_Ids;
use Horde_Imap_Client_Search_Query;
use Horde_Imap_Client_Socket_Pop3;
use PHPUnit\Framework\Attributes\Depends;

/**
 * Package testing on a (live) POP3 server.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2013-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Pop3 extends Base
{
    public static $config;

    public static function setUpBeforeClass(): void
    {
        $c = array_shift(self::$config);

        try {
            $c['client_config']['cache'] = [
                'cacheob' => new Horde_Cache(
                    new Horde_Cache_Storage_Mock(),
                    ['compress' => true]
                ),
            ];
        } catch (Exception $e) {
        }

        self::$live = new Horde_Imap_Client_Socket_Pop3(
            $c['client_config']
        );
    }

    /* Tests */

    public function testPreLoginCommands()
    {
        $c = self::$live->capability;

        $this->assertInstanceOf(
            'Horde_Imap_Client_Data_Capability',
            $c
        );
    }

    #[Depends('testPreLoginCommands')]
    public function testLogin()
    {
        /* Throws exception on error, which will prevent all further testing
         * on this server. */
        self::$live->login();
    }

    #[Depends('testLogin')]
    public function testPostLoginCapability()
    {
        /* Re-use testPreLoginCommands(). */
        $this->testPreLoginCommands();
    }

    #[Depends('testLogin')]
    public function testOpenMailbox()
    {
        self::$live->openMailbox('INBOX', Horde_Imap_Client::OPEN_READONLY);
        self::$live->openMailbox('INBOX', Horde_Imap_Client::OPEN_READWRITE);
        self::$live->openMailbox('INBOX', Horde_Imap_Client::OPEN_AUTO);
    }

    #[Depends('testLogin')]
    public function testListMailbox()
    {
        // Listing all mailboxes (flat format).
        $l = self::$live->listMailboxes(
            '*',
            Horde_Imap_Client::MBOX_ALL,
            ['flat' => true]
        );

        $this->assertEquals(1, count($l));
    }

    #[Depends('testLogin')]
    public function testStatus()
    {
        $s = self::$live->status('INBOX', Horde_Imap_Client::STATUS_ALL);

        $this->assertIsArray($s);

        $this->assertArrayHasKey('messages', $s);
        $this->assertArrayHasKey('recent', $s);
        $this->assertEquals($s['messages'], $s['recent']);
        $this->assertArrayHasKey('uidnext', $s);
        $this->assertArrayHasKey('uidvalidity', $s);
        $this->assertArrayHasKey('unseen', $s);
        $this->assertEquals(0, $s['unseen']);
    }
}
