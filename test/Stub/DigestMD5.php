<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Stub;

use Horde_Imap_Client_Auth_DigestMD5;

/**
 * Stub for testing the Digest MD5 library.
 * Needed because we need to overwrite a protected method.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class DigestMD5 extends Horde_Imap_Client_Auth_DigestMD5
{
    protected $_cnonce;

    public function __construct(
        $id,
        $pass,
        $challenge,
        $hostname,
        $service,
        $cnonce
    ) {
        $this->_cnonce = $cnonce;
        parent::__construct($id, $pass, $challenge, $hostname, $service);
    }

    protected function _getCnonce()
    {
        return $this->_cnonce;
    }
}
