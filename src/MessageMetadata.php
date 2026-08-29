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

use DateTimeImmutable;

/**
 * Scalar message metadata always available, always lightweight.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2011-2026 The Horde Project
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
