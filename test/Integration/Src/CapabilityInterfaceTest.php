<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Integration\Src;

use Horde\Imap\Client\CapabilityInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class CapabilityInterfaceTest extends TestCase
{
    private function createImplementation(): CapabilityInterface
    {
        return new class implements CapabilityInterface {
            public function query(string $capability, ?string $parameter = null): bool
            {
                if ($capability === 'IMAP4rev1' && $parameter === null) {
                    return true;
                }
                if ($capability === 'AUTH' && $parameter === 'PLAIN') {
                    return true;
                }
                return false;
            }

            public function getParams(string $capability): array
            {
                if ($capability === 'AUTH') {
                    return ['PLAIN', 'LOGIN'];
                }
                return [];
            }
        };
    }

    public function testQueryWithoutParameter(): void
    {
        $cap = $this->createImplementation();
        $this->assertTrue($cap->query('IMAP4rev1'));
        $this->assertFalse($cap->query('CONDSTORE'));
    }

    public function testQueryWithParameter(): void
    {
        $cap = $this->createImplementation();
        $this->assertTrue($cap->query('AUTH', 'PLAIN'));
        $this->assertFalse($cap->query('AUTH', 'CRAM-MD5'));
    }

    public function testGetParamsReturnsStringArray(): void
    {
        $cap = $this->createImplementation();
        $this->assertSame(['PLAIN', 'LOGIN'], $cap->getParams('AUTH'));
    }

    public function testGetParamsEmptyArray(): void
    {
        $cap = $this->createImplementation();
        $this->assertSame([], $cap->getParams('UNKNOWN'));
    }
}
