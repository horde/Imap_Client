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

use Countable;
use IteratorAggregate;

/**
 * Thin shared interface for message ID sets.
 *
 * Two independent implementations: ImapIdSet (range-aware integer UIDs,
 * sequence mode) and Pop3IdSet (flat string set).
 *
 * @extends IteratorAggregate<int|string>
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2011-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface MessageIdSet extends Countable, IteratorAggregate
{
    public function isEmpty(): bool;

    /**
     * @return array<int|string>
     */
    public function toArray(): array;

    public function __toString(): string;
}
