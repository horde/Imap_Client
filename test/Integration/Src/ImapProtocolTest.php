<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Integration\Src;

use Generator;
use Horde\Imap\Client\Capability;
use Horde\Imap\Client\ImapProtocol;
use Horde\Imap\Client\MailboxListMode;
use Horde\Imap\Client\MailboxProtocol;
use Horde\Imap\Client\MessageIdSet;
use Horde\Imap\Client\OpenMode;
use Horde\Imap\Client\Test\Stub\StubMessageIdSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversNothing]
class ImapProtocolTest extends TestCase
{
    private function createImplementation(): ImapProtocol
    {
        return new class implements ImapProtocol {
            // MailboxProtocol methods
            public function login(): void {}
            public function logout(): void {}
            public function noop(): void {}
            public function status(string $mailbox, int $flags): object
            {
                return new stdClass();
            }
            public function fetch(string $mailbox, MessageIdSet $ids, object $query): Generator
            {
                yield 1 => new stdClass();
            }
            public function store(string $mailbox, array $options): MessageIdSet
            {
                return new StubMessageIdSet();
            }
            public function expunge(string $mailbox, array $options): MessageIdSet
            {
                return new StubMessageIdSet();
            }
            public function getIdsOb(mixed $ids = null, bool $sequence = false): MessageIdSet
            {
                return new StubMessageIdSet();
            }

            // ImapProtocol methods
            public function getCapability(): Capability
            {
                return new class implements Capability {
                    public function query(string $capability, ?string $parameter = null): bool
                    {
                        return false;
                    }
                    public function getParams(string $capability): array
                    {
                        return [];
                    }
                };
            }
            public function openMailbox(string $mailbox, OpenMode $mode): void {}
            public function createMailbox(string $mailbox): void {}
            public function deleteMailbox(string $mailbox): void {}
            public function renameMailbox(string $old, string $new): void {}
            public function subscribeMailbox(string $mailbox, bool $subscribe = true): void {}
            public function listMailboxes(string $pattern, MailboxListMode $mode, array $options = []): array
            {
                return [];
            }
            public function close(array $options = []): void {}
            public function search(string $mailbox, object $query, array $options = []): object
            {
                return new stdClass();
            }
            public function thread(string $mailbox, array $options = []): object
            {
                return new stdClass();
            }
            public function copy(string $source, string $dest, array $options = []): MessageIdSet
            {
                return new StubMessageIdSet();
            }
            public function move(string $source, string $dest, array $options = []): MessageIdSet
            {
                return new StubMessageIdSet();
            }
            public function append(string $mailbox, array $data, array $options = []): MessageIdSet
            {
                return new StubMessageIdSet();
            }
            public function getNamespaces(): object
            {
                return new stdClass();
            }
            public function unselect(): void {}
        };
    }

    public function testImplementsMailboxProtocol(): void
    {
        $this->assertInstanceOf(MailboxProtocol::class, $this->createImplementation());
    }

    public function testImplementsImapProtocol(): void
    {
        $this->assertInstanceOf(ImapProtocol::class, $this->createImplementation());
    }

    public function testGetCapabilityReturnsCapability(): void
    {
        $this->assertInstanceOf(Capability::class, $this->createImplementation()->getCapability());
    }

    public function testOpenMailboxAcceptsOpenModeEnum(): void
    {
        $this->createImplementation()->openMailbox('INBOX', OpenMode::ReadWrite);
        $this->addToAssertionCount(1);
    }

    public function testCreateDeleteRenameMailbox(): void
    {
        $stub = $this->createImplementation();
        $stub->createMailbox('Test');
        $stub->renameMailbox('Test', 'Archive');
        $stub->deleteMailbox('Archive');
        $this->addToAssertionCount(1);
    }

    public function testSubscribeMailbox(): void
    {
        $stub = $this->createImplementation();
        $stub->subscribeMailbox('INBOX', true);
        $stub->subscribeMailbox('INBOX');
        $this->addToAssertionCount(1);
    }

    public function testListMailboxesAcceptsMailboxListModeEnum(): void
    {
        $result = $this->createImplementation()->listMailboxes('*', MailboxListMode::All);
        $this->assertIsArray($result);
    }

    public function testSearchReturnsObject(): void
    {
        $this->assertIsObject($this->createImplementation()->search('INBOX', new stdClass()));
    }

    public function testThreadReturnsObject(): void
    {
        $this->assertIsObject($this->createImplementation()->thread('INBOX'));
    }

    public function testCopyReturnsMessageIdSet(): void
    {
        $this->assertInstanceOf(MessageIdSet::class, $this->createImplementation()->copy('INBOX', 'Archive'));
    }

    public function testMoveReturnsMessageIdSet(): void
    {
        $this->assertInstanceOf(MessageIdSet::class, $this->createImplementation()->move('INBOX', 'Archive'));
    }

    public function testAppendReturnsMessageIdSet(): void
    {
        $this->assertInstanceOf(MessageIdSet::class, $this->createImplementation()->append('INBOX', []));
    }

    public function testGetNamespacesReturnsObject(): void
    {
        $this->assertIsObject($this->createImplementation()->getNamespaces());
    }

    public function testCloseAndUnselect(): void
    {
        $stub = $this->createImplementation();
        $stub->close();
        $stub->unselect();
        $this->addToAssertionCount(1);
    }
}
