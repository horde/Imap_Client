<?php

declare(strict_types=1);

/**
 * Copyright 2008-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

use Generator;

/**
 * Common ground between IMAP and POP3.
 *
 * Methods that both protocols genuinely support. Everything else
 * belongs on ImapProtocol or extension interfaces.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface MailboxProtocol
{
    public function login(): void;

    public function logout(): void;

    public function noop(): void;

    /**
     * @return object MailboxStatus value object
     */
    public function status(string $mailbox, int $flags): object;

    /**
     * @param object $query FetchQuery
     * @return Generator<int|string, MessageMetadata&MessageContent>
     */
    public function fetch(string $mailbox, MessageIdSet $ids, object $query): Generator;

    public function store(string $mailbox, array $options): MessageIdSet;

    public function expunge(string $mailbox, array $options): MessageIdSet;

    public function getIdsOb(mixed $ids = null, bool $sequence = false): MessageIdSet;
}
