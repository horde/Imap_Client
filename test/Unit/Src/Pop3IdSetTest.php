<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\Pop3IdSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pop3IdSet::class)]
class Pop3IdSetTest extends TestCase
{
    public function testEmptySet(): void
    {
        $ids = new Pop3IdSet();

        self::assertTrue($ids->isEmpty());
        self::assertSame(0, $ids->count());
        self::assertSame([], $ids->toArray());
        self::assertSame('', (string) $ids);
    }

    public function testDeduplicatesWhilePreservingFirstSeenOrder(): void
    {
        $ids = new Pop3IdSet(['uidl-b', 'uidl-a', 'uidl-b', 'uidl-c']);

        self::assertSame(['uidl-b', 'uidl-a', 'uidl-c'], $ids->toArray());
        self::assertSame(3, $ids->count());
    }

    public function testToStringJoinsWithSpace(): void
    {
        $ids = new Pop3IdSet(['uidl-a', 'uidl-b']);

        self::assertSame('uidl-a uidl-b', (string) $ids);
    }

    public function testIsSequenceFlag(): void
    {
        $sequence = new Pop3IdSet([1, 2, 3], true);
        $uids = new Pop3IdSet(['uidl-a'], false);

        self::assertTrue($sequence->isSequence());
        self::assertFalse($uids->isSequence());
    }

    public function testIterationYieldsIds(): void
    {
        $ids = new Pop3IdSet(['uidl-a', 'uidl-b']);

        self::assertSame(['uidl-a', 'uidl-b'], iterator_to_array($ids->getIterator()));
    }

    public function testFromSequenceStringParsesSpaceDelimited(): void
    {
        $ids = Pop3IdSet::fromSequenceString('uidl-a uidl-b uidl-c');

        self::assertSame(['uidl-a', 'uidl-b', 'uidl-c'], $ids->toArray());
    }

    public function testFromSequenceStringHandlesEmptyString(): void
    {
        $ids = Pop3IdSet::fromSequenceString('');

        self::assertTrue($ids->isEmpty());
    }

    public function testFromSequenceStringTrimsSurroundingWhitespace(): void
    {
        $ids = Pop3IdSet::fromSequenceString('  1 2 3  ');

        // Numeric-looking UIDs are int-cast by PHP's array-key coercion
        // (array_flip/array_keys), exactly like the legacy Ids_Pop3 class.
        // Harmless since MessageIdSet's type is int|string.
        self::assertSame([1, 2, 3], $ids->toArray());
    }
}
