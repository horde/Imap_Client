<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Integration\Src;

use Horde\Imap\Client\MessageIdSet;
use Horde\Imap\Client\Test\Stub\StubMessageIdSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class MessageIdSetTest extends TestCase
{
    public function testCountableContract(): void
    {
        $set = new StubMessageIdSet([1, 2, 3]);
        $this->assertCount(3, $set);
    }

    public function testIteratorAggregateContract(): void
    {
        $set = new StubMessageIdSet([1, 2, 3]);
        $collected = [];
        foreach ($set as $id) {
            $collected[] = $id;
        }
        $this->assertSame([1, 2, 3], $collected);
    }

    public function testIsEmptyWhenEmpty(): void
    {
        $set = new StubMessageIdSet();
        $this->assertTrue($set->isEmpty());
    }

    public function testIsEmptyWhenNotEmpty(): void
    {
        $set = new StubMessageIdSet([42]);
        $this->assertFalse($set->isEmpty());
    }

    public function testToArrayReturnsArray(): void
    {
        $set = new StubMessageIdSet([1, 2, 3]);
        $this->assertSame([1, 2, 3], $set->toArray());
    }

    public function testToStringReturnsString(): void
    {
        $set = new StubMessageIdSet([1, 2, 3]);
        $this->assertSame('1,2,3', (string) $set);
    }

    public function testStringIdSet(): void
    {
        $set = new StubMessageIdSet(['abc', 'def']);
        $this->assertCount(2, $set);
        $this->assertSame(['abc', 'def'], $set->toArray());
        $this->assertSame('abc,def', (string) $set);
        $this->assertFalse($set->isEmpty());
    }

    public function testCountZeroWhenEmpty(): void
    {
        $set = new StubMessageIdSet();
        $this->assertSame(0, count($set));
    }

    public function testInstanceOfMessageIdSet(): void
    {
        $set = new StubMessageIdSet();
        $this->assertInstanceOf(MessageIdSet::class, $set);
    }
}
