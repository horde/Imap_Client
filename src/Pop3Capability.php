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
 * Capability set for a POP3 server's `CAPA` response (RFC 2449).
 *
 * POP3 has no capability-implication rules and no `ENABLE` state. It is
 * exactly the generic {@see CapabilityData} storage with no protocol-
 * specific behavior added, kept as its own named type (rather than
 * consumers using the trait directly) so `Pop3Client`'s capability
 * accessor has a type as specific as `ImapClient`'s.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2014-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class Pop3Capability implements Capability
{
    use CapabilityData;
}
