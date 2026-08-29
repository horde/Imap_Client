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
 * Capability set for an IMAP server's `CAPABILITY` response.
 *
 * Adds two things the plain {@see CapabilityData} storage doesn't have:
 *
 * - Capability *implication* rules that only make sense for IMAP:
 *   `QRESYNC` implies both `CONDSTORE` and `ENABLE` (RFC 7162 §3.2.3), and
 *   `UTF8=ONLY` implies `UTF8=ACCEPT` (RFC 6855 §3). These are read-only
 *   `query()` conveniences. They never add rows to the underlying data.
 * - `ENABLE` (RFC 5161) state: which extensions the client has actually
 *   turned on for the connection, tracked separately from which the server
 *   merely advertised support for.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2014-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapCapability implements Capability
{
    use CapabilityData {
        query as private baseQuery;
    }

    /**
     * @var list<string>
     */
    private array $enabled = [];

    public function query(string $capability, ?string $parameter = null): bool
    {
        if ($this->baseQuery($capability, $parameter)) {
            return true;
        }

        return match (strtoupper($capability)) {
            // RFC 7162 §3.2.3: QRESYNC implies CONDSTORE and ENABLE.
            'CONDSTORE', 'ENABLE' => $parameter === null && $this->query('QRESYNC'),
            // RFC 6855 §3: UTF8=ONLY implies UTF8=ACCEPT.
            'UTF8' => $parameter !== null
                && strtoupper($parameter) === 'ACCEPT'
                && $this->query('UTF8', 'ONLY'),
            default => false,
        };
    }

    /**
     * The extensions currently enabled via `ENABLE` (RFC 5161).
     *
     * @return list<string>
     */
    public function enabled(): array
    {
        return $this->enabled;
    }

    public function isEnabled(string $capability): bool
    {
        return in_array(strtoupper($capability), $this->enabled, true);
    }

    /**
     * Record an extension as enabled (or disabled) for this connection.
     *
     * This does not itself send `ENABLE` to the server. It records the
     * outcome of an exchange the protocol driver already carried out.
     */
    public function enable(string $capability, bool $enable = true): void
    {
        $capability = strtoupper($capability);
        $isEnabled = $this->isEnabled($capability);

        if ($enable && !$isEnabled) {
            if ($capability === 'QRESYNC') {
                // RFC 7162 §3.2.3: enabling QRESYNC also enables CONDSTORE.
                $this->enable('CONDSTORE');
            }

            $this->enabled[] = $capability;
        } elseif (!$enable && $isEnabled) {
            $this->enabled = array_values(array_diff($this->enabled, [$capability]));
        }
    }

    /**
     * The command-line length (in octets) it's safe to assume the server
     * accepts.
     *
     * RFC 2683 §3.2.1.5 originally recommended limiting lines to
     * "approximately 1000 octets" while requiring servers to accept at
     * least 8000. RFC 7162 §4 raised the recommendation to 8192. As a
     * compromise, assume 2000 octets for a plain server, and 8000 once
     * CONDSTORE/QRESYNC support is advertised (their mere presence is
     * signal enough. No need to check they're actually in use).
     */
    public function cmdLength(): int
    {
        return ($this->query('CONDSTORE') || $this->query('QRESYNC')) ? 8000 : 2000;
    }
}
