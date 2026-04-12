<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2014-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Namespace;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Horde_Imap_Client_Namespace_List;
use Horde_Imap_Client_Data_Namespace;

/**
 * Tests for the Namespace list object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class ListTest extends TestCase
{
    private $ob;

    public function setUp(): void
    {
        $this->ob = new Horde_Imap_Client_Namespace_List();

        $ob2 = new Horde_Imap_Client_Data_Namespace();
        $ob2->delimiter = '.';
        $ob2->type = $ob2::NS_SHARED;
        $this->ob[''] = $ob2;

        $ob3 = new Horde_Imap_Client_Data_Namespace();
        $ob3->delimiter = '.';
        $ob3->hidden = true;
        $ob3->name = 'foo';
        $this->ob['foo'] = $ob3;
    }

    #[DataProvider('arrayProvider')]
    public function testArrayAccess($name, $exists = true)
    {
        if ($exists) {
            $this->assertTrue(isset($this->ob[$name]));
            $this->assertInstanceof(
                'Horde_Imap_Client_Data_Namespace',
                $this->ob[$name]
            );
        } else {
            $this->assertFalse(isset($this->ob[$name]));
            $this->assertNull($this->ob[$name]);
        }
    }

    public function testCountable()
    {
        $this->assertEquals(
            2,
            count($this->ob)
        );
    }

    public function testIterator()
    {
        foreach ($this->ob as $val) {
            $this->assertInstanceof(
                'Horde_Imap_Client_Data_Namespace',
                $val
            );
        }
    }

    public function testSerialize()
    {
        $ob2 = unserialize(serialize($this->ob));

        $this->assertEquals(
            2,
            count($this->ob)
        );
    }

    #[DataProvider('getNamespaceProvider')]
    public function testGetNamespace($mbox, $personal, $expected)
    {
        if (is_null($expected)) {
            $this->assertNull($this->ob->getNamespace($mbox, $personal));
        } else {
            $this->assertEquals(
                $expected,
                strval($this->ob->getNamespace($mbox, $personal))
            );
        }
    }

    public static function arrayProvider()
    {
        return [
            [''],
            ['foo'],
            ['bar', false],
        ];
    }

    public static function getNamespaceProvider()
    {
        return [
            ['baz', false, ''],
            ['baz', true, null],
            ['foo.bar', false, 'foo'],
            ['foo.bar', true, 'foo'],
            ['baz.bar', false, ''],
            ['baz.bar', true, null],
        ];
    }
}
