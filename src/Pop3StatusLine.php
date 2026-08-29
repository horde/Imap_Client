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
 * One parsed POP3 status line: `+OK <text>`, `-ERR <text>`, or `+ <text>`
 * (RFC 1939 §3, RFC 5034 continuation).
 *
 * Non-throwing by design. {@see Pop3Connection::readStatusLine()}
 * hands this back raw so callers with different needs (an ordinary command
 * that always wants an exception on `-ERR`, versus a SASL exchange that
 * needs to route `-ERR` into a {@see Auth\ChannelEvent} failure) can each
 * decide what a `-ERR` means to them.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class Pop3StatusLine
{
    public function __construct(
        public readonly Pop3ResponseKind $kind,
        public readonly string $text,
    ) {}

    public function isOk(): bool
    {
        return $this->kind === Pop3ResponseKind::Ok;
    }

    public function isError(): bool
    {
        return $this->kind === Pop3ResponseKind::Error;
    }

    public function isContinuation(): bool
    {
        return $this->kind === Pop3ResponseKind::Continuation;
    }
}
