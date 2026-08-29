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
use Horde\Stream\StreamInterface;
use Horde\Stream\Temp;

/**
 * POP3's `MessageMetadata`/`MessageContent` value object.
 *
 * POP3 has no MIME part addressin and no flag or mod-sequence concept beyond
 * the client's own locally-tracked `\Deleted` marks. RFC 1939 has no
 * FETCH-style flag report at all. `getFlags()` is always empty and
 * `getModSeq()` is always null here. Header/body text is still keyed by
 * an `$id`, matching `MessageContent`'s shape, even though POP3 only ever
 * populates the whole-message key (`0`); legacy code plumbed the same key
 * through for symmetry with the IMAP fetch pipeline, not because POP3 has
 * addressable sub-parts.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class Pop3MessageData implements MessageMetadata, MessageContent
{
    private ?StreamInterface $fullMsg = null;

    /** @var array<string|int, StreamInterface> */
    private array $headerText = [];

    /** @var array<string|int, StreamInterface> */
    private array $bodyText = [];

    private int $size = 0;

    private ?DateTimeImmutable $imapDate = null;

    public function __construct(
        private readonly int|string $uid,
        private readonly ?int $seq = null,
    ) {}

    public function setFullMsg(string $data): void
    {
        $this->fullMsg = $this->toStream($data);
    }

    public function setHeaderText(string|int $id, string $data): void
    {
        $this->headerText[$id] = $this->toStream($data);
    }

    public function setBodyText(string|int $id, string $data): void
    {
        $this->bodyText[$id] = $this->toStream($data);
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function setImapDate(?DateTimeImmutable $date): void
    {
        $this->imapDate = $date;
    }

    public function getUid(): int|string
    {
        return $this->uid;
    }

    public function getFlags(): array
    {
        return [];
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getImapDate(): DateTimeImmutable
    {
        return $this->imapDate ?? new DateTimeImmutable('@0');
    }

    public function getSeq(): ?int
    {
        return $this->seq;
    }

    public function getModSeq(): ?int
    {
        return null;
    }

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

    private function toStream(string $data): StreamInterface
    {
        $stream = new Temp();
        $stream->add($data, true);

        return $stream;
    }
}
