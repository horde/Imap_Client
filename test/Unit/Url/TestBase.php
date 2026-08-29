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

namespace Horde\Imap\Client\Test\Unit\Url;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Base class for URL tests.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
abstract class TestBase extends TestCase
{
    protected $classname;

    #[DataProvider('urlProvider')]
    public function testUrlParsing($in, $url, $expected)
    {
        $ob = new $this->classname($in);

        foreach ($expected as $key => $val) {
            $this->assertEquals(
                $val,
                $ob->$key
            );
        }

        $this->assertEquals(
            is_null($url) ? $in : $url,
            strval($ob)
        );
    }

    abstract public static function urlProvider();

    #[DataProvider('serializeProvider')]
    public function testSerialize($url)
    {
        $orig = new $this->classname($url);
        $copy = unserialize(serialize($orig));

        $this->assertEquals(
            $orig,
            $copy
        );
    }

    abstract public static function serializeProvider();
}
