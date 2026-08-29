<?php

declare(strict_types=1);

/**
 * Copyright 2015-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Stub;

use Horde_Imap_Client_Auth_Scram;

/**
 * Stub for testing SCRAM authentication.
 * Needed because we need to overwrite a protected property.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2015-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Scram extends Horde_Imap_Client_Auth_Scram
{
    public function setNonce($nonce)
    {
        $this->_nonce = $nonce;
    }
}
