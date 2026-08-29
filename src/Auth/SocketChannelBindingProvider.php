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

use Horde\Sasl\ChannelBinding\ChannelBindingProvider;
use Horde\Sasl\ChannelBinding\ChannelBindingType as SaslBindingType;
use Horde\Sasl\Exception\ChannelBindingException as SaslChannelBindingException;
use Horde\Socket\Client\ChannelBinding\ChannelBindingType as SocketBindingType;
use Horde\Socket\Client\ClientInterface;
use Horde\Socket\Client\Exception\ChannelBindingException as SocketChannelBindingException;

/**
 * Delegates SCRAM-*-PLUS channel-binding data to the underlying socket.
 *
 * horde/socket_client owns the TLS connection and captures the data the
 * SCRAM-*-PLUS mechanisms need (RFC 5929 / RFC 9266); this adapter is the
 * thin translation the two libraries' independently-defined
 * `ChannelBindingType` enums need (they share backing string values by
 * design but neither library depends on the other).
 *
 * Only `tls-server-end-point` is ever available in pure PHP.
 * `tls-exporter`/`tls-unique` cannot be implemented on this platform.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class SocketChannelBindingProvider implements ChannelBindingProvider
{
    public function __construct(
        private readonly ClientInterface $client,
    ) {}

    public function available(): array
    {
        return $this->client->supportsChannelBinding(SocketBindingType::TlsServerEndPoint)
            ? [SaslBindingType::TlsServerEndPoint]
            : [];
    }

    public function bindingData(SaslBindingType $type): string
    {
        try {
            return $this->client->channelBindingData(SocketBindingType::from($type->value));
        } catch (SocketChannelBindingException $e) {
            // Re-thrown as horde/Sasl's own exception type so mechanisms
            // (and their callers) only ever need to catch one hierarchy,
            // never leak the horde/socket_client exception across the package
            // boundary.
            throw new SaslChannelBindingException($e->getMessage(), 0, $e);
        }
    }
}
