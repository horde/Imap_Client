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

use Horde\Imap\Client\ImapFetchQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapFetchQuery::class)]
class ImapFetchQueryTest extends TestCase
{
    public function testStructureEnvelopeFlagsModseqEmitBareAtoms(): void
    {
        $query = (new ImapFetchQuery())
            ->structure()
            ->envelope()
            ->flags()
            ->modseq();

        self::assertSame(
            ['BODYSTRUCTURE', 'ENVELOPE', 'FLAGS', 'MODSEQ'],
            $query->wireItems(),
        );
        self::assertTrue($query->wantsStructure());
        self::assertTrue($query->wantsEnvelope());
        self::assertTrue($query->wantsFlags());
        self::assertTrue($query->wantsModSeq());
    }

    public function testWireItemsPreserveFirstRequestedOrder(): void
    {
        $query = (new ImapFetchQuery())
            ->flags()
            ->envelope()
            ->structure();

        self::assertSame(['FLAGS', 'ENVELOPE', 'BODYSTRUCTURE'], $query->wireItems());
    }

    public function testDuplicateItemsAreDeduplicated(): void
    {
        $query = (new ImapFetchQuery())
            ->flags()
            ->flags()
            ->structure();

        self::assertSame(['FLAGS', 'BODYSTRUCTURE'], $query->wireItems());
    }

    public function testHeadersEmitsPeekFieldListAndUppercasesNames(): void
    {
        $query = (new ImapFetchQuery())->headers('std', ['From', 'to', 'Subject']);

        self::assertSame(
            ['BODY.PEEK[HEADER.FIELDS (FROM TO SUBJECT)]'],
            $query->wireItems(),
        );
    }

    public function testHeadersNotUsesHeaderFieldsNot(): void
    {
        $query = (new ImapFetchQuery())->headers('rest', ['Received'], not: true);

        self::assertSame(
            ['BODY.PEEK[HEADER.FIELDS.NOT (RECEIVED)]'],
            $query->wireItems(),
        );
    }

    public function testHeaderLabelRoundTrips(): void
    {
        $query = (new ImapFetchQuery())->headers('std', ['From', 'To']);

        self::assertSame('std', $query->headerLabelFor('HEADER.FIELDS (FROM TO)'));
        self::assertNull($query->headerLabelFor('HEADER.FIELDS (FROM CC)'));
    }

    public function testHeaderTextWholeMessageVsPart(): void
    {
        $query = (new ImapFetchQuery())
            ->headerText()
            ->headerText(2);

        self::assertSame(
            ['BODY.PEEK[HEADER]', 'BODY.PEEK[2.HEADER]'],
            $query->wireItems(),
        );
    }

    public function testBodyTextWholeMessageVsPart(): void
    {
        $query = (new ImapFetchQuery())
            ->bodyText()
            ->bodyText('1.2');

        self::assertSame(
            ['BODY.PEEK[TEXT]', 'BODY.PEEK[1.2.TEXT]'],
            $query->wireItems(),
        );
    }

    public function testMimeHeader(): void
    {
        $query = (new ImapFetchQuery())->mimeHeader(2);

        self::assertSame(['BODY.PEEK[2.MIME]'], $query->wireItems());
    }

    public function testBodyPartWithPartialRange(): void
    {
        $query = (new ImapFetchQuery())
            ->bodyPart('1')
            ->bodyPart('2', 0, 1024)
            ->bodyPart('3', 100);

        self::assertSame(
            [
                'BODY.PEEK[1]',
                'BODY.PEEK[2]<0.1024>',
                'BODY.PEEK[3]<100>',
            ],
            $query->wireItems(),
        );
    }

    public function testFullMsgWithAndWithoutRange(): void
    {
        $query = (new ImapFetchQuery())->fullMsg();

        self::assertSame(['BODY.PEEK[]'], $query->wireItems());
        self::assertTrue($query->wantsFullMsg());

        $ranged = (new ImapFetchQuery())->fullMsg(0, 512);

        self::assertSame(['BODY.PEEK[]<0.512>'], $ranged->wireItems());
    }

    public function testPeekFalseEmitsNonPeekingBodyItem(): void
    {
        $query = (new ImapFetchQuery())->bodyText(0, peek: false);

        self::assertSame(['BODY[TEXT]'], $query->wireItems());
    }

    public function testInheritedScalarFieldsDoNotEmitWireItems(): void
    {
        // uid/size/seq/imapDate are request flags the client turns into
        // their own wire atoms; they are not BODY[...] items recorded here.
        $query = (new ImapFetchQuery())
            ->uid()
            ->size()
            ->seq()
            ->imapDate();

        self::assertSame([], $query->wireItems());
        self::assertTrue($query->wantsUid());
        self::assertTrue($query->wantsSize());
        self::assertTrue($query->wantsSeq());
        self::assertTrue($query->wantsImapDate());
    }
}
