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
 * IMAP ACL extension (RFC 4314).
 *
 * Separated because ACL is an optional server capability.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface ImapAclAware
{
    /**
     * @return array<string, object> ACL rights keyed by identifier (each
     *                               value an Acl value object).
     */
    public function getACL(string $mailbox): array;

    /**
     * @param array{rights?: string} $options
     */
    public function setACL(string $mailbox, string $identifier, array $options): void;

    public function deleteACL(string $mailbox, string $identifier): void;

    /**
     * @return object AclRights value object
     */
    public function listACLRights(string $mailbox, string $identifier): object;

    /**
     * @return object AclRights value object
     */
    public function getMyACLRights(string $mailbox): object;
}
