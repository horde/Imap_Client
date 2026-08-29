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

namespace Horde\Imap\Client\Test\Unit\Data\Format;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Horde_Imap_Client_Data_Format_List;
use Horde_Imap_Client_Data_Format_String;
use Horde_Imap_Client_Data_Format_Atom;

/**
 * Tests for the List data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class ListTest extends TestCase
{
    public function testBasicListFunctions()
    {
        $ob = new Horde_Imap_Client_Data_Format_List();

        $this->assertEquals(
            0,
            count($ob)
        );

        $ob->add(new Horde_Imap_Client_Data_Format_Atom('Foo'));
        $ob->add(new Horde_Imap_Client_Data_Format_Atom('Bar'));
        $ob->add(new Horde_Imap_Client_Data_Format_String('Baz'));

        $this->assertEquals(
            3,
            count($ob)
        );

        $this->assertEquals(
            'Foo Bar "Baz"',
            strval($ob)
        );

        $this->assertEquals(
            'Foo Bar "Baz"',
            $ob->escape()
        );

        foreach ($ob as $key => $val) {
            switch ($key) {
                case 0:
                case 1:
                    $this->assertEquals(
                        'Horde_Imap_Client_Data_Format_Atom',
                        get_class($val)
                    );
                    break;

                case 2:
                    $this->assertEquals(
                        'Horde_Imap_Client_Data_Format_String',
                        get_class($val)
                    );
                    break;
            }
        }

    }

    public function testAdvancedListFunctions()
    {
        $ob = new Horde_Imap_Client_Data_Format_List('Foo');

        $this->assertEquals(
            1,
            count($ob)
        );

        $ob_array = iterator_to_array($ob);
        $this->assertEquals(
            'Horde_Imap_Client_Data_Format_Atom',
            get_class(reset($ob_array))
        );

        $ob->add([
            'Foo',
            new Horde_Imap_Client_Data_Format_List(['Bar']),
        ]);

        $this->assertEquals(
            3,
            count($ob)
        );

        $this->assertEquals(
            'Foo Foo (Bar)',
            $ob->escape()
        );

        $ob = new Horde_Imap_Client_Data_Format_List([
            'Foo',
            new Horde_Imap_Client_Data_Format_List([
                'Foo1',
            ]),
            'Bar',
            new Horde_Imap_Client_Data_Format_List([
                new Horde_Imap_Client_Data_Format_String('Bar1'),
                new Horde_Imap_Client_Data_Format_List([
                    'Baz',
                ]),
            ]),
        ]);

        $this->assertEquals(
            4,
            count($ob)
        );

        $this->assertEquals(
            'Foo (Foo1) Bar ("Bar1" (Baz))',
            $ob->escape()
        );
    }

}
