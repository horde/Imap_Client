<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\PasswordInterface;
use Horde\Imap\Client\SecureMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConnectionConfig::class)]
class ConnectionConfigTest extends TestCase
{
    public function testMinimalConstruction(): void
    {
        $cfg = new ConnectionConfig('user', 'pass');

        $this->assertSame('user', $cfg->username);
        $this->assertSame('pass', $cfg->password);
        $this->assertSame('localhost', $cfg->hostspec);
        $this->assertNull($cfg->port);
        $this->assertSame(SecureMode::None, $cfg->secure);
        $this->assertSame(30, $cfg->timeout);
        $this->assertSame(120, $cfg->readTimeout);
        $this->assertNull($cfg->context);
        $this->assertSame([], $cfg->capabilityIgnore);
        $this->assertNull($cfg->id);
        $this->assertSame([], $cfg->lang);
    }

    public function testFullConstruction(): void
    {
        $cfg = new ConnectionConfig(
            username: 'admin',
            password: 'secret',
            hostspec: 'mail.example.com',
            port: 993,
            secure: SecureMode::Ssl,
            timeout: 60,
            readTimeout: 300,
            context: ['ssl' => ['verify_peer' => false]],
            capabilityIgnore: ['CONDSTORE'],
            id: ['name' => 'TestClient'],
            lang: ['en'],
        );

        $this->assertSame('admin', $cfg->username);
        $this->assertSame(993, $cfg->port);
        $this->assertSame(SecureMode::Ssl, $cfg->secure);
        $this->assertSame(60, $cfg->timeout);
        $this->assertSame(['CONDSTORE'], $cfg->capabilityIgnore);
        $this->assertSame(['en'], $cfg->lang);
    }

    public function testPasswordInterface(): void
    {
        $pw = new class implements PasswordInterface {
            public function getPassword(): string
            {
                return 'dynamic-pass';
            }
        };

        $cfg = new ConnectionConfig('user', $pw);

        $this->assertInstanceOf(PasswordInterface::class, $cfg->password);
        $this->assertSame('dynamic-pass', $cfg->password->getPassword());
    }
}
