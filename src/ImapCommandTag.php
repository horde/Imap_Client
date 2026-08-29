<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

/**
 * Generates unique command tags (RFC 3501 §2.2.1).
 *
 * A tag only has to be distinct from every other tag currently
 * outstanding on the same connection. A short prefix plus a sequential
 * counter (`A1`, `A2`, ...) is enough so it will not collide with the untagged (`*`) or continuation (`+`)
 * markers.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapCommandTag
{
    private int $counter = 0;

    public function __construct(
        private readonly string $prefix = 'A',
    ) {}

    public function next(): string
    {
        ++$this->counter;

        return $this->prefix . $this->counter;
    }
}
