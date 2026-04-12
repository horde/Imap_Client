<?php

declare(strict_types=1);

/**
 * Copyright 2008-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2008-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Event;

/**
 * Base event for all IMAP/POP3 client events.
 *
 * Consumers can listen for this class to catch every event, or for
 * specific subclasses to handle individual signals.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2008-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
abstract class ImapEvent
{
    public function __construct(
        private readonly string $message = '',
        private readonly array $context = [],
    ) {}

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
