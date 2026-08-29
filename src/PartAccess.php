<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2011-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

use Generator;
use Horde\Stream\StreamInterface;

/**
 * MIME part access for IMAP.
 *
 * POP3 does not implement this.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2011-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface PartAccess
{
    public function getBodyPart(string $id): StreamInterface;

    public function getMimeHeader(string $id): StreamInterface;

}
