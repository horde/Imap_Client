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
 * The result of a `LISTRIGHTS` command (RFC 4314 §3.7): the rights an
 * identifier always has on a mailbox, and the rights that may optionally
 * be granted to it.
 *
 * The wire response is a space-separated list whose first element is the
 * set of always-granted rights (a single run of letters) followed by the
 * optionally-grantable rights, each as its own element (a right that must
 * be granted as a group appears as a multi-letter element).
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final readonly class ImapAclRights
{
    /**
     * @param list<string> $required Rights the identifier always has.
     * @param list<string> $optional Rights that may be granted (each entry
     *                               is one grantable unit, possibly a
     *                               multi-letter group).
     */
    public function __construct(
        public array $required = [],
        public array $optional = [],
    ) {}
}
