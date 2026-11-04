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
 * The IMAP `fetch()` result value object:
 * {@see MessageMetadata} for scalar metadata,
 * {@see MessageContent} for raw stream content,
 * {@see PartAccess} for MIME part addressing and
 * {@see ParsedAccess} for OO envelope/headers/structure.
 * {@see Pop3MessageData} implements only the first two since POP3 has no wire-level part addressing or structure. IMAP has both.
 *
 * Section-addressed content (header text, body text, MIME header and
 * body parts) is stored keyed by MIME id. Whole message content uses the id `0`,
 * matching {@see MessageContent}'s default argument.
 *
 * OO representations reuse the `horde/mime` and `horde/mail` value objects
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

    /** @var array<int|string, StreamInterface|null> Server-decoded BINARY sections. */
    private array $binaryBodyPart = [];

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

    public function setBinaryBodyPart(int|string $id, ?string $data): void
    {
        $this->binaryBodyPart[$id] = $data === null ? null : $this->toStream($data);
    }

    /** Null means no decoded section was returned (including a NIL response). */
    public function getBinaryBodyPart(int|string $id): ?StreamInterface
    {
        return $this->binaryBodyPart[$id] ?? null;
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
        // Preserve the earlier parser's BINARY-only getBodyPart behavior while
        // keeping encoded BODY and decoded BINARY distinct when both arrive.
        return $this->bodyPart[$id] ?? $this->binaryBodyPart[$id] ?? $this->toStream('');
    }

    public function getMimeHeader(string $id): StreamInterface
    {
        return $this->mimeHeader[$id] ?? $this->toStream('');
    }

    /**
     * The reported octet size of a body part (`BINARY.SIZE`/`BODY[id]`
     * length) or null if it was not fetched.
     */
    public function getBodyPartSize(int|string $id): ?int
    {
        return $this->bodyPartSize[$id] ?? null;
    }

    /**
     * Yields numerically addressable parts depth-first, keyed by valid IMAP
     * sections. Includes a non-multipart root as section 1. Containers and
     * descendants may overlap in content; use getLeafParts() for payloads only.
     * Empty if no structure was fetched. The complete tree is getStructure().
     *
     * @see https://www.rfc-editor.org/rfc/rfc3501#section-6.4.5 IMAP section addressing.
     * @return Generator<string, Part>
     */
    public function getParts(): Generator
    {
        if ($this->structure === null) {
            return;
        }

        if ($this->structure->isMultipart()) {
            yield from $this->iterateParts($this->structure, '');
        } else {
            yield '1' => $this->structure;
            yield from $this->iterateParts($this->structure, '1.');
        }
    }

    /**
     * Yields terminal non-multipart payloads once, without enclosing messages.
     *
     * @see https://www.rfc-editor.org/rfc/rfc3501#section-6.4.5 Numeric fetch addresses.
     * @return Generator<string, Part>
     */
    public function getLeafParts(): Generator
    {
        foreach ($this->getParts() as $id => $part) {
            if ($part->isLeaf() && !$part->isMultipart()) {
                yield $id => $part;
            }
        }
    }

    /**
     * Migration aid
     *
     * Skips the root and includes Horde's synthetic X.0 containers.
     * X.0 is not fetchable. Request the embedded body with bodyText(X) instead.
     *
     * @see https://www.rfc-editor.org/rfc/rfc3501#section-9 Non-Zero wire section numbers.
     * @return Generator<string, Part>
     */
    public function getPartsH5(): Generator
    {
        if ($this->structure === null) {
            return;
        }

        $prefix = $this->structure->fullType() === 'message/rfc822' ? '1.' : '';
        yield from $this->iterateParts($this->structure, $prefix, true);
    }

    /**
     * @see https://www.rfc-editor.org/rfc/rfc3501#section-9 Non-Zero wire section numbers.
     *  X.0 is a Horde convention not part of the spec.
     * @return Generator<string, Part>
     */
    private function iterateParts(Part $parent, string $prefix, bool $includeSynthetic = false): Generator
    {
        foreach ($parent->children as $index => $child) {
            if ($parent->fullType() === 'message/rfc822' && $child->isMultipart()) {
                // Embedded multipart containers do not add a section level.
                if ($includeSynthetic) {
                    yield $prefix . '0' => $child;
                }
                yield from $this->iterateParts($child, $prefix, $includeSynthetic);
            } else {
                $id = $prefix . ($index + 1);
                yield $id => $child;
                yield from $this->iterateParts($child, $id . '.', $includeSynthetic);
            }
        }
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
     * The raw (unparsed) header text stored for a label or null if the
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
