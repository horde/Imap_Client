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

namespace Horde\Imap\Client\Test\Unit\Src\Auth;

use Horde\Imap\Client\Auth\SocketChannelBindingProvider;
use Horde\Sasl\ChannelBinding\ChannelBindingType as SaslBindingType;
use Horde\Sasl\Exception\ChannelBindingException as SaslChannelBindingException;
use Horde\Socket\Client\ChannelBinding\ChannelBindingType as SocketBindingType;
use Horde\Socket\Client\ClientInterface;
use Horde\Socket\Client\Exception\ChannelBindingException as SocketChannelBindingException;
use Horde\Socket\Client\StreamStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SocketChannelBindingProvider::class)]
class SocketChannelBindingProviderTest extends TestCase
{
    private function client(
        bool $supportsTlsServerEndPoint = true,
        ?string $bindingData = 'fake-cert-hash',
        ?\Throwable $bindingException = null,
    ): ClientInterface {
        return new class ($supportsTlsServerEndPoint, $bindingData, $bindingException) implements ClientInterface {
            public function __construct(
                private readonly bool $supportsTlsServerEndPoint,
                private readonly ?string $bindingData,
                private readonly ?\Throwable $bindingException,
            ) {}

            public function isConnected(): bool
            {
                return true;
            }

            public function isSecure(): bool
            {
                return true;
            }

            public function supportsChannelBinding(SocketBindingType $type): bool
            {
                return $type === SocketBindingType::TlsServerEndPoint && $this->supportsTlsServerEndPoint;
            }

            public function channelBindingData(SocketBindingType $type): string
            {
                if ($this->bindingException !== null) {
                    throw $this->bindingException;
                }

                return $this->bindingData ?? '';
            }

            public function startTls(): bool
            {
                return true;
            }

            public function close(): void {}

            public function getStatus(): StreamStatus
            {
                return new StreamStatus(timedOut: false, blocked: false, eof: false, unreadBytes: 0);
            }

            public function gets(int $size): string
            {
                return '';
            }

            public function read(int $size): string
            {
                return '';
            }

            public function write(string $data): void {}
        };
    }

    public function testAvailableReportsTlsServerEndPointWhenSupported(): void
    {
        $provider = new SocketChannelBindingProvider($this->client(supportsTlsServerEndPoint: true));

        $this->assertSame([SaslBindingType::TlsServerEndPoint], $provider->available());
    }

    public function testAvailableIsEmptyWhenNotSupported(): void
    {
        $provider = new SocketChannelBindingProvider($this->client(supportsTlsServerEndPoint: false));

        $this->assertSame([], $provider->available());
    }

    public function testBindingDataDelegatesToSocketClientAndTranslatesEnum(): void
    {
        $provider = new SocketChannelBindingProvider($this->client(bindingData: 'fake-cert-hash'));

        $this->assertSame('fake-cert-hash', $provider->bindingData(SaslBindingType::TlsServerEndPoint));
    }

    public function testBindingDataWrapsSocketClientExceptionIntoSaslException(): void
    {
        $original = new SocketChannelBindingException('no TLS session bound to this socket');
        $provider = new SocketChannelBindingProvider($this->client(bindingException: $original));

        try {
            $provider->bindingData(SaslBindingType::TlsServerEndPoint);
            $this->fail('Expected a SaslChannelBindingException to be thrown.');
        } catch (SaslChannelBindingException $e) {
            $this->assertSame('no TLS session bound to this socket', $e->getMessage());
            $this->assertSame($original, $e->getPrevious());
        }
    }
}
