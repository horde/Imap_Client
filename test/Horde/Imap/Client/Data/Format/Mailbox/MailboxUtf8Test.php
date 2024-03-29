<?php
/**
 * Copyright 2014-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
namespace Horde\Imap\Client\Data\Format\Mailbox;

/**
 * Tests for the UTF-8 Mailbox data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
class MailboxUtf8Test extends TestBase
{
    protected $cname = 'Horde_Imap_Client_Data_Format_Mailbox_Utf8';

    public function stringRepresentationProvider()
    {
        return array(
            array('Foo', 'Foo'),
            array('Foo(', 'Foo('),
            array('Foo%Bar', 'Foo%Bar'),
            array('Foo*Bar', 'Foo*Bar'),
            array('Envoyé', 'Envoyé')
        );
    }

    public function escapeProvider()
    {
        return array(
            array('Foo', 'Foo'),
            array('Foo(', '"Foo("'),
            array('Foo%Bar', '"Foo%Bar"'),
            array('Foo*Bar', '"Foo*Bar"'),
            array('Envoyé', '"Envoyé"')
        );
    }

    public function verifyProvider()
    {
        return array(
            array('Foo', false),
            array('Foo(', false),
            array('Foo%Bar', false),
            array('Foo*Bar', false),
            array('Envoyé', false)
        );
    }

    public function binaryProvider()
    {
        return array(
            array('Foo', false),
            array('Foo(', false),
            array('Foo%Bar', false),
            array('Foo*Bar', false),
            array('Envoyé', false)
        );
    }

    public function literalProvider()
    {
        return array(
            array('Foo', false),
            array('Foo(', false),
            array('Foo%Bar', false),
            array('Foo*Bar', false),
            array('Envoyé', false)
        );
    }

    public function quotedProvider()
    {
        return array(
            array('Foo', false),
            array('Foo(', true),
            array('Foo%Bar', true),
            array('Foo*Bar', true),
            array('Envoyé', true)
        );
    }

    public function escapeStreamProvider()
    {
        return array(
            array('Foo', '"Foo"'),
            array('Foo(', '"Foo("'),
            array('Foo%Bar', '"Foo%Bar"'),
            array('Foo*Bar', '"Foo*Bar"'),
            array('Envoyé', '"Envoyé"')
        );
    }

    public function testBadInput()
    {
        $this->expectException('Horde_Imap_Client_Data_Format_Exception');

        new $this->cname("foo\1");
    }

}
