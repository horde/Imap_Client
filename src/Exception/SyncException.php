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

namespace Horde\Imap\Client\Exception;

/**
 * A mailbox sync could not proceed: the token was malformed, or the
 * mailbox's UIDVALIDITY changed since the token was taken (RFC 3501
 * §2.3.1.1), meaning the cached UID space is no longer valid and the
 * caller must resynchronize from scratch.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class SyncException extends ImapProtocolException {}
