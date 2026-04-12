<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Integration\Src;

use Horde\Imap\Client\PasswordInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class PasswordInterfaceTest extends TestCase
{
    public function testAnonymousClassImplementation(): void
    {
        $pw = new class implements PasswordInterface {
            public function getPassword(): string
            {
                return 's3cret';
            }
        };

        $this->assertInstanceOf(PasswordInterface::class, $pw);
        $this->assertSame('s3cret', $pw->getPassword());
    }

    public function testEmptyPasswordReturn(): void
    {
        $pw = new class implements PasswordInterface {
            public function getPassword(): string
            {
                return '';
            }
        };

        $this->assertSame('', $pw->getPassword());
    }
}
