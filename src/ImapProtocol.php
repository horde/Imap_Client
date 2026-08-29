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

/**
 * IMAP protocol operations beyond MailboxProtocol.
 *
 * Includes mailbox management, search, threading, copy/move, append,
 * namespace queries, and capability access. IMAP rev1/rev2 negotiation
 * is internal to the implementation.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface ImapProtocol extends MailboxProtocol
{
    public function getCapability(): Capability;

    public function openMailbox(string $mailbox, OpenMode $mode): void;

    public function createMailbox(string $mailbox): void;

    public function deleteMailbox(string $mailbox): void;

    public function renameMailbox(string $old, string $new): void;

    public function subscribeMailbox(string $mailbox, bool $subscribe = true): void;

    public function listMailboxes(string $pattern, MailboxListMode $mode, array $options = []): array;

    public function close(array $options = []): void;

    /**
     * @param object $query SearchQuery
     * @return object SearchResult
     */
    public function search(string $mailbox, object $query, array $options = []): object;

    /**
     * @return object ThreadResult
     */
    public function thread(string $mailbox, array $options = []): object;

    public function copy(string $source, string $dest, array $options = []): MessageIdSet;

    public function move(string $source, string $dest, array $options = []): MessageIdSet;

    public function append(string $mailbox, array $data, array $options = []): MessageIdSet;

    /**
     * @return object NamespaceList
     */
    public function getNamespaces(): object;

    public function unselect(): void;
}
