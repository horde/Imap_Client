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
use Horde_Imap_Client_Data_Capability;
use Horde_Imap_Client_Exception;
use Horde_Imap_Client_Socket;

/**
 * Integration tests for IMAP socket connection lifecycle.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class ImapConnectionTest extends TestCase
{
    use ConfigHelper;

    private static ?array $clientConfig = null;
    private static ?Horde_Imap_Client_Socket $live = null;

    public static function setUpBeforeClass(): void
    {
        $c = self::getConfig('IMAPCLIENT_TEST_CONFIG', dirname(__DIR__));

        if (is_null($c) || empty($c['imapclient'])) {
            self::markTestSkipped('No IMAP configuration available.');
            return;
        }

        foreach ($c['imapclient'] as $val) {
            if (!empty($val['enabled']) && !empty($val['client_config']['username'])) {
                self::$clientConfig = $val['client_config'];
                break;
            }
        }

        if (empty(self::$clientConfig)) {
            self::markTestSkipped('No enabled IMAP server configuration found.');
            return;
        }

        self::$live = new Horde_Imap_Client_Socket(self::$clientConfig);
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

    public function testConnectReturnsCapability(): void
    {
        $this->assertInstanceOf(
            Horde_Imap_Client_Data_Capability::class,
            self::$live->capability
        );
    }

    #[Depends('testConnectReturnsCapability')]
    public function testLogin(): void
    {
        self::$live->login();
        $this->assertTrue(true);
    }

    #[Depends('testLogin')]
    public function testCapabilityAfterLoginIncludesImap4Rev1(): void
    {
        $this->assertTrue(
            self::$live->capability->query('IMAP4REV1')
        );
    }

    #[Depends('testLogin')]
    public function testNoop(): void
    {
        self::$live->noop();
        $this->assertTrue(true);
    }

    #[Depends('testLogin')]
    public function testUrlPropertyMatchesConfig(): void
    {
        $url = self::$live->url;
        $this->assertEquals(
            self::$clientConfig['hostspec'],
            $url->hostspec
        );
    }

    #[Depends('testLogin')]
    public function testLogout(): void
    {
        self::$live->logout();
        $this->assertTrue(true);
    }

    #[Depends('testLogout')]
    public function testReconnectAfterLogout(): void
    {
        self::$live->login();
        $this->assertTrue(true);
    }

    public function testInvalidCredentialsFails(): void
    {
        if (empty(self::$clientConfig)) {
            $this->markTestSkipped('No IMAP configuration available.');
        }

        $config = self::$clientConfig;
        $config['password'] = 'intentionally-wrong-password-' . mt_rand();

        $bad = new Horde_Imap_Client_Socket($config);

        $this->expectException(Horde_Imap_Client_Exception::class);
        $bad->login();
    }
}
