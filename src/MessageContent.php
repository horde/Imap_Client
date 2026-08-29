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

use Horde\Stream\StreamInterface;

/**
 * Layer 1: Message content access via StreamInterface.
 *
 * All content methods return StreamInterface. Use (string) cast for string access.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2011-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface MessageContent
{
    public function getFullMsg(): StreamInterface;

    public function getHeaderText(string|int $id = 0): StreamInterface;

    public function getBodyText(string|int $id = 0): StreamInterface;
}
