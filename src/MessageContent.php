<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2011-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

use Horde_Stream;

/**
 * Layer 1: Message content access via Horde_Stream.
 *
 * All content methods return Horde_Stream. Use (string) cast for string access.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2011-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface MessageContent
{
    public function getFullMsg(): Horde_Stream;

    public function getHeaderText(string|int $id = 0): Horde_Stream;

    public function getBodyText(string|int $id = 0): Horde_Stream;
}
