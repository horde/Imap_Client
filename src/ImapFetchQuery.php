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
 * A fluent `fetch()` query for {@see ImapClient}.
 *
 * By choice unrelated to {@see Pop3FetchQuery}. Both share only the
 * genuinely protocol-agnostic builder slice in {@see FetchQueryFields}
 * (full message / header text / body text / uid / size / seq / date) and
 * are otherwise free to diverge.
 * `MailboxProtocol::fetch()` types its `$query` as bare `object`.
 *
 * Each builder call records the wire data item(s) it needs, in request
 * order, so {@see ImapClient::fetch()} can serialize them into the
 * `FETCH (...)` item list and {@see ImapFetchParser} can route each
 * response key back to the right result slot. The trait's section-scoped
 * `headerText($id)`/`bodyText($id)`/`fullMsg()` are re-expressed here as
 * their IMAP wire forms (`BODY[id.HEADER]`, `BODY[id.TEXT]`, `BODY[]`);
 * this class adds the IMAP-only items the trait has no concept of:
 * `ENVELOPE`, `BODYSTRUCTURE`, `FLAGS`, `MODSEQ`, selective
 * `BODY[HEADER.FIELDS (...)]`, per-part `BODY[id.MIME]` and raw
 * `BODY[id]` part bodies.
 *
 * `BODY.PEEK[...]` is used by default for every content item so a fetch
 * does not implicitly set `\Seen` (RFC 3501 §6.4.5). A caller who wants `\Seen` as a side effect passes `peek: false` but is
 * then responsible for having opened the mailbox read-write first.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapFetchQuery
{
    use FetchQueryFields;

    /**
     * Wire data items, in first-requested order, deduplicated. These are
     * emitted verbatim as bare atoms in the `FETCH (...)` list.
     *
     * @var array<string, true>
     */
    private array $items = [];

    private bool $wantStructure = false;

    private bool $wantEnvelope = false;

    private bool $wantFlags = false;

    private bool $wantModSeq = false;

    /**
     * Requested `HEADER.FIELDS` groups, keyed by caller label. Each maps
     * to the reconstructed section signature (`HEADER.FIELDS (FROM TO)`)
     * that the response key will carry, so {@see ImapFetchParser} can map
     * an incoming `BODY[HEADER.FIELDS (...)]` key back to its label.
     * This is the same round-trip legacy did through its `fetch_lookup` map.
     *
     * @var array<string, string>
     */
    private array $headerFieldLabels = [];

    public function structure(): self
    {
        $this->wantStructure = true;
        $this->addItem('BODYSTRUCTURE');

        return $this;
    }

    public function envelope(): self
    {
        $this->wantEnvelope = true;
        $this->addItem('ENVELOPE');

        return $this;
    }

    public function flags(): self
    {
        $this->wantFlags = true;
        $this->addItem('FLAGS');

        return $this;
    }

    public function modseq(): self
    {
        $this->wantModSeq = true;
        $this->addItem('MODSEQ');

        return $this;
    }

    /**
     * Request a named subset of RFC 822 headers.
     *
     * @param string        $label  A caller-chosen key the matching
     *                                headers are stored under, retrievable
     *                                via {@see ImapFetchResult::getHeaders()}.
     * @param list<string>  $fields The header field names (case-insensitive
     *                                on the wire; upper-cased here).
     * @param bool          $not    Fetch every header *except* `$fields`
     *                                (`HEADER.FIELDS.NOT`, RFC 3501 §6.4.5).
     * @param bool          $peek   Use `BODY.PEEK` (default) to avoid the
     *                                `\Seen` side effect.
     */
    public function headers(string $label, array $fields, bool $not = false, bool $peek = true): self
    {
        $upper = array_map('strtoupper', $fields);
        $keyword = $not ? 'HEADER.FIELDS.NOT' : 'HEADER.FIELDS';
        $signature = $keyword . ' (' . implode(' ', $upper) . ')';

        $this->headerFieldLabels[$label] = $signature;
        $this->addItem(($peek ? 'BODY.PEEK[' : 'BODY[') . $signature . ']');

        return $this;
    }

    /**
     * Request the RFC 822 header text of a MIME part (`BODY[id.HEADER]`),
     * or the whole message header when `$id` is `0`/`''` (`BODY[HEADER]`).
     */
    public function headerText(int|string $id = 0, bool $peek = true): self
    {
        $this->addItem($this->sectionItem($id, 'HEADER', $peek));

        return $this;
    }

    /**
     * Request the body text of a MIME part (`BODY[id.TEXT]`) or the whole
     * message body when `$id` is `0`/`''` (`BODY[TEXT]`).
     */
    public function bodyText(int|string $id = 0, bool $peek = true): self
    {
        $this->addItem($this->sectionItem($id, 'TEXT', $peek));

        return $this;
    }

    /**
     * Request the MIME header of a specific part (`BODY[id.MIME]`, RFC
     * 3501 §6.4.5). Only meaningful for a non-zero part id.
     */
    public function mimeHeader(int|string $id, bool $peek = true): self
    {
        $prefix = $peek ? 'BODY.PEEK[' : 'BODY[';
        $this->addItem($prefix . $id . '.MIME]');

        return $this;
    }

    /**
     * Request a specific MIME part's raw body content (`BODY[id]`),
     * optionally a byte range of it (`BODY[id]<start.length>`).
     *
     */
    public function bodyPart(int|string $id, ?int $start = null, ?int $length = null, bool $peek = true): self
    {
        $prefix = $peek ? 'BODY.PEEK[' : 'BODY[';
        $this->addItem($prefix . $id . ']' . $this->partialSuffix($start, $length));

        return $this;
    }

    /**
     * Request the full raw message (`BODY[]`), optionally a byte range.
     * Overrides the trait's storage-only `fullMsg()` so the wire item is
     * recorded too.
     */
    public function fullMsg(?int $start = null, ?int $length = null, bool $peek = true): static
    {
        $this->wantFullMsg = true;
        $this->fullMsgStart = $start;
        $this->fullMsgLength = $length;

        $prefix = $peek ? 'BODY.PEEK[]' : 'BODY[]';
        $this->addItem($prefix . $this->partialSuffix($start, $length));

        return $this;
    }

    public function wantsStructure(): bool
    {
        return $this->wantStructure;
    }

    public function wantsEnvelope(): bool
    {
        return $this->wantEnvelope;
    }

    public function wantsFlags(): bool
    {
        return $this->wantFlags;
    }

    public function wantsModSeq(): bool
    {
        return $this->wantModSeq;
    }

    /**
     * Whether the query asks for any stream-backed content that cannot be
     * served from the metadata cache: the full message, a body/text
     * section, a MIME header, or a raw body part. Header-field groups
     * (`BODY[HEADER.FIELDS (...)]`) are cacheable and do not count. When
     * false, every requested item (envelope, flags, structure, modseq,
     * size, internaldate, header-field groups) can be served from cache.
     */
    public function wantsStreamContent(): bool
    {
        if ($this->wantsFullMsg()) {
            return true;
        }

        foreach ($this->items as $item => $_) {
            // A header-field group is cacheable; other BODY[...]/BINARY[...]
            // sections carry stream content that is not.
            if (str_contains($item, 'HEADER.FIELDS')) {
                continue;
            }

            if (
                str_starts_with($item, 'BODY.PEEK[')
                || str_starts_with($item, 'BODY[')
                || str_starts_with($item, 'BINARY[')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The caller labels of every requested `HEADER.FIELDS` group, in
     * request order (RFC 3501 §6.4.5). These map to cacheable header text.
     *
     * @return list<string>
     */
    public function headerLabels(): array
    {
        return array_keys($this->headerFieldLabels);
    }

    /**
     * The wire data items to place inside the `FETCH (...)` list in
     * first-requested order. `UID` is added by {@see ImapClient::fetch()}
     * itself when needed (a `UID FETCH` returns it implicitly). It is
     * never recorded here.
     *
     * @return list<string>
     */
    public function wireItems(): array
    {
        return array_keys($this->items);
    }

    /**
     * Map a reconstructed `HEADER.FIELDS (...)` section signature back to
     * the caller label it was requested under or null if none matches.
     */
    public function headerLabelFor(string $signature): ?string
    {
        $match = array_search($signature, $this->headerFieldLabels, true);

        return $match === false ? null : $match;
    }

    private function addItem(string $item): void
    {
        $this->items[$item] = true;
    }

    private function sectionItem(int|string $id, string $keyword, bool $peek): string
    {
        $prefix = $peek ? 'BODY.PEEK[' : 'BODY[';
        $whole = $id === 0 || $id === '' || $id === '0';
        $section = $whole ? $keyword : $id . '.' . $keyword;

        return $prefix . $section . ']';
    }

    private function partialSuffix(?int $start, ?int $length): string
    {
        if ($length !== null) {
            return '<' . ($start ?? 0) . '.' . $length . '>';
        }

        return $start !== null ? '<' . $start . '>' : '';
    }
}
