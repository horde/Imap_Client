<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Integration\Src;

use Horde\Imap\Client\ImapMetadataAware;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class ImapMetadataAwareTest extends TestCase
{
    private function createImplementation(): ImapMetadataAware
    {
        return new class implements ImapMetadataAware {
            public function getMetadata(string $mailbox, array $entries, array $options = []): array
            {
                return ['/shared/comment' => 'test'];
            }

            public function setMetadata(string $mailbox, array $data): void {}
        };
    }

    public function testGetMetadataReturnsArray(): void
    {
        $this->assertIsArray($this->createImplementation()->getMetadata('INBOX', ['/shared/comment']));
    }

    public function testGetMetadataWithOptions(): void
    {
        $result = $this->createImplementation()->getMetadata('INBOX', ['/shared/comment'], ['DEPTH' => 0]);
        $this->assertIsArray($result);
    }

    public function testSetMetadataReturnsVoid(): void
    {
        $this->createImplementation()->setMetadata('INBOX', ['/shared/comment' => 'test']);
        $this->addToAssertionCount(1);
    }

    public function testInstanceOfImapMetadataAware(): void
    {
        $this->assertInstanceOf(ImapMetadataAware::class, $this->createImplementation());
    }
}
