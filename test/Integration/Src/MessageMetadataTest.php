<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Integration\Src;

use DateTimeImmutable;
use Horde\Imap\Client\MessageMetadata;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class MessageMetadataTest extends TestCase
{
    private function createImplementation(
        int|string $uid = 42,
        array $flags = ['\\Seen'],
        int $size = 1024,
        ?DateTimeImmutable $date = null,
        ?int $seq = 7,
        ?int $modSeq = 12345,
    ): MessageMetadata {
        $date ??= new DateTimeImmutable('2026-01-15 10:30:00');
        return new class ($uid, $flags, $size, $date, $seq, $modSeq) implements MessageMetadata {
            public function __construct(
                private readonly int|string $uid,
                private readonly array $flags,
                private readonly int $size,
                private readonly DateTimeImmutable $date,
                private readonly ?int $seq,
                private readonly ?int $modSeq,
            ) {}

            public function getUid(): int|string
            {
                return $this->uid;
            }
            public function getFlags(): array
            {
                return $this->flags;
            }
            public function getSize(): int
            {
                return $this->size;
            }
            public function getImapDate(): DateTimeImmutable
            {
                return $this->date;
            }
            public function getSeq(): ?int
            {
                return $this->seq;
            }
            public function getModSeq(): ?int
            {
                return $this->modSeq;
            }
        };
    }

    public function testAllMethodReturnTypes(): void
    {
        $stub = $this->createImplementation();

        $this->assertSame(42, $stub->getUid());
        $this->assertSame(['\\Seen'], $stub->getFlags());
        $this->assertSame(1024, $stub->getSize());
        $this->assertSame('2026-01-15 10:30:00', $stub->getImapDate()->format('Y-m-d H:i:s'));
        $this->assertSame(7, $stub->getSeq());
        $this->assertSame(12345, $stub->getModSeq());
    }

    public function testStringUid(): void
    {
        $stub = $this->createImplementation(uid: 'msg-abc');
        $this->assertIsString($stub->getUid());
        $this->assertSame('msg-abc', $stub->getUid());
    }

    public function testNullableSeq(): void
    {
        $stub = $this->createImplementation(seq: null);
        $this->assertNull($stub->getSeq());
    }

    public function testNullableModSeq(): void
    {
        $stub = $this->createImplementation(modSeq: null);
        $this->assertNull($stub->getModSeq());
    }

    public function testEmptyFlags(): void
    {
        $stub = $this->createImplementation(flags: []);
        $this->assertSame([], $stub->getFlags());
    }

    public function testDateTimeImmutableType(): void
    {
        $stub = $this->createImplementation();
        $this->assertInstanceOf(DateTimeImmutable::class, $stub->getImapDate());
    }
}
