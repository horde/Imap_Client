<?php

/**
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */

namespace Horde\Imap\Client;

use PHPUnit\Framework\TestCase;
use Horde_Imap_Client_Ids_Map;
use Horde_Imap_Client_Ids;

/**
 * Tests for the UID -> Sequence Number mapping object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 * @coversNothing
 */
class MapTest extends TestCase
{
    private $lookup;
    private $map;

    public function setUp(): void
    {
        $this->lookup = [
            2 => 5,
            4 => 10,
            6 => 15,
            8 => 20,
            10 => 25,
            12 => 30,
        ];

        $this->map = new Horde_Imap_Client_Ids_Map($this->lookup);
    }

    public function testUpdate()
    {
        $map = new Horde_Imap_Client_Ids_Map();
        $map->update([
            1 => 1,
        ]);

        $this->assertEquals(
            [
                1 => 1,
            ],
            $map->map
        );

        $map->update([
            2 => 2,
        ]);

        $this->assertEquals(
            [
                1 => 1,
                2 => 2,
            ],
            $map->map
        );

        $map->update([
            1 => 3,
        ]);

        $this->assertEquals(
            [
                2 => 2,
                1 => 3,
            ],
            $map->map
        );

        $map->update([
            2 => 4,
            1 => 5,
        ]);

        $this->assertEquals(
            [
                2 => 4,
                1 => 5,
            ],
            $map->map
        );
    }

    public function testCount()
    {
        $this->assertEquals(
            6,
            count($this->map)
        );
    }

    /**
     * @depends testCount
     */
    public function testClone()
    {
        $map2 = clone $this->map;
        $map2->update([
            1 => 1,
        ]);

        $this->assertEquals(
            6,
            count($this->map)
        );
        $this->assertEquals(
            7,
            count($map2)
        );
    }

    /**
     * @dataProvider lookupProvider
     * @depends testClone
     */
    public function testLookup($range, $expected = null)
    {
        $map = clone $this->map;

        $this->assertEquals(
            $expected ?: $map->map,
            $map->lookup($range)
        );
    }

    public function lookupProvider()
    {
        return [
            [
                new Horde_Imap_Client_Ids('5:15'),
                [
                    2 => 5,
                    4 => 10,
                    6 => 15,
                ],
            ],
            [
                new Horde_Imap_Client_Ids('2:6', true),
                [
                    2 => 5,
                    4 => 10,
                    6 => 15,
                ],
            ],
            [
                new Horde_Imap_Client_Ids(Horde_Imap_Client_Ids::ALL),
            ],
        ];
    }

    /**
     * @dataProvider removeProvider
     * @depends testClone
     */
    public function testRemove($range, $expected)
    {
        $map = clone $this->map;
        $map->remove($range);

        $this->assertEquals(
            $expected,
            $map->map
        );
    }

    public function removeProvider()
    {
        return [
            [
                new Horde_Imap_Client_Ids('10'),
                [
                    2 => 5,
                    5 => 15,
                    7 => 20,
                    9 => 25,
                    11 => 30,
                ],
            ],
            [
                new Horde_Imap_Client_Ids('4', true),
                [
                    2 => 5,
                    5 => 15,
                    7 => 20,
                    9 => 25,
                    11 => 30,
                ],
            ],
            [
                new Horde_Imap_Client_Ids('10:15,25'),
                [
                    2 => 5,
                    6 => 20,
                    9 => 30,
                ],
            ],
            // Efficient sequence number remove.
            [
                new Horde_Imap_Client_Ids(['10', '6', '4'], true),
                [
                    2 => 5,
                    6 => 20,
                    9 => 30,
                ],
            ],
            // Inefficient sequence number remove.
            [
                new Horde_Imap_Client_Ids(['4', '5', '8'], true),
                [
                    2 => 5,
                    6 => 20,
                    9 => 30,
                ],
            ],
            // Shortcut removing all.
            [
                new Horde_Imap_Client_Ids('5:30'),
                [],
            ],
            [
                new Horde_Imap_Client_Ids(['5', '10', '15', '20', '25', '30']),
                [],
            ],
            [
                new Horde_Imap_Client_Ids(['2', '4', '6', '8', '10', '12'], true),
                [],
            ],
            [
                new Horde_Imap_Client_Ids(['12', '10', '8', '6', '4', '2'], true),
                [],
            ],
        ];
    }

    public function testRemoveWithDuplicateSequenceNumbers()
    {
        $map = new Horde_Imap_Client_Ids_Map([
            1 => 1,
            2 => 2,
            3 => 3,
        ]);

        // Inefficient sequence number remove with duplicate sequence numbers.
        $ids = new Horde_Imap_Client_Ids([], true);
        $ids->duplicates = true;
        $ids->add(['2', '2']);

        $map->remove($ids);

        $this->assertEquals(
            [
                1 => 1,
            ],
            $map->map
        );
    }

    public function testIterator()
    {
        $this->assertEquals(
            $this->map->map,
            iterator_to_array($this->map)
        );
    }

    public function testSerialize()
    {
        $map = unserialize(serialize($this->map));

        $this->assertEquals(
            $this->map->map,
            $map->map
        );
    }

}
