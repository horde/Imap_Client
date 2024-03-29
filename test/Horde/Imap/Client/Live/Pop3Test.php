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
namespace Horde\Imap\Client\Live;
use Horde_Test_Case as TestCase;
use \PHPUnit\Framework\TestSuite;

/**
 * Package testing on a real (live) IMAP server.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
class Pop3Test extends TestCase
{
    /**
     * Add the tests to the current test runner.
     */
    public static function suite()
    {
        $suite = new TestSuite;

        $c = self::getConfig('IMAPCLIENT_TEST_CONFIG_POP3', __DIR__ . '/../');
        if (!is_null($c) && !empty($c['pop3client'])) {
            $key = 0;

            foreach ($c['pop3client'] as $val) {
                if (!empty($val['enabled']) &&
                    !empty($val['client_config']['username']) &&
                    !empty($val['client_config']['password'])) {
                    /* Create a temp class for each instance to ensure that
                     * no @depends mixing between servers occurs. */
                    $temp_class = 'Horde_Imap_Client_Live_Pop3_' . ++$key;
                    eval(
                        "class $temp_class extends Horde_Imap_Client_Live_Pop3 {}"
                    );

                    Horde_Imap_Client_Live_Pop3::$config[] = $val;
                    $suite->addTestSuite($temp_class);
                }
            }
        }

        return $suite;
    }

}
