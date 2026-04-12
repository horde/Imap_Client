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

namespace Horde\Imap\Client\Exception;

use RuntimeException;

/**
 * Base exception for all mailbox protocol operations.
 *
 * Catch this to handle any IMAP or POP3 error uniformly.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2008-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class MailboxProtocolException extends RuntimeException {}
