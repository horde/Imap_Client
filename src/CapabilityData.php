<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2014-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

/**
 * Generic capability storage trait shared by IMAP and POP3.
 *
 * Holds the raw capability-name to parameter-list data parsed out of a
 * `CAPABILITY`/`CAPA` response and implements the {@see Capability}
 * read contract (`query()`/`getParams()`) plus the mutation/introspection
 * methods (`add()`/`remove()`/`toArray()`/`__serialize()`/`__unserialize()`)
 * the protocol driver uses while parsing a response.
 *
 * {@see Pop3Capability} uses the trait verbatim
 * (POP3's `CAPA` response has no protocol-specific implication rules), while
 * {@see ImapCapability} uses it and *overrides* `query()` to layer IMAP's
 * own implication rules (RFC 7162 §3.2.3, RFC 6855 §3) on top.
 * Avoids a logically wrong `extends` relationship between two otherwise-unrelated protocol types.
 *
 * No PSR-14 dispatcher included by choice. Capability changes are an
 * internal parsing detail, not an event in their own right. The driving
 * code dispatches `CapabilityNegotiated` or `CapabilityIgnored` itself once
 * parsing is complete, using whatever context (mailbox, connection) it has
 * that this storage does not.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2014-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
trait CapabilityData
{
    /**
     * @var array<string, true|list<string>>
     */
    protected array $data = [];

    /**
     * Add a capability with optional parameters.
     *
     * Calling this again for an already-known capability merges the new
     * parameters into the existing list rather than replacing it (a server
     * response may list `AUTH=PLAIN` and `AUTH=LOGIN` as separate tokens
     * that both belong under one `AUTH` capability).
     *
     * @param string|list<string>|null $params
     */
    public function add(string $capability, string|array|null $params = null): void
    {
        $capability = strtoupper($capability);

        if ($params === null) {
            if (isset($this->data[$capability])) {
                return;
            }

            $this->data[$capability] = true;

            return;
        }

        $params = array_map(strtoupper(...), is_array($params) ? $params : [$params]);

        $existing = $this->data[$capability] ?? null;
        $this->data[$capability] = is_array($existing) ? [...$existing, ...$params] : $params;
    }

    /**
     * Remove a capability or just one/more of its parameters.
     *
     * @param string|list<string>|null $params
     */
    public function remove(string $capability, string|array|null $params = null): void
    {
        $capability = strtoupper($capability);

        if ($params === null) {
            unset($this->data[$capability]);

            return;
        }

        if (!isset($this->data[$capability])) {
            return;
        }

        $params = array_map(strtoupper(...), is_array($params) ? $params : [$params]);
        $remaining = is_array($this->data[$capability])
            ? array_values(array_diff($this->data[$capability], $params))
            : [];

        if ($remaining === []) {
            unset($this->data[$capability]);
        } else {
            $this->data[$capability] = $remaining;
        }
    }

    public function query(string $capability, ?string $parameter = null): bool
    {
        $capability = strtoupper($capability);

        if (!isset($this->data[$capability])) {
            return false;
        }

        if ($parameter === null) {
            return true;
        }

        $params = $this->data[$capability];

        return is_array($params) && in_array(strtoupper($parameter), $params, true);
    }

    /**
     * @return list<string>
     */
    public function getParams(string $capability): array
    {
        $params = $this->data[strtoupper($capability)] ?? null;

        return is_array($params) ? $params : [];
    }

    /**
     * Raw capability data keyed by uppercased capability name.
     *
     * @return array<string, true|list<string>>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, true|list<string>>
     */
    public function __serialize(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, true|list<string>> $data
     */
    public function __unserialize(array $data): void
    {
        $this->data = $data;
    }
}
