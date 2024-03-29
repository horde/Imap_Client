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
namespace Horde\Imap\Client\Data\Format;
use PHPUnit\Framework\TestCase;
use \Horde_Imap_Client_Data_Format_Atom;

/**
 * Base test provider for data format objects.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
abstract class TestBase extends TestCase
{
    abstract protected function getTestObs();

    protected function createProviderArray($data)
    {
        $data = array_values($data);
        $out = array();

        foreach (array_values($this->getTestObs()) as $key => $val) {
            $out[] = array_merge(
                array($val),
                isset($data[$key]) ? (is_array($data[$key]) ? $data[$key] : array($data[$key])) : array()
            );
        }

        return $out;
    }

    public function obsProvider()
    {
        return $this->createProviderArray(array());
    }

}
