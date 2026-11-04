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
use Horde\Imap\Client\ImapEnvelope;
use Horde\Imap\Client\ImapFetchQuery;
use Horde\Imap\Client\ImapFetchResult;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Part;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapFetchResult::class)]
class ImapFetchResultTest extends TestCase
{
    public function testUidFallsBackToSequenceWhenUnset(): void
    {
        $result = new ImapFetchResult(7);

        self::assertSame(7, $result->getUid());
        self::assertSame(7, $result->getSeq());

        $result->setUid(4211);

        self::assertSame(4211, $result->getUid());
    }

    public function testScalarMetadataRoundTrips(): void
    {
        $result = new ImapFetchResult(1);
        $result->setFlags(['\Seen', '\Answered']);
        $result->setSize(2048);
        $result->setModSeq(12345);
        $date = new DateTimeImmutable('2026-01-02 03:04:05');
        $result->setImapDate($date);

        self::assertSame(['\Seen', '\Answered'], $result->getFlags());
        self::assertSame(2048, $result->getSize());
        self::assertSame(12345, $result->getModSeq());
        self::assertSame($date->getTimestamp(), $result->getImapDate()->getTimestamp());
    }

    public function testDefaultsForUnsetMetadata(): void
    {
        $result = new ImapFetchResult(1);

        self::assertSame([], $result->getFlags());
        self::assertSame(0, $result->getSize());
        self::assertNull($result->getModSeq());
        // Unset INTERNALDATE reads as the epoch.
        self::assertSame(0, $result->getImapDate()->getTimestamp());
    }

    public function testContentStreamsRoundTripAsStrings(): void
    {
        $result = new ImapFetchResult(1);
        $result->setFullMsg("Subject: x\r\n\r\nbody");
        $result->setHeaderText(0, 'Subject: x');
        $result->setBodyText(0, 'body');
        $result->setHeaderText('1', 'Content-Type: text/plain');
        $result->setBodyText('1', 'part body');

        self::assertSame("Subject: x\r\n\r\nbody", (string) $result->getFullMsg());
        self::assertSame('Subject: x', (string) $result->getHeaderText());
        self::assertSame('body', (string) $result->getBodyText());
        self::assertSame('Content-Type: text/plain', (string) $result->getHeaderText('1'));
        self::assertSame('part body', (string) $result->getBodyText('1'));
    }

    public function testUnsetContentDefaultsToEmptyStream(): void
    {
        $result = new ImapFetchResult(1);

        self::assertSame('', (string) $result->getFullMsg());
        self::assertSame('', (string) $result->getHeaderText());
        self::assertSame('', (string) $result->getBodyText(3));
    }

    public function testPartAccessStoresBodyPartsAndMimeHeadersById(): void
    {
        $result = new ImapFetchResult(1);
        $result->setBodyPart('1', 'first');
        $result->setBodyPart('2', 'second');
        $result->setMimeHeader('2', 'Content-Type: image/png');
        $result->setBodyPartSize('1', 5);

        self::assertSame('first', (string) $result->getBodyPart('1'));
        self::assertSame('second', (string) $result->getBodyPart('2'));
        self::assertSame('Content-Type: image/png', (string) $result->getMimeHeader('2'));
        self::assertSame(5, $result->getBodyPartSize('1'));
        self::assertNull($result->getBodyPartSize('2'));
    }

    public function testEnvelopeDefaultsToEmptyWhenUnset(): void
    {
        $result = new ImapFetchResult(1);

        $envelope = $result->getEnvelope();

        self::assertSame('', $envelope->subject);
        self::assertSame('', $envelope->date);
    }

    public function testEnvelopeRoundTrips(): void
    {
        $result = new ImapFetchResult(1);
        $result->setEnvelope(new ImapEnvelope(subject: 'Hello', messageId: '<a@b>'));

        self::assertSame('Hello', $result->getEnvelope()->subject);
        self::assertSame('<a@b>', $result->getEnvelope()->messageId);
    }

