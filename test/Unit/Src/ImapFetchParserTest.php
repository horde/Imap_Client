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

use Horde\Imap\Client\ImapFetchParser;
use Horde\Imap\Client\ImapFetchQuery;
use Horde\Imap\Client\ImapFetchResult;
use Horde\Imap\Client\ImapResponse;
use Horde\Imap\Client\ImapResponseParser;
use Horde\Imap\Client\ImapTokenizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapFetchParser::class)]
class ImapFetchParserTest extends TestCase
{
    /**
     * Tokenize and response-parse a single wire line, exactly as the live
     * connection would, so the parser is exercised against real
     * {@see ImapTokenizer} output rather than hand-built token arrays.
     */
    private function response(string $line): ImapResponse
    {
        $socket = new InMemoryImapSocket($line . "\r\n");
        $tokenizer = new ImapTokenizer($socket);

        return ImapResponseParser::parse($tokenizer->readLine());
    }

    private function parse(string $line, ?ImapFetchQuery $query = null): ImapFetchResult
    {
        $parsed = ImapFetchParser::parse($this->response($line), $query ?? new ImapFetchQuery());
        self::assertNotNull($parsed);

        return $parsed['result'];
    }

    public function testNonFetchResponseReturnsNull(): void
    {
        $parsed = ImapFetchParser::parse($this->response('* 1 EXISTS'), new ImapFetchQuery());

        self::assertNull($parsed);
    }

    public function testTaggedResponseReturnsNull(): void
    {
        $parsed = ImapFetchParser::parse($this->response('A1 OK FETCH completed.'), new ImapFetchQuery());

        self::assertNull($parsed);
    }

    public function testSequenceNumberAndUid(): void
    {
        $parsed = ImapFetchParser::parse($this->response('* 5 FETCH (UID 4211)'), new ImapFetchQuery());

        self::assertNotNull($parsed);
        self::assertSame(5, $parsed['seq']);
        self::assertSame(4211, $parsed['result']->getUid());
    }

    public function testFlags(): void
    {
        $result = $this->parse('* 1 FETCH (FLAGS (\Seen \Answered $label1))');

        self::assertSame(['\Seen', '\Answered', '$label1'], $result->getFlags());
    }

    public function testRfc822Size(): void
    {
        $result = $this->parse('* 1 FETCH (RFC822.SIZE 8642)');

        self::assertSame(8642, $result->getSize());
    }

    public function testInternalDate(): void
    {
        $result = $this->parse('* 1 FETCH (INTERNALDATE "17-Jul-1996 02:44:25 -0700")');

        self::assertSame('1996-07-17 02:44:25', $result->getImapDate()->format('Y-m-d H:i:s'));
    }

    public function testMalformedInternalDateFallsBackToEpoch(): void
    {
        $result = $this->parse('* 1 FETCH (INTERNALDATE "not-a-date")');

        self::assertSame(0, $result->getImapDate()->getTimestamp());
    }

    public function testModSeq(): void
    {
        $result = $this->parse('* 1 FETCH (MODSEQ (624140003))');

        self::assertSame(624140003, $result->getModSeq());
    }

    public function testEnvelopeFieldsAndAddresses(): void
    {
        $env = '("Wed, 17 Jul 1996 02:23:25 -0700" "Test subject" '
            . '(("Alice" NIL "alice" "a.com")) '  // from
            . 'NIL '                               // sender (falls back to from)
            . 'NIL '                               // reply-to (falls back to from)
            . '(("Bob" NIL "bob" "b.com")) '       // to
            . 'NIL NIL '                           // cc, bcc
            . '"<reply@a.com>" "<msg1@a.com>")';   // in-reply-to, message-id
        $result = $this->parse('* 1 FETCH (ENVELOPE ' . $env . ')');

        $envelope = $result->getEnvelope();
        self::assertSame('Wed, 17 Jul 1996 02:23:25 -0700', $envelope->date);
        self::assertSame('Test subject', $envelope->subject);
        self::assertSame('alice@a.com', $envelope->from->addresses()[0]->bareAddress());
        self::assertSame('bob@b.com', $envelope->to->addresses()[0]->bareAddress());
        self::assertSame('<reply@a.com>', $envelope->inReplyTo);
        self::assertSame('<msg1@a.com>', $envelope->messageId);
    }

    public function testEnvelopeNilSenderAndReplyToFallBackToFrom(): void
    {
        $env = '(NIL NIL (("Alice" NIL "alice" "a.com")) NIL NIL NIL NIL NIL NIL NIL)';
        $result = $this->parse('* 1 FETCH (ENVELOPE ' . $env . ')');

        $envelope = $result->getEnvelope();
        self::assertSame('alice@a.com', $envelope->sender->addresses()[0]->bareAddress());
        self::assertSame('alice@a.com', $envelope->replyTo->addresses()[0]->bareAddress());
    }

