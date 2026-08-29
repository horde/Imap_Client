<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Integration;

use Horde\Imap\Client\Test\Stub\ConfigHelper;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Package testing on a real (live) POP3 server.
 *
 * Reads configuration from the IMAPCLIENT_TEST_CONFIG_POP3 environment
 * variable or from test/integration/conf.php. Skips all tests if no
 * configuration is available.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class Pop3Test extends Pop3
{
    use ConfigHelper;

    public static function setUpBeforeClass(): void
    {
        $c = self::getConfig('IMAPCLIENT_TEST_CONFIG_POP3', __DIR__);
        if (is_null($c) || empty($c['pop3client'])) {
            self::markTestSkipped('No POP3 test configuration available.');
            return;
        }

        foreach ($c['pop3client'] as $val) {
            if (!empty($val['enabled'])
                && !empty($val['client_config']['username'])
                && !empty($val['client_config']['password'])) {
                self::$config = [$val];
                break;
            }
        }

        if (empty(self::$config)) {
            self::markTestSkipped('No enabled POP3 server configuration found.');
            return;
        }

        parent::setUpBeforeClass();
    }
}
