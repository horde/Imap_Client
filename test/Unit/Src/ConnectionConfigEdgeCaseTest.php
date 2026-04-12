<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Error;
use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\SecureMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConnectionConfig::class)]
class ConnectionConfigEdgeCaseTest extends TestCase
{
    public function testReadonlyUsernameThrowsError(): void
    {
        $cfg = new ConnectionConfig('user', 'pass');
        $this->expectException(Error::class);
        $cfg->username = 'other';
    }

    public function testReadonlyPasswordThrowsError(): void
    {
        $cfg = new ConnectionConfig('user', 'pass');
        $this->expectException(Error::class);
        $cfg->password = 'other';
    }

    public function testReadonlyPortThrowsError(): void
    {
        $cfg = new ConnectionConfig('user', 'pass', port: 993);
        $this->expectException(Error::class);
        $cfg->port = 143;
    }

    public function testEmptyUsername(): void
    {
        $cfg = new ConnectionConfig('', 'pass');
        $this->assertSame('', $cfg->username);
    }

    public function testEmptyPassword(): void
    {
        $cfg = new ConnectionConfig('user', '');
        $this->assertSame('', $cfg->password);
    }

    public function testPortZero(): void
    {
        $cfg = new ConnectionConfig('user', 'pass', port: 0);
        $this->assertSame(0, $cfg->port);
    }

    public function testNegativeTimeout(): void
    {
        $cfg = new ConnectionConfig('user', 'pass', timeout: -1);
        $this->assertSame(-1, $cfg->timeout);
    }

    public function testContextWithNestedArrays(): void
    {
        $context = ['ssl' => ['verify_peer' => false, 'cafile' => '/etc/ssl/certs/ca.pem']];
        $cfg = new ConnectionConfig('user', 'pass', context: $context);
        $this->assertSame($context, $cfg->context);
    }

    public function testCapabilityIgnorePreservesOrder(): void
    {
        $ignore = ['CONDSTORE', 'QRESYNC', 'IDLE'];
        $cfg = new ConnectionConfig('user', 'pass', capabilityIgnore: $ignore);
        $this->assertSame($ignore, $cfg->capabilityIgnore);
    }

    public function testIdArrayPreservesKeys(): void
    {
        $id = ['name' => 'Horde', 'version' => '6.0'];
        $cfg = new ConnectionConfig('user', 'pass', id: $id);
        $this->assertSame($id, $cfg->id);
    }

    public function testLangMultipleEntries(): void
    {
        $cfg = new ConnectionConfig('user', 'pass', lang: ['en', 'de', 'fr']);
        $this->assertCount(3, $cfg->lang);
        $this->assertSame(['en', 'de', 'fr'], $cfg->lang);
    }

    public static function secureModeProvider(): array
    {
        return array_map(
            fn(SecureMode $m) => [$m],
            SecureMode::cases(),
        );
    }

    #[DataProvider('secureModeProvider')]
    public function testAllSecureModes(SecureMode $mode): void
    {
        $cfg = new ConnectionConfig('user', 'pass', secure: $mode);
        $this->assertSame($mode, $cfg->secure);
    }
}
