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
use Horde_Imap_Client_Ids;
use stdClass;

/**
 * Tests for the Ids object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class IdsTest extends TestCase
{
    public function testBasicAddingOfIds()
    {
        $ids = new Horde_Imap_Client_Ids([1, 3, 5]);

        $this->assertEquals(
            3,
            count($ids)
        );

        $this->assertEquals(
            '1,3,5',
            strval($ids)
        );

        $this->assertFalse($ids->isEmpty());
    }

    #[DataProvider('ignoreInvalidAddsProvider')]
    public function testIgnoreInvalidAdds($in)
    {
        $ids = new Horde_Imap_Client_Ids($in);
        $this->assertEquals(
            0,
            count($ids)
        );

        $ids = new Horde_Imap_Client_Ids();
        $ids->add($in);
        $this->assertEquals(
            0,
            count($ids)
        );
    }

    public static function ignoreInvalidAddsProvider()
    {
        return [
            [null],
            [new stdClass()],
        ];
    }

    public function testEmptyIdsArray()
    {
        $ids = new Horde_Imap_Client_Ids([]);

        $this->assertEquals(
            0,
            count($ids)
        );

        $this->assertEquals(
            '',
            strval($ids)
        );

        $this->assertTrue($ids->isEmpty());
    }

    #[DataProvider('sequenceParsingProvider')]
    public function testSequenceParsing($in, $expected)
    {
        $ids = new Horde_Imap_Client_Ids($in);

        $this->assertEquals(
            count($expected),
            count($ids)
        );

        $this->assertEquals(
            $expected,
            iterator_to_array($ids)
        );
    }

    public static function sequenceParsingProvider()
    {
        return [
            ['12:10', [10, 11, 12]],
            ['12,11,10', [12, 11, 10]],
            ['10:12,10,11,12,10:12', [10, 11, 12]],
            ['10', [10]],
        ];
    }

    #[DataProvider('rangeGenerationProvider')]
    public function testRangeGeneration($in, $expected)
    {
        $ids = new Horde_Imap_Client_Ids($in);

        $this->assertEquals(
            $expected,
            $ids->range_string
        );
    }

    public static function rangeGenerationProvider()
    {
        return [
            ['100,300,200', '100:300'],
            [Horde_Imap_Client_Ids::ALL, ''],
            ['50', '50'],
        ];
    }

    public function testSorting()
    {
        $ids = new Horde_Imap_Client_Ids('14,12,10');

        $this->assertEquals(
            '14,12,10',
            $ids->tostring
        );
        $this->assertEquals(
            '10,12,14',
            $ids->tostring_sort
        );
    }

    #[DataProvider('specialIdValueStringRepresentationsProvider')]
    public function testSpecialIdValueStringRepresentations($in, $expected)
    {
        $ids = new Horde_Imap_Client_Ids($in);

        $this->assertEquals(
            $expected,
            $ids->tostring
        );
    }

    public static function specialIdValueStringRepresentationsProvider()
    {
        return [
            [Horde_Imap_Client_Ids::ALL, '1:*'],
            [Horde_Imap_Client_Ids::SEARCH_RES, '$'],
            [Horde_Imap_Client_Ids::LARGEST, '*'],
        ];
    }

    public function testDuplicatesAllowed()
    {
        $ids = new Horde_Imap_Client_Ids('1:10,1:10');
        $this->assertEquals(
            10,
            count($ids)
        );

        $ids = new Horde_Imap_Client_Ids();
        $ids->duplicates = true;
        $ids->add('1:10,1:10');
        $this->assertEquals(
            20,
            count($ids)
        );
    }

    public function testSplit()
    {
        // ~5000 non-sequential IDs
        $ids = new Horde_Imap_Client_Ids(range(1, 10000, 2));

        $split = $ids->split(2000);

        $this->assertGreaterThan(1, count($split));

        foreach (array_slice($split, 0, -1) as $val) {
            $this->assertGreaterThan(
                2000,
                strlen($val)
            );

            $this->assertNotEquals(
                ',',
                substr($val, -1)
            );
        }

        $last = array_pop($split);
        $this->assertLessThan(
            2001,
            strlen($last)
        );

        $this->assertNotEquals(
            ',',
            substr($last, -1)
        );
    }

    public function testSplitOnAll()
    {
        $ids = new Horde_Imap_Client_Ids(Horde_Imap_Client_Ids::ALL);

        $this->assertEquals(
            [
                '1:*',
            ],
            $ids->split(2000)
        );
    }

    public function testRemove()
    {
        $ids = new Horde_Imap_Client_Ids('1:100');
        $this->assertEquals(
            100,
            count($ids)
        );

        // Remove from array
        $ids->remove(range(1, 10));
        $this->assertEquals(
            90,
            count($ids)
        );

        // Removing same IDs shouldn't change anything.
        $ids->remove(range(1, 10));
        $this->assertEquals(
            90,
            count($ids)
        );

        // Remove via sequence string
        $ids->remove('11:20');
        $this->assertEquals(
            80,
            count($ids)
        );

        // Remove via object
        $ids->remove(new Horde_Imap_Client_Ids('21:30'));
        $this->assertEquals(
            70,
            count($ids)
        );
    }

    #[DataProvider('minAndMaxProvider')]
    public function testMinAndMax($in, $min, $max)
    {
        $ids = new Horde_Imap_Client_Ids($in);

        $this->assertEquals(
            $min,
            $ids->min
        );
        $this->assertEquals(
            $max,
            $ids->max
        );
    }

    public static function minAndMaxProvider()
    {
        return [
            [[1], 1, 1],
            [[1, 2], 1, 2],
            [[1, 5, 3], 1, 5],
        ];
    }

    public function testReverse()
    {
        $ids = new Horde_Imap_Client_Ids([1, 3, 5]);
        $ids->reverse();

        $this->assertEquals(
            [5, 3, 1],
            $ids->ids
        );
    }

    #[DataProvider('sequenceStringGenerationProvider')]
    public function testSequenceStringGeneration($in, $expected)
    {
        $ids = new Horde_Imap_Client_Ids($in);

        $this->assertEquals(
            $expected,
            strval($ids)
        );
    }

    public static function sequenceStringGenerationProvider()
    {
        return [
            [[1, 2, 3], '1:3'],
            [[3, 2, 1], '3,2,1'],
            [[1, 2, 3, 5], '1:3,5'],
        ];
    }

    public function testClone()
    {
        $ids = new Horde_Imap_Client_Ids([1, 3]);

        $ids2 = clone $ids;
        $ids2->add(5);

        $this->assertEquals(
            [1, 3],
            iterator_to_array($ids)
        );
        $this->assertEquals(
            [1, 3, 5],
            iterator_to_array($ids2)
        );
    }

    public function testSerialize()
    {
        $ids = new Horde_Imap_Client_Ids([1, 3, 5]);

        $ids2 = unserialize(serialize($ids));

        $this->assertEquals(
            [1, 3, 5],
            iterator_to_array($ids2)
        );
    }

    public function testForcedIntForRange()
    {
        $ids = new Horde_Imap_Client_Ids('1:3');
        $this->assertEquals(
            [1, 2, 3],
            iterator_to_array($ids)
        );

        foreach (iterator_to_array($ids) as $id) {
            $this->assertIsInt($id);
        }
    }

    public function testForcedIntForSequence()
    {
        $ids = new Horde_Imap_Client_Ids('1,5,7');
        $this->assertEquals(
            [1, 5, 7],
            iterator_to_array($ids)
        );

        foreach (iterator_to_array($ids) as $id) {
            $this->assertIsInt($id);
        }
    }

    public function testAddingWithForcedIntConversion()
    {
        $ids = new Horde_Imap_Client_Ids('1,5,7');
        $ids->add('101:103');

        $this->assertEquals(
            [1, 5, 7, 101, 102, 103],
            iterator_to_array($ids)
        );

        foreach (iterator_to_array($ids) as $id) {
            $this->assertIsInt($id);
        }
    }

}
