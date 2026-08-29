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
 * The wire seam an `AUTHENTICATE` exchange is driven over.
 *
 * Minimal and IMAP-command-pipeline-agnostic: {@see
 * SaslAuthenticator} only needs to send the initial `AUTHENTICATE` command,
 * read the next continuation-or-result event, send a continuation response
 * and cancel the exchange (`*`) on a client-side failure. A concrete
 * implementation backed by the full command pipeline can be substituted
 * later without any change to the authenticator. A fake in-memory
 * implementation is enough to test the whole exchange today.
 *
 * All payloads are raw octets. Base64 framing (RFC 3501 §9 `base64`) is
 * this interface's concern, not the caller's, matching how horde/Sasl keeps
 * its own {@see \Horde\Sasl\Data\Challenge}/{@see \Horde\Sasl\Data\Response}
 * octet-based and framing-agnostic.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface AuthenticationChannel
{
    /**
     * Send the `AUTHENTICATE <mechanism> [initial-response]` command.
     *
     * @param string      $mechanismName    The IANA mechanism name (e.g.
     *                                      `SCRAM-SHA-256`).
     * @param string|null $initialResponse  Raw octets to inline as the
     *                                      initial response (SASL-IR,
     *                                      RFC 4959), or null to send none
     *                                      (server-first mechanism, or a
     *                                      server that lacks SASL-IR).
     */
    public function sendAuthenticate(string $mechanismName, ?string $initialResponse): void;

    /**
     * Read the next event: a continuation challenge or the tagged result.
     */
    public function nextEvent(): ChannelEvent;

    /**
     * Send a continuation response line.
     *
     * @param string $response Raw octets (empty string for a zero-length
     *                         placeholder response).
     */
    public function sendResponse(string $response): void;

    /**
     * Abort the exchange client-side (the `*` continuation response).
     *
     * Sent when the mechanism rejects a challenge or the exchange protocol
     * is violated, so the server can clean up its side before the
     * connection is (typically) closed.
     */
    public function cancel(): void;
}
