<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Data\Format;

use PHPUnit\Framework\TestCase;
use Horde_Imap_Client_Data_Format_Atom;

/**
 * Base test provider for data format objects.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
abstract class TestBase extends TestCase
{
    abstract protected static function getTestObs(): array;

    protected static function createProviderArray(array $data): array
    {
        $data = array_values($data);
        $out = [];

        foreach (array_values(static::getTestObs()) as $key => $val) {
            $out[] = array_merge(
                [$val],
                isset($data[$key]) ? (is_array($data[$key]) ? $data[$key] : [$data[$key]]) : []
            );
        }

        return $out;
    }

    public static function obsProvider(): array
    {
        return static::createProviderArray([]);
    }

}
