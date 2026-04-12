<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Integration\Src;

use Generator;
use Horde\Imap\Client\MailboxProtocol;
use Horde\Imap\Client\MessageIdSet;
use Horde\Imap\Client\Test\Stub\StubMessageIdSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversNothing]
class MailboxProtocolTest extends TestCase
{
    private function createImplementation(): MailboxProtocol
    {
        return new class implements MailboxProtocol {
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
                return new StubMessageIdSet(is_array($ids) ? $ids : []);
            }
        };
    }

    public function testLoginReturnsVoid(): void
    {
        $this->createImplementation()->login();
        $this->addToAssertionCount(1);
    }

    public function testLogoutReturnsVoid(): void
    {
        $this->createImplementation()->logout();
        $this->addToAssertionCount(1);
    }

    public function testNoopReturnsVoid(): void
    {
        $this->createImplementation()->noop();
        $this->addToAssertionCount(1);
    }

    public function testStatusReturnsObject(): void
    {
        $this->assertIsObject($this->createImplementation()->status('INBOX', 0));
    }

    public function testFetchReturnsGenerator(): void
    {
        $gen = $this->createImplementation()->fetch('INBOX', new StubMessageIdSet([1]), new stdClass());
        $this->assertInstanceOf(Generator::class, $gen);
    }

    public function testFetchYieldsResults(): void
    {
        $gen = $this->createImplementation()->fetch('INBOX', new StubMessageIdSet([1]), new stdClass());
        $items = iterator_to_array($gen);
        $this->assertCount(1, $items);
    }

    public function testStoreReturnsMessageIdSet(): void
    {
        $this->assertInstanceOf(MessageIdSet::class, $this->createImplementation()->store('INBOX', []));
    }

    public function testExpungeReturnsMessageIdSet(): void
    {
        $this->assertInstanceOf(MessageIdSet::class, $this->createImplementation()->expunge('INBOX', []));
    }

    public function testGetIdsObReturnsMessageIdSet(): void
    {
        $this->assertInstanceOf(MessageIdSet::class, $this->createImplementation()->getIdsOb());
    }

    public function testGetIdsObWithParameters(): void
    {
        $ids = $this->createImplementation()->getIdsOb([1, 2, 3], true);
        $this->assertInstanceOf(MessageIdSet::class, $ids);
    }

    public function testStubInstanceOfMailboxProtocol(): void
    {
        $this->assertInstanceOf(MailboxProtocol::class, $this->createImplementation());
    }
}
