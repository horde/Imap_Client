<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\SecureMode;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConnectionConfig::class)]
class ConnectionConfigTest extends TestCase
{
    public function testMinimalConstruction(): void
    {
        $cfg = new ConnectionConfig();

        $this->assertSame('localhost', $cfg->hostspec);
        $this->assertNull($cfg->port);
        $this->assertSame(SecureMode::None, $cfg->secure);
        $this->assertSame(30, $cfg->timeout);
        $this->assertSame(120, $cfg->readTimeout);
        $this->assertNull($cfg->context);
        $this->assertSame([], $cfg->capabilityIgnore);
        $this->assertNull($cfg->id);
        $this->assertSame([], $cfg->lang);
        $this->assertNull($cfg->saslPolicy);
    }

    public function testFullConstruction(): void
    {
        $policy = SaslPolicy::legacyCompatible();

        $cfg = new ConnectionConfig(
            hostspec: 'mail.example.com',
            port: 993,
            secure: SecureMode::Ssl,
            timeout: 60,
            readTimeout: 300,
            context: ['ssl' => ['verify_peer' => false]],
            capabilityIgnore: ['CONDSTORE'],
            id: ['name' => 'TestClient'],
            lang: ['en'],
            saslPolicy: $policy,
        );

        $this->assertSame('mail.example.com', $cfg->hostspec);
        $this->assertSame(993, $cfg->port);
        $this->assertSame(SecureMode::Ssl, $cfg->secure);
        $this->assertSame(60, $cfg->timeout);
        $this->assertSame(['CONDSTORE'], $cfg->capabilityIgnore);
        $this->assertSame(['en'], $cfg->lang);
        $this->assertSame($policy, $cfg->saslPolicy);
    }

    public function testSaslPolicyDefaultsToNull(): void
    {
        $cfg = new ConnectionConfig(hostspec: 'mail.example.com');

        $this->assertNull($cfg->saslPolicy);
    }
}
