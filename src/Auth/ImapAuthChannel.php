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

namespace Horde\Imap\Client\Auth;

use Horde\Imap\Client\Exception\ImapProtocolException;
use Horde\Imap\Client\ImapCommand;
use Horde\Imap\Client\ImapCommandTag;
use Horde\Imap\Client\ImapConnection;

/**
 * Drives IMAP's `AUTHENTICATE` command (RFC 3501 §6.2.2, RFC 4959
 * SASL-IR) over an {@see ImapConnection}.
 *
 * Consumer of {@see SaslAuthenticator} besides
 * {@see \Horde\Imap\Client\Pop3AuthChannel}.
 *
 * - {@see nextEvent()} must never throw for a tagged `NO`/`BAD`. It
 *   has to come back as {@see ChannelEvent::failure()} since
 *   {@see SaslAuthenticator::runExchange()} only ever catches
 *   mechanism-level exceptions. A malformed base64 continuation is a
 *   different kind of problem (wire corruption, not an authentication
 *   outcome) and does still throw.
 * - The `=` empty-initial-response shorthand (RFC 4959 §3) only
 *   applies to `AUTHENTICATE`'s own initial-response argument. An
 *   ordinary continuation reply is always a plain (possibly
 *   zero-length) base64 line but never `=`. {@see sendResponse()} must
 *   not apply the shorthand there.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapAuthChannel implements AuthenticationChannel
{
    private readonly string $tag;

    public function __construct(
        private readonly ImapConnection $connection,
        ?ImapCommandTag $tags = null,
    ) {
        $this->tag = ($tags ?? new ImapCommandTag())->next();
    }

    public function sendAuthenticate(string $mechanismName, ?string $initialResponse): void
    {
        $arguments = [$mechanismName];

        if ($initialResponse !== null) {
            $arguments[] = $initialResponse === '' ? '=' : base64_encode($initialResponse);
        }

        $this->connection->sendCommand(new ImapCommand($this->tag, 'AUTHENTICATE', $arguments));
    }

    /**
     * @throws ImapProtocolException If a continuation carries malformed
     *                                 base64 data.
     */
    public function nextEvent(): ChannelEvent
    {
        while (true) {
            $response = $this->connection->readResponse();

            if ($response->isContinuation()) {
                $decoded = base64_decode($response->text, true);

                if ($decoded === false) {
                    throw new ImapProtocolException(
                        'Server sent a malformed base64 continuation.',
                    );
                }

                return ChannelEvent::challenge($decoded);
            }

            if ($response->isUntagged()) {
                // Unsolicited data mid-exchange (usually an alert).
                // Nothing in this channel's contract has room to
                // surface it and skipping it is harmless.
                continue;
            }

            // The only tagged response this exchange can produce is
            // the one for its own AUTHENTICATE command.
            return $response->isOk()
                ? ChannelEvent::success($response->responseCode?->name, $response->text)
                : ChannelEvent::failure($response->responseCode?->name, $response->text);
        }
    }

    /**
     * A zero-length response is a bare empty base64 line, never the
     * `=` shorthand. That shorthand is reserved for `AUTHENTICATE`'s
     * own initial-response argument (RFC 4959 §3).
     */
    public function sendResponse(string $response): void
    {
        $this->connection->writeLine(base64_encode($response));
    }

    public function cancel(): void
    {
        $this->connection->writeLine('*');
    }
}
