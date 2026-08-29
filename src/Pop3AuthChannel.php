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

use Horde\Imap\Client\Auth\AuthenticationChannel;
use Horde\Imap\Client\Auth\ChannelEvent;
use Horde\Imap\Client\Exception\Pop3ProtocolException;

/**
 * Drives POP3's `AUTH` command (RFC 5034) over a {@see Pop3Connection}.
 *
 * Wire-equivalent to IMAP's `AUTHENTICATE`: `AUTH <mechanism>
 * [initial-response]`, then `+ <base64-challenge>` continuations, then a
 * final `+OK`/`-ERR`. This is a real consumer of
 * {@see \Horde\Imap\Client\Auth\SaslAuthenticator} as opposed to the IMAP-shaped
 * fake used in tests), proving the channel abstraction isn't IMAP-specific.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class Pop3AuthChannel implements AuthenticationChannel
{
    public function __construct(
        private readonly Pop3Connection $connection,
    ) {}

    public function sendAuthenticate(string $mechanismName, ?string $initialResponse): void
    {
        $command = 'AUTH ' . $mechanismName;

        if ($initialResponse !== null) {
            $command .= ' ' . ($initialResponse === '' ? '=' : base64_encode($initialResponse));
        }

        $this->connection->sendLine($command);
    }

    public function nextEvent(): ChannelEvent
    {
        $line = $this->connection->readStatusLine();

        if ($line->isContinuation()) {
            $decoded = base64_decode($line->text, true);

            if ($decoded === false) {
                throw new Pop3ProtocolException(
                    'Server sent a malformed base64 continuation.',
                );
            }

            return ChannelEvent::challenge($decoded);
        }

        if ($line->isError()) {
            return ChannelEvent::failure(null, $line->text);
        }

        return ChannelEvent::success(null, $line->text);
    }

    /**
     * A zero-length response is a bare empty base64 line, not the `=`
     * shorthand. RFC 5034 §4 reserves `=` for the initial-response
     * argument of the `AUTH` command only; ordinary continuation
     * responses are always "a line containing a string encoded as
     * Base64", which for zero-length data is simply an empty line.
     */
    public function sendResponse(string $response): void
    {
        $this->connection->sendLine(base64_encode($response));
    }

    public function cancel(): void
    {
        $this->connection->sendLine('*');
    }
}
