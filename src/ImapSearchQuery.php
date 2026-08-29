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

use DateTimeInterface;

/**
 * A fluent IMAP `SEARCH` criteria builder (RFC 3501 §6.4.4).
 *
 * By choice unrelated to the legacy `Horde_Imap_Client_Search_Query`
 * god-object: this records each criterion as an ordered run of
 * {@see ImapWireEncodable} tokens as it is added, so {@see build()} can
 * hand {@see ImapClient::search()} a flat token list to splice straight
 * into the command. Every criterion is implicitly AND-ed, matching IMAP's
 * own default (RFC 3501 §6.4.4); {@see orWith()} and {@see notMatching()}
 * build the explicit `OR`/`NOT` forms.
 *
 * Text values are added as {@see ImapWireString}s, which pick quoted vs
 * literal form automatically. A non-ASCII value forces the search to
 * declare a `$charset`; {@see build()} reports it so the client can emit
 * the `CHARSET` argument (RFC 3501 §6.4.4, RFC 6855 §3 turns it off once
 * `UTF8=ACCEPT` is enabled).
 *
 * Deliberately not ported from the legacy builder: `SEARCH=FUZZY`
 * (RFC 6203, rarely deployed) and the client-visible serialization
 * surface. `WITHIN` (RFC 5032) and `MODSEQ` (RFC 7162) are included but
 * left to the caller to gate on capability, matching how the rest of the
 * modern client treats optional extensions.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapSearchQuery
{
    /**
     * The RFC 3501 §2.3.2 system flags, which search as a single token
     * (`SEEN`) or its negation (`UNSEEN`), unlike keywords which take a
     * `KEYWORD <name>` pair.
     */
    private const SYSTEM_FLAGS = ['ANSWERED', 'DELETED', 'DRAFT', 'FLAGGED', 'RECENT', 'SEEN'];

    /**
     * The RFC 3501 §6.4.4 header search keys that are a single token
     * rather than the generic `HEADER <field>` two-token form.
     */
    private const HEADER_KEYS = ['BCC', 'CC', 'FROM', 'SUBJECT', 'TO'];

    /**
     * Accumulated criteria nodes, resolved to wire form at {@see build()}.
     * A node is an {@see ImapWireEncodable} (structural token, ready as-is),
     * an {@see ImapSearchText} (deferred text, encoded at build time), or a
     * group `array{op: 'NOT'|'OR', nodes: list<...>}` for a nested
     * sub-query (also resolved at build time so its text picks up the
     * chosen charset).
     *
     * @var list<ImapWireEncodable|ImapSearchText|array{op: string, nodes: array<int, mixed>}>
     */
    private array $criteria = [];

    private ?string $charset = null;

    /**
     * Match a system flag or keyword. A system flag (`\Seen`, `\Flagged`,
     * ...) searches as `SEEN`/`UNSEEN`; any other name searches as
     * `KEYWORD <name>`/`UNKEYWORD <name>` (RFC 3501 §6.4.4).
     */
    public function flag(string $name, bool $set = true): self
    {
        $name = strtoupper(ltrim($name, '\\'));

        if (in_array($name, self::SYSTEM_FLAGS, true)) {
            $this->add(new ImapWireAtom(($set ? '' : 'UN') . $name));

            return $this;
        }

        $this->add(new ImapWireAtom($set ? 'KEYWORD' : 'UNKEYWORD'));
        $this->add(new ImapWireAtom($name));

        return $this;
    }

    /**
     * Mark the next criterion as fuzzy (`SEARCH=FUZZY`, RFC 6203): the
     * server may return relevance-ranked, approximate matches rather than
     * exact ones. Emits a `FUZZY` token before the criterion that follows,
     * e.g. `->fuzzy()->text('mispeld')` produces `FUZZY BODY mispeld`.
     *
     * The caller is responsible for confirming the server advertises
     * `SEARCH=FUZZY` (Dovecot and Cyrus do so when a full-text index is
     * configured).
     */
    public function fuzzy(): self
    {
        $this->add(new ImapWireAtom('FUZZY'));

        return $this;
    }

    /**
     * Match every message (`ALL`, RFC 3501 §6.4.4). This is the implicit
     * default when no criteria are added, but is offered explicitly so a
     * caller can be unambiguous.
     */
    public function all(): self
    {
        $this->add(new ImapWireAtom('ALL'));

        return $this;
    }

    /**
     * Match a specific header field (RFC 3501 §6.4.4). The well-known
     * keys (`FROM`, `TO`, `CC`, `BCC`, `SUBJECT`) use their single-token
     * form; any other field name uses the generic `HEADER <field> <text>`.
     */
    public function header(string $field, string $value, bool $not = false): self
    {
        $field = strtoupper($field);

        if ($not) {
            $this->add(new ImapWireAtom('NOT'));
        }

        if (in_array($field, self::HEADER_KEYS, true)) {
            $this->add(new ImapWireAtom($field));
        } else {
            $this->add(new ImapWireAtom('HEADER'));
            $this->add(new ImapWireString($field, isAstring: true));
        }

        $this->addText($value);

        return $this;
    }

    /**
     * Match message body text (`BODY`) or the full message text including
     * headers (`TEXT`) (RFC 3501 §6.4.4).
     */
    public function text(string $value, bool $bodyOnly = true, bool $not = false): self
    {
        if ($not) {
            $this->add(new ImapWireAtom('NOT'));
        }

        $this->add(new ImapWireAtom($bodyOnly ? 'BODY' : 'TEXT'));
        $this->addText($value);

        return $this;
    }

    /**
     * Match on message size in bytes: `LARGER` or `SMALLER`
     * (RFC 3501 §6.4.4).
     */
    public function size(int $bytes, bool $larger = true): self
    {
        $this->add(new ImapWireAtom($larger ? 'LARGER' : 'SMALLER'));
        $this->add(new ImapWireNumber($bytes));

        return $this;
    }

    /**
     * Match a set of message ids. When `$ids` is a UID set the `UID`
     * keyword is emitted first; a sequence set is added bare
     * (RFC 3501 §6.4.4).
     */
    public function ids(ImapIdSet $ids, bool $not = false): self
    {
        if ($ids->isEmpty()) {
            return $this;
        }

        if ($not) {
            $this->add(new ImapWireAtom('NOT'));
        }

        if (!$ids->isSequence()) {
            $this->add(new ImapWireAtom('UID'));
        }

        $this->add(new ImapWireAtom((string) $ids));

        return $this;
    }

    /**
     * Match every UID at or above `$from` (`UID <from>:*`, RFC 3501
     * §6.4.4). The open-ended `:*` range cannot be expressed through an
     * {@see ImapIdSet} (which only models the lone `*` special), so this
     * is a dedicated helper, used chiefly by {@see ImapClient::sync()} to
     * find messages arrived since a known UIDNEXT.
     */
    public function uidFrom(int $from): self
    {
        $this->add(new ImapWireAtom('UID'));
        $this->add(new ImapWireAtom($from . ':*'));

        return $this;
    }

    /**
     * Match on a date, comparing either the message's internal date or
     * (when `$sent` is true) its `Date:` header (RFC 3501 §6.4.4).
     * `$range` is one of `BEFORE`, `ON`, `SINCE`.
     */
    public function date(DateTimeInterface $date, string $range = 'SINCE', bool $sent = false, bool $not = false): self
    {
        $range = strtoupper($range);
        $keyword = $sent ? 'SENT' . $range : $range;

        if ($not) {
            $this->add(new ImapWireAtom('NOT'));
        }

        $this->add(new ImapWireAtom($keyword));
        // RFC 3501 date format: 1-Feb-1994 (day is not zero padded).
        $this->add(new ImapWireAtom($date->format('j-M-Y')));

        return $this;
    }

    /**
     * Match on relative age using the WITHIN extension (RFC 5032):
     * `YOUNGER <seconds>` or `OLDER <seconds>`. The caller is responsible
     * for confirming the server advertises `WITHIN`.
     */
    public function within(int $seconds, bool $younger = true, bool $not = false): self
    {
        if ($not) {
            $this->add(new ImapWireAtom('NOT'));
        }

        $this->add(new ImapWireAtom($younger ? 'YOUNGER' : 'OLDER'));
        $this->add(new ImapWireNumber($seconds));

        return $this;
    }

    /**
     * Match on modification sequence (CONDSTORE, RFC 7162 §3.1.5). The
     * optional `$entryName`/`$entryType` pair narrows the match to a
     * metadata item; `$entryType` is one of `all`, `priv`, `shared`. The
     * caller is responsible for confirming the server advertises
     * `CONDSTORE`.
     */
    public function modseq(int $value, ?string $entryName = null, string $entryType = 'all'): self
    {
        $this->add(new ImapWireAtom('MODSEQ'));

        if ($entryName !== null) {
            $this->add(new ImapWireString($entryName));
            $this->add(new ImapWireAtom(strtolower($entryType)));
        }

        $this->add(new ImapWireNumber($value));

        return $this;
    }

    /**
     * Match the result of the previous SEARCH on this connection (the `$`
     * marker, SEARCHRES / RFC 5182). The caller is responsible for
     * confirming the server advertises `SEARCHRES`.
     */
    public function previousSearch(bool $not = false): self
    {
        if ($not) {
            $this->add(new ImapWireAtom('NOT'));
        }

        $this->add(new ImapWireAtom('$'));

        return $this;
    }

    /**
     * Negate a whole sub-query: `NOT (...)` (RFC 3501 §6.4.4). The
     * sub-query's criteria are wrapped in a parenthesized group, resolved
     * (including any text charset) when this query is built.
     */
    public function notMatching(self $query): self
    {
        $this->mergeCharset($query->charset);
        $this->criteria[] = ['op' => 'NOT', 'nodes' => $query->criteria];

        return $this;
    }

    /**
     * Match either this query's accumulated criteria or the alternative:
     * `OR (this-so-far) (other)` (RFC 3501 §6.4.4). Each side is wrapped
     * in its own parenthesized group so the operator binds the two whole
     * sub-queries, not just their first tokens.
     */
    public function orWith(self $query): self
    {
        $this->mergeCharset($query->charset);
        $this->criteria = [['op' => 'OR', 'nodes' => $this->criteria, 'right' => $query->criteria]];

        return $this;
    }

    /**
     * Force the search charset. Normally the charset is inferred (it
     * becomes `UTF-8` the first time a non-ASCII text value is added);
     * setting it explicitly is only needed for a legacy charset a caller
     * has already encoded its values in.
     */
    public function charset(string $charset): self
    {
        $this->charset = strtoupper($charset);

        return $this;
    }

    /**
     * The built search command.
     *
     * `criteria` is the ordered token list to splice into the `SEARCH`
     * command as individual arguments. When empty, the client sends `ALL`.
     * `charset` is the charset the text values need, or null when the
     * query holds no text (so no `CHARSET` argument is required).
     *
     * `$charsetOverride` re-encodes every text value into a specific
     * charset instead of the query's own (used by {@see ImapClient::search()}
     * to retry after a `NO [BADCHARSET ...]` rejection); the returned
     * `charset` then reflects the override.
     *
     * @return array{charset: ?string, criteria: list<ImapWireEncodable>}
     */
    public function build(?string $charsetOverride = null): array
    {
        $charset = $charsetOverride !== null ? strtoupper($charsetOverride) : $this->charset;

        return ['charset' => $charset, 'criteria' => $this->resolveNodes($this->criteria, $charset)];
    }

    /**
     * Resolve a node list to wire encodables in `$charset`: structural
     * tokens pass through, deferred text is encoded, and NOT/OR groups
     * recurse into parenthesized lists.
     *
     * @param array<int, mixed> $nodes
     *
     * @return list<ImapWireEncodable>
     */
    private function resolveNodes(array $nodes, ?string $charset): array
    {
        $out = [];

        foreach ($nodes as $node) {
            if ($node instanceof ImapSearchText) {
                $out[] = $node->encode($charset);
            } elseif ($node instanceof ImapWireEncodable) {
                $out[] = $node;
            } elseif (is_array($node) && ($node['op'] ?? null) === 'NOT') {
                $out[] = new ImapWireAtom('NOT');
                $out[] = new ImapWireList($this->resolveNodes($node['nodes'], $charset));
            } elseif (is_array($node) && ($node['op'] ?? null) === 'OR') {
                $out[] = new ImapWireAtom('OR');
                $out[] = new ImapWireList($this->resolveNodes($node['nodes'], $charset));
                $out[] = new ImapWireList($this->resolveNodes($node['right'], $charset));
            }
        }

        return $out;
    }

    private function add(ImapWireEncodable $token): void
    {
        $this->criteria[] = $token;
    }

    /**
     * Add a text value, promoting the search charset to UTF-8 when the
     * value is not pure ASCII (RFC 3501 §6.4.4 requires a CHARSET for
     * non-ASCII search text). The raw UTF-8 is held (as an
     * {@see ImapSearchText}) and only encoded at {@see build()} time, so a
     * BADCHARSET retry can re-encode it.
     */
    private function addText(string $value): void
    {
        if ($this->charset === null && !$this->isAscii($value)) {
            $this->charset = 'UTF-8';
        }

        $this->criteria[] = new ImapSearchText($value);
    }

    private function mergeCharset(?string $charset): void
    {
        if ($charset !== null) {
            $this->charset = $charset;
        }
    }

    private function isAscii(string $value): bool
    {
        return $value === '' || !preg_match('/[^\x00-\x7F]/', $value);
    }

    /**
     * Whether every text value in this query (including nested OR/NOT
     * sub-queries) can be represented in `$charset` without loss, i.e. it
     * survives a UTF-8 -> charset -> UTF-8 round trip unchanged. Used to
     * reject a lossy BADCHARSET retry target (e.g. US-ASCII for accented
     * text).
     */
    public function canEncodeIn(string $charset): bool
    {
        if (strtoupper($charset) === 'UTF-8') {
            return true;
        }

        foreach ($this->collectText($this->criteria) as $value) {
            if ($value === '') {
                continue;
            }

            $roundTrip = mb_convert_encoding(mb_convert_encoding($value, $charset, 'UTF-8'), 'UTF-8', $charset);

            if ($roundTrip !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gather the raw UTF-8 text values from a node list, descending into
     * NOT/OR groups.
     *
     * @param array<int, mixed> $nodes
     *
     * @return list<string>
     */
    private function collectText(array $nodes): array
    {
        $out = [];

        foreach ($nodes as $node) {
            if ($node instanceof ImapSearchText) {
                $out[] = $node->utf8Value;
            } elseif (is_array($node)) {
                $out = [...$out, ...$this->collectText($node['nodes'])];

                if (isset($node['right'])) {
                    $out = [...$out, ...$this->collectText($node['right'])];
                }
            }
        }

        return $out;
    }
}
