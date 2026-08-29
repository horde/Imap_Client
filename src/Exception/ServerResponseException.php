<?php

declare(strict_types=1);

/**
 * Copyright 2012-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2012-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Exception;

use Horde\Imap\Client\ImapResponseCode;
use Throwable;

/**
 * Carries the server's tagged response data.
 *
 * Provides access to the IMAP command tag, status code, response text and
 * (when present) the bracketed response code so callers can inspect the
 * server's exact reply. The response code lets a caller react to
 * machine-readable failure hints such as `[TRYCREATE]` (RFC 3501 §6.4.7)
 * or `[MODIFIED ...]` without re-parsing the human-readable text.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2012-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class ServerResponseException extends MailboxProtocolException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $command = null,
        public readonly ?string $status = null,
        public readonly ?string $responseText = null,
        public readonly ?ImapResponseCode $responseCode = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
