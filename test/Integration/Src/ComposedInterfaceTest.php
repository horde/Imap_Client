<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Integration\Src;

use DateTimeImmutable;
use Generator;
use Horde\Imap\Client\CapabilityInterface;
use Horde\Imap\Client\ImapAclAware;
use Horde\Imap\Client\ImapMetadataAware;
use Horde\Imap\Client\ImapProtocol;
use Horde\Imap\Client\ImapQuotaAware;
use Horde\Imap\Client\MailboxListMode;
use Horde\Imap\Client\MessageContent;
use Horde\Imap\Client\MessageIdSet;
use Horde\Imap\Client\MessageMetadata;
use Horde\Imap\Client\OpenMode;
use Horde\Imap\Client\ParsedAccess;
use Horde\Imap\Client\PartAccess;
use Horde\Imap\Client\Test\Stub\StubMessageIdSet;
use Horde_Stream;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversNothing]
class ComposedInterfaceTest extends TestCase
{
    public function testClassCanImplementImapProtocolAndExtensions(): void
    {
        $stub = new class implements ImapProtocol, ImapQuotaAware, ImapAclAware, ImapMetadataAware {
            // MailboxProtocol
            public function login(): void {}
            public function logout(): void {}
            public function noop(): void {}
            public function status(string $mailbox, int $flags): object
            {
                return new stdClass();
            }
            public function fetch(string $mailbox, MessageIdSet $ids, object $query): Generator
            {
                yield from [];
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
            // ImapProtocol
            public function getCapability(): CapabilityInterface
            {
                return new class implements CapabilityInterface {
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
            // ImapQuotaAware
            public function setQuota(string $root, array $resources): void {}
            public function getQuota(string $root): array
            {
                return [];
            }
            public function getQuotaRoot(string $mailbox): array
            {
                return [];
            }
            // ImapAclAware
            public function getACL(string $mailbox): object
            {
                return new stdClass();
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
            // ImapMetadataAware
            public function getMetadata(string $mailbox, array $entries, array $options = []): array
            {
                return [];
            }
            public function setMetadata(string $mailbox, array $data): void {}
        };

        $this->assertInstanceOf(ImapProtocol::class, $stub);
        $this->assertInstanceOf(ImapQuotaAware::class, $stub);
        $this->assertInstanceOf(ImapAclAware::class, $stub);
        $this->assertInstanceOf(ImapMetadataAware::class, $stub);

        // Exercise one method from each interface
        $stub->login();
        $stub->openMailbox('INBOX', OpenMode::ReadWrite);
        $stub->setQuota('', []);
        $stub->deleteACL('INBOX', 'user');
        $stub->setMetadata('INBOX', []);
        $this->addToAssertionCount(1);
    }

    public function testMessageCanImplementMetadataAndContent(): void
    {
        $stub = new class implements MessageMetadata, MessageContent {
            public function getUid(): int|string
            {
                return 1;
            }
            public function getFlags(): array
            {
                return [];
            }
            public function getSize(): int
            {
                return 0;
            }
            public function getImapDate(): DateTimeImmutable
            {
                return new DateTimeImmutable();
            }
            public function getSeq(): ?int
            {
                return null;
            }
            public function getModSeq(): ?int
            {
                return null;
            }
            public function getFullMsg(): Horde_Stream
            {
                return new Horde_Stream();
            }
            public function getHeaderText(string|int $id = 0): Horde_Stream
            {
                return new Horde_Stream();
            }
            public function getBodyText(string|int $id = 0): Horde_Stream
            {
                return new Horde_Stream();
            }
        };

        $this->assertInstanceOf(MessageMetadata::class, $stub);
        $this->assertInstanceOf(MessageContent::class, $stub);
        $this->assertSame(1, $stub->getUid());
        $this->assertInstanceOf(Horde_Stream::class, $stub->getFullMsg());
    }

    public function testMessageCanImplementAllFourLayers(): void
    {
        $stub = new class implements MessageMetadata, MessageContent, PartAccess, ParsedAccess {
            public function getUid(): int|string
            {
                return 42;
            }
            public function getFlags(): array
            {
                return ['\\Seen'];
            }
            public function getSize(): int
            {
                return 2048;
            }
            public function getImapDate(): DateTimeImmutable
            {
                return new DateTimeImmutable();
            }
            public function getSeq(): ?int
            {
                return 1;
            }
            public function getModSeq(): ?int
            {
                return null;
            }
            public function getFullMsg(): Horde_Stream
            {
                return new Horde_Stream();
            }
            public function getHeaderText(string|int $id = 0): Horde_Stream
            {
                return new Horde_Stream();
            }
            public function getBodyText(string|int $id = 0): Horde_Stream
            {
                return new Horde_Stream();
            }
            public function getBodyPart(string $id): Horde_Stream
            {
                return new Horde_Stream();
            }
            public function getMimeHeader(string $id): Horde_Stream
            {
                return new Horde_Stream();
            }
            public function getParts(): Generator
            {
                yield '1' => new stdClass();
            }
            public function getEnvelope(): object
            {
                return new stdClass();
            }
            public function getHeaders(string $label): object
            {
                return new stdClass();
            }
            public function getHeadersIterator(string $label): Generator
            {
                yield new stdClass();
            }
            public function getStructure(): object
            {
                return new stdClass();
            }
        };

        $this->assertInstanceOf(MessageMetadata::class, $stub);
        $this->assertInstanceOf(MessageContent::class, $stub);
        $this->assertInstanceOf(PartAccess::class, $stub);
        $this->assertInstanceOf(ParsedAccess::class, $stub);

        $this->assertSame(42, $stub->getUid());
        $this->assertInstanceOf(Horde_Stream::class, $stub->getBodyPart('1'));
        $this->assertIsObject($stub->getEnvelope());
    }
}
