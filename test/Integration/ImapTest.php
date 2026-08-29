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
 * Package testing on a real (live) IMAP server.
 *
 * Reads configuration from the IMAPCLIENT_TEST_CONFIG environment variable
 * or from test/integration/conf.php. Skips all tests if no configuration
 * is available.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class ImapTest extends Imap
{
    use ConfigHelper;

    public static function setUpBeforeClass(): void
    {
        $c = self::getConfig('IMAPCLIENT_TEST_CONFIG', __DIR__);
        if (is_null($c) || empty($c['imapclient'])) {
            self::markTestSkipped('No IMAP test configuration available.');
            return;
        }

        foreach ($c['imapclient'] as $val) {
            if (!empty($val['enabled'])
                && !empty($val['client_config']['username'])
                && !empty($val['client_config']['password'])) {
                self::$config = [$val];
                break;
            }
        }

        if (empty(self::$config)) {
            self::markTestSkipped('No enabled IMAP server configuration found.');
            return;
        }

        parent::setUpBeforeClass();
    }
}
