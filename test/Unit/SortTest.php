<?php

declare(strict_types=1);

/**
 * Copyright 2012-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2012-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Horde_Imap_Client_Mailbox_List;

/**
 * Tests for IMAP mailbox sorting.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2012-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class SortTest extends TestCase
{
    #[DataProvider('numericComponentSortingProvider')]
    public function testNumericComponentSorting($mboxes, $expected)
    {
        $list_ob = new Horde_Imap_Client_Mailbox_List($mboxes);
        $list_ob->sort([
            'delimiter' => '.',
        ]);

        $this->assertEquals(
            $expected,
            array_values(iterator_to_array($list_ob))
        );
    }

    public static function numericComponentSortingProvider()
    {
        return [
            [
                [
                    '100',
                    '1',
                    '10000',
                    '10',
                    '1000',
                ],
                [
                    '1',
                    '10',
                    '100',
                    '1000',
                    '10000',
                ],
            ],
            [
                [
                    'Foo.002',
                    'Foo.00002',
                    'Foo.0002',
                ],
                [
                    'Foo.002',
                    'Foo.0002',
                    'Foo.00002',
                ],
            ],
        ];
    }

    #[DataProvider('inboxSortProvider')]
    public function testInboxSort($mboxes, $expected)
    {
        $list_ob = new Horde_Imap_Client_Mailbox_List($mboxes);
        $sorted = $list_ob->sort([
            'inbox' => true,
        ]);

        $this->assertEquals(
            $expected,
            array_values($sorted)
        );

        $list_ob = new Horde_Imap_Client_Mailbox_List($mboxes);
        $sorted = $list_ob->sort([
            'inbox' => false,
        ]);

        $this->assertEquals(
            $mboxes,
            $sorted
        );
    }

    public static function inboxSortProvider()
    {
        return [
            [
                [
                    'A',
                    'Z',
                    'INBOX',
                    'C',
                ],
                [
                    'INBOX',
                    'A',
                    'C',
                    'Z',
                ],
            ],
        ];
    }

    #[DataProvider('indexAssociationProvider')]
    public function testIndexAssociation($mboxes, $expected)
    {
        $list_ob = new Horde_Imap_Client_Mailbox_List($mboxes);
        $sorted = $list_ob->sort();

        $this->assertEquals(
            $expected,
            array_values($sorted)
        );

        $this->assertEquals(
            $expected,
            array_keys($sorted)
        );
    }

    public static function indexAssociationProvider()
    {
        return [
            [
                [
                    'Z' => 'Z',
                    'A' => 'A',
                ],
                [
                    'A',
                    'Z',
                ],
            ],
        ];
    }

    #[DataProvider('noUpdateOfListObjectProvider')]
    public function testNoUpdateOfListObject($mboxes, $expected)
    {
        $list_ob = new Horde_Imap_Client_Mailbox_List($mboxes);
        $sorted = $list_ob->sort([
            'noupdate' => true,
        ]);

        $this->assertEquals(
            $expected,
            array_values($sorted)
        );
        $this->assertEquals(
            $mboxes,
            array_values(iterator_to_array($list_ob))
        );

        $list_ob = new Horde_Imap_Client_Mailbox_List($mboxes);
        $sorted = $list_ob->sort();

        $this->assertEquals(
            $expected,
            array_values($sorted)
        );
        $this->assertEquals(
            $expected,
            array_values(iterator_to_array($list_ob))
        );
    }

    public static function noUpdateOfListObjectProvider()
    {
        return [
            [
                [
                    'Z',
                    'A',
                ],
                [
                    'A',
                    'Z',
                ],
            ],
        ];
    }

}
