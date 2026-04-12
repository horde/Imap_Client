<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Stub;

use Collator;
use Horde_Imap_Client_Socket;
use Horde_Imap_Client_Socket_ClientSort;

/**
 * Stub for testing the IMAP Socket client sorting library.
 * Needed because we need to fix the locale of the collator for testing
 * consistency.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
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
