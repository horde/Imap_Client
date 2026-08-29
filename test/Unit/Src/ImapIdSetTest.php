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

use Horde\Imap\Client\ImapIdSet;
use Horde\Imap\Client\ImapIdSetToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapIdSet::class)]
#[CoversClass(ImapIdSetToken::class)]
class ImapIdSetTest extends TestCase
{
    public function testEmptySet(): void
    {
        $ids = new ImapIdSet();

        self::assertTrue($ids->isEmpty());
        self::assertFalse($ids->isSpecial());
        self::assertSame(0, $ids->count());
        self::assertSame([], $ids->toArray());
        self::assertSame('', (string) $ids);
        self::assertNull($ids->min());
        self::assertNull($ids->max());
        self::assertNull($ids->token());
    }

    public function testExplicitListDeduplicatesPreservingFirstSeenOrder(): void
    {
        $ids = new ImapIdSet([3, 1, 3, 2, 1]);

        self::assertSame([3, 1, 2], $ids->toArray());
        self::assertSame(3, $ids->count());
        self::assertFalse($ids->isEmpty());
    }

    public function testNumericStringsAreCastToInt(): void
    {
        $ids = new ImapIdSet(['5', '7', 9]);

        self::assertSame([5, 7, 9], $ids->toArray());
    }

    public function testIterationYieldsIds(): void
    {
        $ids = new ImapIdSet([1, 2, 3]);

        self::assertSame([1, 2, 3], iterator_to_array($ids->getIterator()));
    }

    public function testMinMax(): void
    {
        $ids = new ImapIdSet([7, 2, 9, 4]);

        self::assertSame(2, $ids->min());
        self::assertSame(9, $ids->max());
    }

    /**
     * @param list<int> $input
     */
    #[DataProvider('sequenceStringProvider')]
    public function testToStringCompressesRanges(array $input, string $expected): void
    {
        self::assertSame($expected, (string) new ImapIdSet($input));
    }

    /**
     * @return iterable<string, array{list<int>, string}>
     */
    public static function sequenceStringProvider(): iterable
    {
        yield 'single' => [[5], '5'];
        yield 'contiguous run' => [[1, 2, 3, 4, 5], '1:5'];
        yield 'run then gap' => [[1, 2, 3, 7], '1:3,7'];
        yield 'mixed ranges and singletons' => [[1, 2, 3, 5, 7, 8, 9], '1:3,5,7:9'];
        yield 'unsorted input is sorted' => [[9, 1, 3, 2], '1:3,9'];
        yield 'two element range' => [[4, 5], '4:5'];
        yield 'non adjacent singletons' => [[2, 4, 6], '2,4,6'];
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('parseProvider')]
    public function testFromSequenceStringParsesRanges(string $input, array $expected): void
    {
        $ids = ImapIdSet::fromSequenceString($input);

        self::assertSame($expected, $ids->toArray());
        self::assertFalse($ids->isSpecial());
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function parseProvider(): iterable
    {
        yield 'empty' => ['', []];
        yield 'whitespace only' => ['   ', []];
        yield 'single' => ['5', [5]];
        yield 'range' => ['1:5', [1, 2, 3, 4, 5]];
        yield 'mixed' => ['1:3,7,9:11', [1, 2, 3, 7, 9, 10, 11]];
        yield 'reversed range is normalized' => ['5:1', [1, 2, 3, 4, 5]];
        yield 'surrounding whitespace trimmed' => ['  1:3  ', [1, 2, 3]];
    }

    public function testRoundTripThroughSequenceString(): void
    {
        $original = '1:3,7,9:11';
        $ids = ImapIdSet::fromSequenceString($original);

        self::assertSame($original, (string) $ids);
    }

    public function testConstructFromSequenceString(): void
    {
        $ids = new ImapIdSet('2,4:6');

        self::assertSame([2, 4, 5, 6], $ids->toArray());
        self::assertSame('2,4:6', (string) $ids);
    }

    public function testConstructFromSingleInt(): void
    {
        $ids = new ImapIdSet(42);

        self::assertSame([42], $ids->toArray());
        self::assertSame('42', (string) $ids);
    }

    /**
     * @param non-empty-string $wire
     */
    #[DataProvider('specialTokenProvider')]
    public function testSpecialTokens(ImapIdSetToken $token, string $wire): void
    {
        $ids = new ImapIdSet($token);

        self::assertTrue($ids->isSpecial());
        self::assertSame($token, $ids->token());
        self::assertSame($wire, (string) $ids);
        // A special set has no concrete list to enumerate.
        self::assertSame([], $ids->toArray());
        self::assertSame(0, $ids->count());
        // isEmpty() is false: it represents messages, just not by list.
        self::assertFalse($ids->isEmpty());
    }

    /**
     * @return iterable<string, array{ImapIdSetToken, string}>
     */
    public static function specialTokenProvider(): iterable
    {
        yield 'all' => [ImapIdSetToken::All, '1:*'];
        yield 'largest' => [ImapIdSetToken::Largest, '*'];
        yield 'search result' => [ImapIdSetToken::SearchRes, '$'];
    }

    /**
     * @param non-empty-string $wire
     */
    #[DataProvider('specialTokenProvider')]
    public function testFromSequenceStringRecognizesSpecialTokens(ImapIdSetToken $token, string $wire): void
    {
        $ids = ImapIdSet::fromSequenceString($wire);

        self::assertTrue($ids->isSpecial());
        self::assertSame($token, $ids->token());
        self::assertSame($wire, (string) $ids);
    }

    public function testSequenceFlag(): void
    {
        $seq = new ImapIdSet([1, 2, 3], true);
        $uid = new ImapIdSet([1, 2, 3]);

        self::assertTrue($seq->isSequence());
        self::assertFalse($uid->isSequence());
    }

    public function testAddReturnsNewSetAndIsImmutable(): void
    {
        $ids = new ImapIdSet([1, 2, 3]);
        $more = $ids->add([3, 4, 5]);

        self::assertSame([1, 2, 3], $ids->toArray());
        self::assertSame([1, 2, 3, 4, 5], $more->toArray());
        self::assertNotSame($ids, $more);
    }

    public function testAddAcceptsSequenceString(): void
    {
        $ids = (new ImapIdSet([1]))->add('3:5');

        self::assertSame([1, 3, 4, 5], $ids->toArray());
    }

    public function testRemoveReturnsNewSet(): void
    {
        $ids = new ImapIdSet([1, 2, 3, 4, 5]);
        $fewer = $ids->remove([2, 4]);

        self::assertSame([1, 2, 3, 4, 5], $ids->toArray());
        self::assertSame([1, 3, 5], $fewer->toArray());
    }

    public function testRemoveFromSpecialSetIsNoOp(): void
    {
        $ids = new ImapIdSet(ImapIdSetToken::All);
        $result = $ids->remove([1, 2]);

        self::assertTrue($result->isSpecial());
        self::assertSame(ImapIdSetToken::All, $result->token());
    }

    public function testAddToSpecialSetProducesExplicitList(): void
    {
        $ids = new ImapIdSet(ImapIdSetToken::All);
        $result = $ids->add([1, 2]);

        self::assertFalse($result->isSpecial());
        self::assertSame([1, 2], $result->toArray());
    }

    public function testSequenceFlagPreservedThroughAdd(): void
    {
        $ids = (new ImapIdSet([1], true))->add([2]);

        self::assertTrue($ids->isSequence());
    }
}
