<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Integration\Src;

use Horde\Imap\Client\ImapQuotaAware;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class ImapQuotaAwareTest extends TestCase
{
    private function createImplementation(): ImapQuotaAware
    {
        return new class implements ImapQuotaAware {
            public function setQuota(string $root, array $resources): void {}
            public function getQuota(string $root): array
            {
                return ['STORAGE' => [1024, 10240]];
            }
            public function getQuotaRoot(string $mailbox): array
            {
                return ['INBOX' => ''];
            }
        };
    }

    public function testSetQuotaReturnsVoid(): void
    {
        $this->createImplementation()->setQuota('', ['STORAGE' => 10240]);
        $this->addToAssertionCount(1);
    }

    public function testGetQuotaReturnsArray(): void
    {
        $this->assertIsArray($this->createImplementation()->getQuota(''));
    }

    public function testGetQuotaRootReturnsArray(): void
    {
        $this->assertIsArray($this->createImplementation()->getQuotaRoot('INBOX'));
    }

    public function testInstanceOfImapQuotaAware(): void
    {
        $this->assertInstanceOf(ImapQuotaAware::class, $this->createImplementation());
    }
}
