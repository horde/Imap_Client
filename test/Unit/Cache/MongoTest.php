<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2013-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Cache;

use PHPUnit\Framework\Attributes\CoversNothing;
use Horde_Mongo_Client;
use Horde_Imap_Client_Cache_Backend_Mongo;
use Exception;

/**
 * Tests for the Mongo cache driver.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2013-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class MongoTest extends TestBase
{
    private string $_dbname = 'horde_imap_client_cache_mongodbtest';
    private ?Horde_Mongo_Client $_mongo = null;

    private static function getConfig(string $env, string $path): ?array
    {
        $config = getenv($env);
        if ($config !== false) {
            $json = json_decode($config, true);
            if (is_array($json)) {
                return $json;
            }
        }
        $configFile = $path . '/conf.php';
        if (file_exists($configFile)) {
            require $configFile;
            return $conf ?? null;
        }
        return null;
    }

    protected function _getBackend()
    {
        if (!extension_loaded('mongo') && !extension_loaded('mongodb')) {
            $this->markTestSkipped('MongoDB extension not available.');
        }

        $config = self::getConfig('IMAPCLIENT_TEST_CONFIG', dirname(__DIR__, 2));
        if (!$config || !isset($config['mongo'])) {
            $this->markTestSkipped('MongoDB configuration not available.');
        }

        try {
            $this->_mongo = new Horde_Mongo_Client($config['mongo']);
            $this->_mongo->dbname = $this->_dbname;
            $this->_mongo->selectDB(null)->drop();
        } catch (Exception $e) {
            $this->markTestSkipped('MongoDB connection failed: ' . $e->getMessage());
        }

        return new Horde_Imap_Client_Cache_Backend_Mongo([
            'hostspec' => self::HOSTSPEC,
            'mongo_db' => $this->_mongo,
            'port' => self::PORT,
            'username' => self::USERNAME,
        ]);
    }

    public function tearDown(): void
    {
        if (!empty($this->_mongo)) {
            $this->_mongo->selectDB(null)->drop();
        }

        parent::tearDown();
    }
}
