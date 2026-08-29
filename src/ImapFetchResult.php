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

namespace Horde\Imap\Client;

use DateTimeImmutable;
use Generator;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Part;
use Horde\Stream\StreamInterface;
use Horde\Stream\Temp;

/**
 * The IMAP `fetch()` result value object: {@see MessageMetadata} (scalar metadata),
 * {@see MessageContent} (raw stream content), {@see PartAccess} (MIME
 * part addressing) and {@see ParsedAccess} (OO envelope/headers/
 * structure). {@see Pop3MessageData} implements only the first two since
 * POP3 has no wire-level part addressing or structure. IMAP has both.
 *
 * Section-addressed content (header text, body text, MIME header and
 * body parts) is stored keyed by MIME id. Whole-message content uses the id `0`,
 * matching {@see MessageContent}'s default argument.
 *
 * OO representations reuse the `horde/mime` and `horde/mail` value
 * objects rather than inventing parallels: {@see getStructure()} returns
 * a {@see \Horde\Mime\Part} tree, {@see getHeaders()} a
 * {@see \Horde\Mime\Headers\HeaderCollection}, and {@see getEnvelope()}
 * an {@see ImapEnvelope} whose addresses are `Horde\Mail\Rfc822` types.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapFetchResult implements MessageMetadata, MessageContent, PartAccess, ParsedAccess
{
    private int|string|null $uid = null;

    /** @var list<string> */
    private array $flags = [];

    private int $size = 0;

    private ?DateTimeImmutable $imapDate = null;

    private ?int $modSeq = null;

    private ?StreamInterface $fullMsg = null;

    /** @var array<int|string, StreamInterface> */
    private array $headerText = [];

    /** @var array<int|string, StreamInterface> */
    private array $bodyText = [];

    /** @var array<int|string, StreamInterface> */
    private array $mimeHeader = [];

    /** @var array<int|string, StreamInterface> */
    private array $bodyPart = [];

    /** @var array<int|string, int> */
    private array $bodyPartSize = [];

    /** @var array<string, StreamInterface> Selective header groups by label. */
    private array $headers = [];

    private ?ImapEnvelope $envelope = null;

    private ?Part $structure = null;

    public function __construct(
        private readonly int $seq,
    ) {}

    // -- Setters (driven by ImapFetchParser) --

    public function setUid(int|string $uid): void
    {
        $this->uid = $uid;
    }

    /**
     * @param list<string> $flags
     */
    public function setFlags(array $flags): void
    {
        $this->flags = $flags;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function setImapDate(?DateTimeImmutable $date): void
    {
        $this->imapDate = $date;
    }

    public function setModSeq(?int $modSeq): void
    {
        $this->modSeq = $modSeq;
    }

    public function setFullMsg(string $data): void
    {
        $this->fullMsg = $this->toStream($data);
    }

    public function setHeaderText(int|string $id, string $data): void
    {
        $this->headerText[$id] = $this->toStream($data);
    }

    public function setBodyText(int|string $id, string $data): void
    {
        $this->bodyText[$id] = $this->toStream($data);
    }

    public function setMimeHeader(int|string $id, string $data): void
    {
        $this->mimeHeader[$id] = $this->toStream($data);
    }

    public function setBodyPart(int|string $id, string $data): void
    {
        $this->bodyPart[$id] = $this->toStream($data);
    }

    public function setBodyPartSize(int|string $id, int $size): void
    {
        $this->bodyPartSize[$id] = $size;
    }

    public function setHeaders(string $label, string $data): void
    {
        $this->headers[$label] = $this->toStream($data);
    }

    public function setEnvelope(ImapEnvelope $envelope): void
    {
        $this->envelope = $envelope;
    }

    public function setStructure(Part $structure): void
    {
        $this->structure = $structure;
    }

    // -- MessageMetadata --

    public function getUid(): int|string
    {
        return $this->uid ?? $this->seq;
    }

    /**
     * @return list<string>
     */
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
        return $this->imapDate ?? new DateTimeImmutable('@0');
    }

    public function getSeq(): int
    {
        return $this->seq;
    }

    public function getModSeq(): ?int
    {
        return $this->modSeq;
    }

    // -- MessageContent --

    public function getFullMsg(): StreamInterface
    {
        return $this->fullMsg ?? $this->toStream('');
    }

    public function getHeaderText(string|int $id = 0): StreamInterface
    {
        return $this->headerText[$id] ?? $this->toStream('');
    }

    public function getBodyText(string|int $id = 0): StreamInterface
    {
        return $this->bodyText[$id] ?? $this->toStream('');
    }

    // -- PartAccess --

    public function getBodyPart(string $id): StreamInterface
    {
        return $this->bodyPart[$id] ?? $this->toStream('');
    }

    public function getMimeHeader(string $id): StreamInterface
    {
        return $this->mimeHeader[$id] ?? $this->toStream('');
    }

    /**
     * The reported octet size of a body part (`BINARY.SIZE`/`BODY[id]`
     * length) or null if it was not fetched.
     */
    public function getBodyPartSize(string $id): ?int
    {
        return $this->bodyPartSize[$id] ?? null;
    }

    /**
     * Yields the MIME parts of the structure lazily (RFC 3501 §7.4.2
     * `BODYSTRUCTURE`), skipping the top-level message part itself. Empty
     * if no structure was fetched.
     *
     * @return Generator<Part>
     */
    public function getParts(): Generator
    {
        if ($this->structure === null) {
            return;
        }

        yield from $this->structure->iterate(false);
    }

    // -- ParsedAccess --

    public function getEnvelope(): ImapEnvelope
    {
        return $this->envelope ??= new ImapEnvelope();
    }

    /**
     * The named header group requested via {@see ImapFetchQuery::headers()},
     * parsed into a {@see HeaderCollection}. An unknown label yields an
     * empty collection.
     */
    public function getHeaders(string $label): HeaderCollection
    {
        $stream = $this->headers[$label] ?? null;

        return HeaderCollection::parse($stream !== null ? (string) $stream : '');
    }

    /**
     * @return Generator<\Horde\Mime\Headers\HeaderElement>
     */
    public function getHeadersIterator(string $label): Generator
    {
        yield from $this->getHeaders($label)->all();
    }

    /**
     * The raw (unparsed) header text stored for a label, or null if the
     * label was not fetched. Used by the cache layer, which stores the
     * raw text and reconstructs the {@see HeaderCollection} on read via
     * {@see getHeaders()}.
     */
    public function getRawHeaders(string $label): ?string
    {
        return isset($this->headers[$label]) ? (string) $this->headers[$label] : null;
    }

    /**
     * The header-group labels present on this result.
     *
     * @return list<string>
     */
    public function headerLabels(): array
    {
        return array_keys($this->headers);
    }

    public function getStructure(): Part
    {
        return $this->structure ??= new Part();
    }

    private function toStream(string $data): StreamInterface
    {
        $stream = new Temp();
        $stream->add($data, true);

        return $stream;
    }
}
