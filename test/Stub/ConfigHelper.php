<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Stub;

/**
 * Helper trait for loading test configuration.
 *
 * Replaces the Horde_Test_Case::getConfig() method with a standalone
 * implementation that works with PHPUnit\Framework\TestCase.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
trait ConfigHelper
{
    protected static function getConfig(string $env, string $path): ?array
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
}
