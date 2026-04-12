<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2013-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

/**
 * Interface for dynamic password generation.
 *
 * Successor of Horde_Imap_Client_Base_Password.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2013-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface PasswordInterface
{
    /**
     * Return the password for the server connection.
     */
    public function getPassword(): string;
}
