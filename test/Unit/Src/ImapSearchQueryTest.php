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

use DateTimeImmutable;
use Horde\Imap\Client\ImapIdSet;
use Horde\Imap\Client\ImapSearchQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The {@see ImapSearchQuery} criteria builder.
 */
#[CoversClass(ImapSearchQuery::class)]
class ImapSearchQueryTest extends TestCase
{
    /**
     * Render a built query's criteria the way the command layer would:
     * each token escaped, a list wrapped in parentheses.
     *
     * @param ImapSearchQuery $query
     */
    private function render(ImapSearchQuery $query): string
    {
        $built = $query->build();
        $parts = [];

        foreach ($built['criteria'] as $token) {
            $escaped = $token->escape();
            $parts[] = $token instanceof \Horde\Imap\Client\ImapWireList ? "({$escaped})" : $escaped;
        }

        return implode(' ', $parts);
    }

    public function testSystemFlagRendersAsSingleToken(): void
    {
        self::assertSame('SEEN', $this->render((new ImapSearchQuery())->flag('\\Seen')));
        self::assertSame('UNSEEN', $this->render((new ImapSearchQuery())->flag('Seen', false)));
        self::assertSame('FLAGGED', $this->render((new ImapSearchQuery())->flag('\\Flagged')));
    }

    public function testKeywordRendersAsPair(): void
    {
        self::assertSame('KEYWORD $IMPORTANT', $this->render((new ImapSearchQuery())->flag('$Important')));
        self::assertSame('UNKEYWORD NOSPAM', $this->render((new ImapSearchQuery())->flag('NoSpam', false)));
    }

    public function testHeaderWellKnownAndGeneric(): void
    {
        self::assertSame('SUBJECT hello', $this->render((new ImapSearchQuery())->header('Subject', 'hello')));
        self::assertSame(
            'HEADER X-SPAM YES',
            $this->render((new ImapSearchQuery())->header('X-Spam', 'YES')),
        );
    }

    public function testHeaderNotNegates(): void
    {
        self::assertSame('NOT FROM bob', $this->render((new ImapSearchQuery())->header('From', 'bob', not: true)));
    }

    public function testTextBodyAndFullText(): void
    {
        self::assertSame('BODY needle', $this->render((new ImapSearchQuery())->text('needle')));
        self::assertSame('TEXT needle', $this->render((new ImapSearchQuery())->text('needle', bodyOnly: false)));
    }

    public function testTextWithSpacesQuotes(): void
    {
        self::assertSame('BODY "two words"', $this->render((new ImapSearchQuery())->text('two words')));
    }

    public function testSize(): void
    {
        self::assertSame('LARGER 1024', $this->render((new ImapSearchQuery())->size(1024)));
        self::assertSame('SMALLER 512', $this->render((new ImapSearchQuery())->size(512, larger: false)));
    }

    public function testIdsUidAndSequence(): void
    {
        $uid = new ImapIdSet([4, 5, 6], false);
        self::assertSame('UID 4:6', $this->render((new ImapSearchQuery())->ids($uid)));

        $seq = new ImapIdSet([1, 2, 3], true);
        self::assertSame('1:3', $this->render((new ImapSearchQuery())->ids($seq)));
    }

    public function testDateVariants(): void
    {
        $date = new DateTimeImmutable('2024-02-01T00:00:00Z');

        self::assertSame('SINCE 1-Feb-2024', $this->render((new ImapSearchQuery())->date($date)));
        self::assertSame(
            'SENTBEFORE 1-Feb-2024',
            $this->render((new ImapSearchQuery())->date($date, 'BEFORE', sent: true)),
        );
    }

    public function testWithin(): void
    {
        self::assertSame('YOUNGER 3600', $this->render((new ImapSearchQuery())->within(3600)));
        self::assertSame('OLDER 86400', $this->render((new ImapSearchQuery())->within(86400, younger: false)));
    }

    public function testModseq(): void
    {
        self::assertSame('MODSEQ 720', $this->render((new ImapSearchQuery())->modseq(720)));
        self::assertSame(
            'MODSEQ "/flags/\\\\draft" all 720',
            $this->render((new ImapSearchQuery())->modseq(720, '/flags/\\draft', 'all')),
        );
    }

    public function testPreviousSearch(): void
    {
        self::assertSame('$', $this->render((new ImapSearchQuery())->previousSearch()));
        self::assertSame('NOT $', $this->render((new ImapSearchQuery())->previousSearch(not: true)));
    }

    public function testImplicitAndConcatenates(): void
    {
        $query = (new ImapSearchQuery())->flag('\\Seen')->size(1024)->header('Subject', 'x');

        self::assertSame('SEEN LARGER 1024 SUBJECT x', $this->render($query));
    }

    public function testNotMatchingWrapsGroup(): void
    {
        $inner = (new ImapSearchQuery())->flag('\\Deleted');
        $query = (new ImapSearchQuery())->flag('\\Seen')->notMatching($inner);

        self::assertSame('SEEN NOT (DELETED)', $this->render($query));
    }

    public function testOrWithWrapsBothSides(): void
    {
        $query = (new ImapSearchQuery())->flag('\\Seen');
        $query->orWith((new ImapSearchQuery())->flag('\\Flagged'));

        self::assertSame('OR (SEEN) (FLAGGED)', $this->render($query));
    }

    public function testNonAsciiTextSetsUtf8Charset(): void
    {
        $query = (new ImapSearchQuery())->text('naïve');
        $built = $query->build();

        self::assertSame('UTF-8', $built['charset']);
    }

    public function testAsciiOnlyQueryHasNullCharset(): void
    {
        $built = (new ImapSearchQuery())->flag('\\Seen')->text('plain')->build();

        self::assertNull($built['charset']);
    }

    public function testExplicitCharsetOverrides(): void
    {
        $built = (new ImapSearchQuery())->charset('iso-8859-1')->text('plain')->build();

        self::assertSame('ISO-8859-1', $built['charset']);
    }

    public function testEmptyQueryHasNoCriteria(): void
    {
        self::assertSame([], (new ImapSearchQuery())->build()['criteria']);
    }

    public function testFuzzyPrefixesFollowingCriterion(): void
    {
        self::assertSame('FUZZY BODY mispeld', $this->render((new ImapSearchQuery())->fuzzy()->text('mispeld')));
    }
}
