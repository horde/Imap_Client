<?php

declare(strict_types=1);

/**
 * Copyright 2012-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2012-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Exception;

use Throwable;

/**
 * Carries the server's tagged response data.
 *
 * Provides access to the IMAP command tag, status code, and response text
 * so callers can inspect the server's exact reply.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2012-2026 Horde LLC
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
    ) {
        parent::__construct($message, $code, $previous);
    }
}
