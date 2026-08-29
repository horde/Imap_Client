<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Integration\Src;

use Horde\Imap\Client\ImapAclAware;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversNothing]
class ImapAclAwareTest extends TestCase
{
    private function createImplementation(): ImapAclAware
    {
        return new class implements ImapAclAware {
            public function getACL(string $mailbox): array
            {
                return [];
            }
            public function setACL(string $mailbox, string $identifier, array $options): void {}
            public function deleteACL(string $mailbox, string $identifier): void {}
            public function listACLRights(string $mailbox, string $identifier): object
            {
                return new stdClass();
            }
            public function getMyACLRights(string $mailbox): object
            {
                return new stdClass();
            }
        };
    }

    public function testGetACLReturnsArray(): void
    {
        $this->assertIsArray($this->createImplementation()->getACL('INBOX'));
    }

    public function testSetACLReturnsVoid(): void
    {
        $this->createImplementation()->setACL('INBOX', 'user', ['rights' => 'lrs']);
        $this->addToAssertionCount(1);
    }

    public function testDeleteACLReturnsVoid(): void
    {
        $this->createImplementation()->deleteACL('INBOX', 'user');
        $this->addToAssertionCount(1);
    }

    public function testListACLRightsReturnsObject(): void
    {
        $this->assertIsObject($this->createImplementation()->listACLRights('INBOX', 'user'));
    }

    public function testGetMyACLRightsReturnsObject(): void
    {
        $this->assertIsObject($this->createImplementation()->getMyACLRights('INBOX'));
    }

    public function testInstanceOfImapAclAware(): void
    {
        $this->assertInstanceOf(ImapAclAware::class, $this->createImplementation());
    }
}
