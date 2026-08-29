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

/**
 * The genuinely protocol-agnostic slice of a fetch query.
 *
 * Mirrors exactly the fields both IMAP and POP3 can serve *without* faking
 * server-side capability through local reparsing. This is what `MessageMetadata`/`MessageContent` already expose
 * (full message, header text, body text, UID, size, sequence number,
 * date).
 * Structure, envelope, MIME-part addressing, header filtering, flags and
 * mod-sequence are deliberately NOT here: POP3 has no wire-level support
 * for any of them. Legacy code faked them by re-parsing a fully RETR'd
 * message with `Horde_Mime_Part`. For POP3 this functionality belongs into another layer.
 * An IMAP-specific fetch query can have them instead.
 *
 * `Pop3FetchQuery` and a future `ImapFetchQuery` are logically unrelated classes sharing no inheritance tree or interface.
 * This trait is restricted to the overlapping field-builder logic so either can diverge freely later without an LSP violation or a BC break.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
trait FetchQueryFields
{
    private bool $wantFullMsg = false;

    private ?int $fullMsgStart = null;

    private ?int $fullMsgLength = null;

    /** @var array<int|string, true> */
    private array $headerTextIds = [];

    /** @var array<int|string, true> */
    private array $bodyTextIds = [];

    private bool $wantUid = false;

    private bool $wantSize = false;

    private bool $wantSeq = false;

    private bool $wantImapDate = false;

    /**
     * Request the full raw message or optionally a byte range of it.
     */
    public function fullMsg(?int $start = null, ?int $length = null): static
    {
        $this->wantFullMsg = true;
        $this->fullMsgStart = $start;
        $this->fullMsgLength = $length;

        return $this;
    }

    public function headerText(int|string $id = 0): static
    {
        $this->headerTextIds[$id] = true;

        return $this;
    }

    public function bodyText(int|string $id = 0): static
    {
        $this->bodyTextIds[$id] = true;

        return $this;
    }

    public function uid(): static
    {
        $this->wantUid = true;

        return $this;
    }

    public function size(): static
    {
        $this->wantSize = true;

        return $this;
    }

    public function seq(): static
    {
        $this->wantSeq = true;

        return $this;
    }

    public function imapDate(): static
    {
        $this->wantImapDate = true;

        return $this;
    }

    public function wantsFullMsg(): bool
    {
        return $this->wantFullMsg;
    }

    /**
     * @return array{start: ?int, length: ?int}
     */
    public function fullMsgRange(): array
    {
        return ['start' => $this->fullMsgStart, 'length' => $this->fullMsgLength];
    }

    /**
     * @return list<int|string>
     */
    public function headerTextIds(): array
    {
        return array_keys($this->headerTextIds);
    }

    /**
     * @return list<int|string>
     */
    public function bodyTextIds(): array
    {
        return array_keys($this->bodyTextIds);
    }

    public function wantsUid(): bool
    {
        return $this->wantUid;
    }

    public function wantsSize(): bool
    {
        return $this->wantSize;
    }

    public function wantsSeq(): bool
    {
        return $this->wantSeq;
    }

    public function wantsImapDate(): bool
    {
        return $this->wantImapDate;
    }
}
