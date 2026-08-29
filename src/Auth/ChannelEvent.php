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

/**
 * One event read from the wire during an AUTHENTICATE exchange.
 *
 * IMAP's continuation protocol only carries two kinds of server response
 * during authentication: a `+ <payload>` continuation (a challenge, or the
 * final additional-data round. The two are wire-indistinguishable, see
 * {@see SaslAuthenticator}) or a tagged final result (`OK`/`NO`/`BAD`).
 * This value object represents whichever one {@see AuthenticationChannel}
 * read next, already un-base64'd and stripped of framing. All payloads are
 * raw octets matching horde/Sasl's own {@see \Horde\Sasl\Data\Challenge}
 * convention.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ChannelEvent
{
    private function __construct(
        private readonly bool $isChallenge,
        private readonly string $payload,
        private readonly bool $success,
        private readonly ?string $responseCode,
        private readonly string $text,
    ) {}

    /**
     * A `+ <payload>` continuation line.
     *
     * @param string $payload Raw (already base64-decoded) octets.
     */
    public static function challenge(string $payload): self
    {
        return new self(true, $payload, false, null, '');
    }

    /**
     * A tagged successful result (`OK`).
     *
     * @param string|null $responseCode The IMAP response code, if any
     *                                  (e.g. `CAPABILITY ...`), without the
     *                                  enclosing brackets.
     * @param string      $text         The human-readable response text.
     */
    public static function success(?string $responseCode = null, string $text = ''): self
    {
        return new self(false, '', true, $responseCode, $text);
    }

    /**
     * A tagged failure result (`NO`/`BAD`).
     *
     * @param string|null $responseCode The IMAP response code, if any
     *                                  (e.g. `AUTHENTICATIONFAILED`).
     * @param string      $text         The human-readable response text.
     */
    public static function failure(?string $responseCode = null, string $text = ''): self
    {
        return new self(false, '', false, $responseCode, $text);
    }

    /**
     * Whether this event is a continuation challenge (as opposed to a
     * tagged final result).
     */
    public function isChallenge(): bool
    {
        return $this->isChallenge;
    }

    /**
     * Whether this event is a tagged final result (as opposed to a
     * continuation challenge).
     */
    public function isOutcome(): bool
    {
        return !$this->isChallenge;
    }

    /**
     * The challenge payload. Empty for outcome events.
     */
    public function payload(): string
    {
        return $this->payload;
    }

    /**
     * Whether a tagged outcome was `OK`. Meaningless for challenge events.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * The IMAP response code carried by a tagged outcome, if any.
     */
    public function responseCode(): ?string
    {
        return $this->responseCode;
    }

    /**
     * The human-readable text carried by a tagged outcome.
     */
    public function text(): string
    {
        return $this->text;
    }
}
