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

namespace Horde\Imap\Client;

use Horde\Imap\Client\Event\DiagnosticEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * PSR-14 decorator that suppresses event classes before the inner bus.
 *
 * By default DiagnosticEvent (and all its subclasses) is suppressed.
 * Pass an empty $suppress array to let everything through.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2008-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class FilteredEventDispatcher implements EventDispatcherInterface
{
    /**
     * @param class-string[] $suppress Event classes to swallow silently.
     */
    public function __construct(
        private readonly EventDispatcherInterface $inner,
        private readonly array $suppress = [DiagnosticEvent::class],
    ) {}

    public function dispatch(object $event): object
    {
        foreach ($this->suppress as $class) {
            if ($event instanceof $class) {
                return $event;
            }
        }

        return $this->inner->dispatch($event);
    }
}
