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

use DateTimeImmutable;

/**
 * Layer 0: Scalar message metadata — always available, always lightweight.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2011-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface MessageMetadata
{
    public function getUid(): int|string;

    /**
     * @return string[]
     */
    public function getFlags(): array;

    public function getSize(): int;

    public function getImapDate(): DateTimeImmutable;

    public function getSeq(): ?int;

    public function getModSeq(): ?int;
}
