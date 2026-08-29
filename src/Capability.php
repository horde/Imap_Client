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
 * Protocol capability query interface.
 *
 * Shared interface for IMAP and POP3 capabilities. Implementations are
 * completely independent (ImapCapability is rich, Pop3Capability is simple).
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2014-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface Capability
{
    /**
     * Query whether a capability (and optional parameter) is supported.
     */
    public function query(string $capability, ?string $parameter = null): bool;

    /**
     * Get the parameters for a capability.
     *
     * @return string[]
     */
    public function getParams(string $capability): array;
}
