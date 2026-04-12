<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Integration\Connection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Horde\Imap\Client\Test\Stub\ConfigHelper;
use Horde_Imap_Client;
use Horde_Imap_Client_Exception;
use Horde_Imap_Client_Socket_Pop3;

/**
 * Integration tests for POP3 socket connection lifecycle.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class Pop3ConnectionTest extends TestCase
{
    use ConfigHelper;

    private static ?array $clientConfig = null;
    private static ?Horde_Imap_Client_Socket_Pop3 $live = null;

    public static function setUpBeforeClass(): void
    {
        $c = self::getConfig('IMAPCLIENT_TEST_CONFIG', dirname(__DIR__));

        if (is_null($c) || empty($c['pop3client'])) {
            self::markTestSkipped('No POP3 configuration available.');
            return;
        }

        foreach ($c['pop3client'] as $val) {
            if (!empty($val['enabled']) && !empty($val['client_config']['username'])) {
                self::$clientConfig = $val['client_config'];
                break;
            }
        }

        if (empty(self::$clientConfig)) {
            self::markTestSkipped('No enabled POP3 server configuration found.');
            return;
        }

        self::$live = new Horde_Imap_Client_Socket_Pop3(self::$clientConfig);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$live) {
            try {
                self::$live->logout();
            } catch (Horde_Imap_Client_Exception $e) {
            }
        }
        self::$live = null;
        self::$clientConfig = null;
    }

    public function testConnect(): void
    {
        $this->assertNotNull(self::$live->capability);
    }

    #[Depends('testConnect')]
    public function testLogin(): void
    {
        self::$live->login();
        $this->assertTrue(true);
    }

    #[Depends('testLogin')]
    public function testLogout(): void
    {
        self::$live->logout();
        $this->assertTrue(true);
    }

    public function testInvalidCredentialsFails(): void
    {
        if (empty(self::$clientConfig)) {
            $this->markTestSkipped('No POP3 configuration available.');
        }

        $config = self::$clientConfig;
        $config['password'] = 'intentionally-wrong-password-' . mt_rand();

        $bad = new Horde_Imap_Client_Socket_Pop3($config);

        $this->expectException(Horde_Imap_Client_Exception::class);
        $bad->login();
    }

    #[Depends('testLogin')]
    public function testStatusAfterLogin(): void
    {
        // Re-login after logout test may have run
        self::$live = new Horde_Imap_Client_Socket_Pop3(self::$clientConfig);
        self::$live->login();

        $status = self::$live->status(
            'INBOX',
            Horde_Imap_Client::STATUS_MESSAGES
        );

        $this->assertArrayHasKey('messages', $status);
    }
}
