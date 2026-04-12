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

use Generator;
use Horde_Stream;

/**
 * Layer 2: MIME part access (IMAP-only).
 *
 * POP3 does not implement this — MIME part addressing is not a POP3 feature.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2011-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface PartAccess
{
    public function getBodyPart(string $id): Horde_Stream;

    public function getMimeHeader(string $id): Horde_Stream;

    /**
     * Yields parts lazily from BODYSTRUCTURE.
     *
     * @return Generator
     */
    public function getParts(): Generator;
}
