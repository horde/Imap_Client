<?php

declare(strict_types=1);

/**
 * Copyright 2008-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

use Horde\Sasl\Negotiation\SaslPolicy;

/**
 * Immutable connection configuration DTO.
 *
 * Replaces the associative array previously passed to
 * Horde_Imap_Client_Base::__construct().  All values are stored.
 * The TCP connection is deferred until the first real protocol call.
 *
 * When $port is null the implementation picks the conventional default
 * (993 for SSL, 143 for plain/TLS IMAP or 995/110 for POP3).
 *
 * Credentials are intentionally *not* part of this DTO.
 * They have a different lifecycle and may need refreshing independent of connection
 * parameters, e.g. OAuth token expiry or re-authentication after an
 * IDLE drop. Thus they are supplied separately to the auth adapter either at
 * construction or via a dedicated `authenticate()` call. See
 * Horde\Sasl\Credentials.
 *
 * $saslPolicy governs which SASL mechanisms are acceptable for this
 * connection. Defaults to SaslPolicy::secureDefaults(), which denies
 * plaintext/weak mechanisms without TLS. Legacy servers that only
 * offer PLAIN/LOGIN without TLS or only CRAM-MD5/DIGEST-MD5 require
 * relaxing this via SaslPolicy::legacyCompatible() or a custom
 * policy. The caller opts in explicitly.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ConnectionConfig
{
    public function __construct(
        public readonly string $hostspec = 'localhost',
        public readonly ?int $port = null,
        public readonly SecureMode $secure = SecureMode::None,
        public readonly int $timeout = 30,
        public readonly int $readTimeout = 120,
        public readonly ?array $context = null,
        public readonly array $capabilityIgnore = [],
        public readonly ?array $id = null,
        public readonly array $lang = [],
        public readonly ?SaslPolicy $saslPolicy = null,
    ) {}
}