    public function testEnvelopeAddressGroup(): void
    {
        // A group: start marker (host NIL, mailbox non-NIL), one member,
        // end marker (host NIL, mailbox NIL). RFC 3501 §7.4.2.
        $to = '((NIL NIL "friends" NIL) ("Bob" NIL "bob" "b.com") (NIL NIL NIL NIL))';
        $env = '(NIL NIL NIL NIL NIL ' . $to . ' NIL NIL NIL NIL)';
        $result = $this->parse('* 1 FETCH (ENVELOPE ' . $env . ')');

        // The group is preserved as a Group in the list. addresses()
        // flattens groups, so iterate the list to see the group itself.
        $items = iterator_to_array($result->getEnvelope()->to);
        self::assertCount(1, $items);
        self::assertInstanceOf(\Horde\Mail\Rfc822\Group::class, $items[0]);
        self::assertSame('friends', $items[0]->groupname);
        self::assertSame('bob@b.com', $items[0]->addresses->addresses()[0]->bareAddress());
    }

    public function testBodyStructureSinglePart(): void
    {
        $bs = '("text" "plain" ("charset" "utf-8") NIL NIL "7bit" 128 4)';
        $result = $this->parse('* 1 FETCH (BODYSTRUCTURE ' . $bs . ')', (new ImapFetchQuery())->structure());

        $structure = $result->getStructure();
        self::assertSame('text/plain', $structure->fullType());
        self::assertSame('utf-8', $structure->charset());
    }

    public function testBodyStructureMultipartYieldsCanonicalIds(): void
    {
        $bs = '(("text" "plain" ("charset" "utf-8") NIL NIL "7bit" 100 5)'
            . '("text" "html" NIL NIL NIL "base64" 200 8) "alternative")';
        $result = $this->parse('* 2 FETCH (BODYSTRUCTURE ' . $bs . ')', (new ImapFetchQuery())->structure());

        self::assertSame('multipart/alternative', $result->getStructure()->fullType());

        $parts = [];

        foreach ($result->getParts() as $id => $part) {
            $parts[$id] = $part->fullType();
        }

        self::assertSame(['1' => 'text/plain', '2' => 'text/html'], $parts);
    }

    public function testBareBodyIsTreatedAsBodyStructure(): void
    {
        $result = $this->parse(
            '* 1 FETCH (BODY ("text" "plain" NIL NIL NIL "7bit" 10 1))',
            (new ImapFetchQuery())->structure(),
        );

        self::assertSame('text/plain', $result->getStructure()->fullType());
    }

    public function testWholeMessageBodySection(): void
    {
        $body = "Subject: hi\r\n\r\nHello";
        $line = '* 1 FETCH (BODY[] {' . strlen($body) . "}\r\n" . $body . ')';
        $result = $this->parse($line);

        self::assertSame($body, (string) $result->getFullMsg());
    }

    public function testHeaderTextSection(): void
    {
        $header = "Subject: hi\r\n";
        $line = '* 1 FETCH (BODY[HEADER] {' . strlen($header) . "}\r\n" . $header . ')';
        $result = $this->parse($line);

        self::assertSame($header, (string) $result->getHeaderText());
    }

    public function testPartTextAndMimeSections(): void
    {
        $line = '* 1 FETCH (BODY[1.TEXT] "text body" BODY[1.MIME] "Content-Type: text/plain")';
        $result = $this->parse($line);

        self::assertSame('text body', (string) $result->getBodyText('1'));
        self::assertSame('Content-Type: text/plain', (string) $result->getMimeHeader('1'));
    }

    public function testRawBodyPartSection(): void
    {
        $result = $this->parse('* 1 FETCH (BODY[2] "raw part")');

        self::assertSame('raw part', (string) $result->getBodyPart('2'));
    }

    public function testPartialOffsetSuffixIsStrippedFromSection(): void
    {
        $result = $this->parse('* 1 FETCH (BODY[1]<0> "partial")');

        self::assertSame('partial', (string) $result->getBodyPart('1'));
    }

    public function testHeaderFieldsMapBackToCallerLabel(): void
    {
        $query = (new ImapFetchQuery())->headers('std', ['From', 'To']);
        $headerText = "From: a@b\r\nTo: c@d\r\n";
        $line = '* 1 FETCH (BODY[HEADER.FIELDS (FROM TO)] {'
            . strlen($headerText) . "}\r\n" . $headerText . ')';
        $result = $this->parse($line, $query);

        $headers = $result->getHeaders('std');
        self::assertSame('a@b', $headers->get('From')->value());
        self::assertSame('c@d', $headers->get('To')->value());
    }

    public function testRfc822AliasesResolveToBodyEquivalents(): void
    {
        $line = '* 1 FETCH (RFC822.HEADER "Subject: x" RFC822.TEXT "body" RFC822 "whole")';
        $result = $this->parse($line);

        self::assertSame('Subject: x', (string) $result->getHeaderText());
        self::assertSame('body', (string) $result->getBodyText());
        self::assertSame('whole', (string) $result->getFullMsg());
    }

    public function testUnknownItemsAreIgnored(): void
    {
        $result = $this->parse('* 1 FETCH (UID 9 XSOMETHING "ignored" FLAGS (\Seen))');

        self::assertSame(9, $result->getUid());
        self::assertSame(['\Seen'], $result->getFlags());
    }

    public function testMultipleItemsInOneResponse(): void
    {
        $line = '* 3 FETCH (UID 100 FLAGS (\Seen) RFC822.SIZE 42 INTERNALDATE "17-Jul-1996 02:44:25 -0700")';
        $result = $this->parse($line);

        self::assertSame(100, $result->getUid());
        self::assertSame(['\Seen'], $result->getFlags());
        self::assertSame(42, $result->getSize());
        self::assertSame('1996-07-17', $result->getImapDate()->format('Y-m-d'));
    }
}
