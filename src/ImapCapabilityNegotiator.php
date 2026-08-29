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
 * Drives `CAPABILITY` and `ENABLE` (RFC 5161) over an
 * {@see ImapInteraction} and decides whether to switch the connection
 * into IMAP4rev2 behavior.
 *
 * This is a dedicated step precisely so "rev2 support" does not turn
 * into scattered ad hoc `if` checks in every later part: Whatever
 * this class stores onto an {@see ImapCapability} (its raw data via
 * {@see fetch()} and its `ENABLE` state via {@see enable()}) is the
 * one behavior flag consuming code reads. `\Recent`/`UNSEEN`
 * removal, `LIST` vs `LSUB`, `SEARCH` vs `ESEARCH` and UTF-8 mailbox
 * names are all gated by querying that same object.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapCapabilityNegotiator
{
    public function __construct(
        private readonly ImapInteraction $interaction,
    ) {}

    /**
     * Send `CAPABILITY` and build a fresh {@see ImapCapability} from
     * every response that carries capability data. The untagged
     * `* CAPABILITY ...` data response and defensively a
     * `[CAPABILITY ...]` response code on the tagged completion, in
     * case a server piggybacks it there instead (RFC 3501 §7.1).
     */
    public function fetch(): ImapCapability
    {
        $capability = new ImapCapability();
        $result = $this->interaction->send('CAPABILITY');

        foreach ($result->untagged as $response) {
            self::mergeFromResponse($response, $capability);
        }

        self::mergeFromResponse($result->tagged, $capability);

        return $capability;
    }

    /**
     * Send `ENABLE <extensions...>` and record whichever ones the
     * server actually acknowledges via its untagged `ENABLED` response
     * (RFC 5161 §3.1: The server may enable fewer than requested but
     * always confirms the ones it did).
     *
     * @param list<string> $extensions
     *
     * @return list<string> The extensions the server confirmed enabled.
     */
    public function enable(ImapCapability $capability, array $extensions): array
    {
        $result = $this->interaction->send('ENABLE', $extensions);
        $enabled = [];

        foreach ($result->untagged as $response) {
            if (!$response->isUntagged() || $response->data === [] || !is_string($response->data[0])) {
                continue;
            }

            if (strtoupper($response->data[0]) !== 'ENABLED') {
                continue;
            }

            foreach (array_slice($response->data, 1) as $token) {
                if (is_string($token)) {
                    $capability->enable($token);
                    $enabled[] = strtoupper($token);
                }
            }
        }

        return $enabled;
    }

    /**
     * Switch the connection into IMAP4rev2 behavior if the server
     * supports it. RFC 9051 §3.2: A server advertising both
     * `IMAP4rev1` and `IMAP4rev2` behaves as rev1 until the client asks
     * otherwise.
     * Without it a rev2-capable server is driven with rev1 semantics all connection long.
     *
     * A rev1-only server that separately advertises `UTF8=ACCEPT` (RFC
     * 6855) still benefits from UTF-8 mailbox names and `literal8`,
     * even without full rev2 command/response semantics.
     *
     * @return string|null The capability actually enabled
     *                       (`IMAP4REV2` or `UTF8=ACCEPT`) or null if
     *                       the server offers neither.
     */
    public function negotiateRev2(ImapCapability $capability): ?string
    {
        if ($capability->query('IMAP4REV2')) {
            $this->enable($capability, ['IMAP4rev2']);

            return 'IMAP4REV2';
        }

        if ($capability->query('UTF8', 'ACCEPT')) {
            $this->enable($capability, ['UTF8=ACCEPT']);

            return 'UTF8=ACCEPT';
        }

        return null;
    }

    /**
     * Merge capability data out of one response if it carries any.
     * Either an untagged `* CAPABILITY ...` data response or a
     * `[CAPABILITY ...]` response code on any response: A greeting or
     * a tagged completion. Returns whether it found anything.
     */
    public static function mergeFromResponse(ImapResponse $response, ImapCapability $capability): bool
    {
        if ($response->responseCode !== null && strtoupper($response->responseCode->name) === 'CAPABILITY') {
            ImapCapabilityParser::parse($response->responseCode->data, $capability);

            return true;
        }

        if (
            $response->isUntagged()
            && $response->data !== []
            && is_string($response->data[0])
            && strtoupper($response->data[0]) === 'CAPABILITY'
        ) {
            ImapCapabilityParser::parse(array_slice($response->data, 1), $capability);

            return true;
        }

        return false;
    }
}
