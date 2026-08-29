<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Horde\Imap\Client\Test\Stub\Utf7imap;

/**
 * Tests for UTF7-IMAP <-> UTF-8 conversions.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class Utf7ConvertTest extends TestCase
{
    /**
     * @requires extension mbstring
     */
    #[DataProvider('conversionProvider')]
    public function testConversionWithMbstring($orig, $expected = null)
    {
        Utf7imap::setMbstring(true);

        $this->_testConversion($orig, $expected);
    }

    #[DataProvider('conversionProvider')]
    public function testConversionWithoutMbstring($orig, $expected = null)
    {
        Utf7imap::setMbstring(false);

        $this->_testConversion($orig, $expected);
    }

    protected function _testConversion($orig, $expected)
    {
        $utf7_imap = Utf7imap::Utf8ToUtf7Imap(
            $orig,
            !is_null($expected)
        );

        $this->assertEquals(
            $expected ?: $orig,
            $utf7_imap
        );

        if ($expected) {
            $utf8 = Utf7imap::Utf7ImapToUtf8($utf7_imap);
            $this->assertEquals(
                $orig,
                $utf8
            );
        }
    }

    public static function conversionProvider()
    {
        return [
            ['Envoyé', 'Envoy&AOk-'],
            ['Töst-', 'T&APY-st-'],
            ['&', '&-'],
            ['&-'],
            ['Envoy&AOk-'],
            ['T&APY-st-'],
            // Bug #10133
            ['Entw&APw-rfe'],
            // Bug #10093
            ['Foo&Bar-2011', 'Foo&-Bar-2011'],
        ];
    }

}
