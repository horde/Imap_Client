<?php

declare(strict_types=1);

/**
 * Copyright 2008-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2008-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

/**
 * Immutable connection configuration DTO.
 *
 * Replaces the associative array previously passed to
 * Horde_Imap_Client_Base::__construct().  All values are stored; the
 * TCP connection is deferred until the first real protocol call.
 *
 * When $port is null the implementation picks the conventional default
 * (993 for SSL, 143 for plain/TLS IMAP; 995/110 for POP3).
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2008-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ConnectionConfig
{
    public function __construct(
        public readonly string $username,
        public readonly string|PasswordInterface $password,
        public readonly string $hostspec = 'localhost',
        public readonly ?int $port = null,
        public readonly SecureMode $secure = SecureMode::None,
        public readonly int $timeout = 30,
        public readonly int $readTimeout = 120,
        public readonly ?array $context = null,
        public readonly array $capabilityIgnore = [],
        public readonly ?array $id = null,
        public readonly array $lang = [],
    ) {}
}