    public function testHeadersParseIntoAHeaderCollectionByLabel(): void
    {
        $result = new ImapFetchResult(1);
        $result->setHeaders('std', "From: alice@a.com\r\nSubject: Hi\r\n");

        $headers = $result->getHeaders('std');

        self::assertInstanceOf(HeaderCollection::class, $headers);
        self::assertSame('alice@a.com', $headers->get('From')->value());
        self::assertSame('Hi', $headers->get('Subject')->value());
    }

    public function testUnknownHeaderLabelYieldsEmptyCollection(): void
    {
        $result = new ImapFetchResult(1);

        self::assertCount(0, $result->getHeaders('missing')->all());
    }

    public function testGetPartsIsEmptyWithoutStructure(): void
    {
        $result = new ImapFetchResult(1);

        self::assertSame([], iterator_to_array($result->getParts()));
        self::assertSame([], iterator_to_array($result->getLeafParts()));
        self::assertSame([], iterator_to_array($result->getPartsH5()));
    }

    /** RFC 3501 §6.4.5: A non-multipart message has section 1, even without child nodes. */
    public function testSinglePartRootIsFetchableButExcludedFromLegacyTraversal(): void
    {
        $result = new ImapFetchResult(1);
        $root = new Part(headers: HeaderCollection::parse("Content-Type: text/plain\r\n"));
        $result->setStructure($root);

        self::assertSame([1 => $root], iterator_to_array($result->getParts()));
        self::assertSame([1 => $root], iterator_to_array($result->getLeafParts()));
        self::assertSame([], iterator_to_array($result->getPartsH5()));
    }

    /** RFC 3501 §6.4.5: Leaf sections omit enclosing messages and multipart containers. */
    public function testLeafPartsCanBeFetchedWithoutOverlappingContainers(): void
    {
        $part = static fn(string $type, array $children = []): Part => new Part(
            headers: HeaderCollection::parse('Content-Type: ' . $type . "\r\n"),
            children: $children,
        );
        $plain = $part('text/plain');
        $html = $part('text/html');
        $pdf = $part('application/pdf');
        $nested = $part('message/rfc822', [$pdf]);
        $alternative = $part('multipart/alternative', [$plain, $html]);
        $embedded = $part('multipart/mixed', [$alternative, $nested]);
        $message = $part('message/rfc822', [$embedded]);
        $root = $part('multipart/mixed', [$message, $part('multipart/mixed')]);
        $result = new ImapFetchResult(1);
        $result->setStructure($root);

        self::assertSame([
            1 => $message,
            '1.1' => $alternative,
            '1.1.1' => $plain,
            '1.1.2' => $html,
            '1.2' => $nested,
            '1.2.1' => $pdf,
            2 => $root->children[1],
        ], iterator_to_array($result->getParts()));
        self::assertSame([
            '1.1.1' => $plain,
            '1.1.2' => $html,
            '1.2.1' => $pdf,
        ], iterator_to_array($result->getLeafParts()));

        $query = new ImapFetchQuery();
        foreach ($result->getLeafParts() as $section => $payload) {
            $query->bodyPart($section);
        }
        self::assertSame([
            'BODY.PEEK[1.1.1]',
            'BODY.PEEK[1.1.2]',
            'BODY.PEEK[1.2.1]',
        ], $query->wireItems());
        self::assertSame($root, $result->getStructure());
        self::assertSame($embedded, iterator_to_array($result->getPartsH5())['1.0']);
    }

    public function testGetPartsYieldsCanonicalMimeIdKeyedChildren(): void
    {
        $result = new ImapFetchResult(1);
        $child1 = new Part(headers: HeaderCollection::parse("Content-Type: text/plain\r\n"));
        $child2 = new Part(headers: HeaderCollection::parse("Content-Type: text/html\r\n"));
        $result->setStructure(new Part(
            headers: HeaderCollection::parse("Content-Type: multipart/alternative\r\n"),
            children: [$child1, $child2],
        ));

        $parts = [];

        foreach ($result->getParts() as $id => $part) {
            $parts[$id] = $part->fullType();
        }

        self::assertSame(
            ['1' => 'text/plain', '2' => 'text/html'],
            $parts,
        );
    }
}
