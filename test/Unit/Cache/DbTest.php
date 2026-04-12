<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2013-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Cache;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use Horde_Db_Adapter_Pdo_Sqlite;
use Horde_Db_Migration_Migrator;
use Horde_Imap_Client_Cache_Backend_Db;

/**
 * Tests for the Db cache driver.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2013-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class DbTest extends TestBase
{
    protected function _getBackend()
    {
        if (!extension_loaded('pdo')
            || !in_array('sqlite', PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite not available.');
        }

        $db = new Horde_Db_Adapter_Pdo_Sqlite([
            'dbname' => ':memory:',
            'charset' => 'utf-8',
        ]);

        $migrator = new Horde_Db_Migration_Migrator($db, null, [
            'migrationsPath' => dirname(__DIR__, 3) . '/migration/Horde/Imap/Client',
        ]);
        $migrator->up();

        return new Horde_Imap_Client_Cache_Backend_Db([
            'db' => $db,
            'hostspec' => self::HOSTSPEC,
            'port' => self::PORT,
            'username' => self::USERNAME,
        ]);
    }
}
