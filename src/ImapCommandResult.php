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
 * The outcome of one {@see ImapInteraction::send()} round trip.
 * A tagged response plus every untagged response collected
 * while waiting for it.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapCommandResult
{
    /**
     * @param list<ImapResponse> $untagged
     */
    public function __construct(
        public readonly ImapResponse $tagged,
        public readonly array $untagged,
    ) {}
}
