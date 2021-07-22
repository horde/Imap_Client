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
namespace Horde\Imap\Client\Stub;
use Horde_Imap_Client_Socket_ClientSort;
use Horde_Imap_Client_Socket;
use \Collator;

/**
 * Stub for testing the IMAP Socket client sorting library.
 * Needed because we need to fix the locale of the collator for testing
 * consistency.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
class ClientSort extends Horde_Imap_Client_Socket_ClientSort
{
    public function __construct(Horde_Imap_Client_Socket $socket)
    {
        parent::__construct($socket);

        if (class_exists('Collator')) {
            $this->_collator = new Collator('root');
        }
    }
}
