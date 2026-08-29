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
use Exception;
use Horde\Imap\Client\Exception\ImapProtocolException;
use Horde\Mail\Rfc822\Address;
use Horde\Mail\Rfc822\AddressList;
use Horde\Mail\Rfc822\Group;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Part;

/**
 * Turns one untagged `* n FETCH (...)` response (RFC 3501 §7.4.2) into an
 * {@see ImapFetchResult}.
 *
 * The H5 version walked a streaming token cursor
 * The new version works off the fully-materialized token tree {@see ImapTokenizer} already produces.
 * The FETCH item list is a flat even-membered array alternating item name and
 * value (`['UID', '5', 'FLAGS', ['\Seen'], 'BODY[]', '<bytes>', ...]`),
 * with complex structures (ENVELOPE, BODYSTRUCTURE, FLAGS) as
 * nested arrays and `NIL` as `null`. Parsing is therefore index walking,
 * not cursor advancing.
 *
 * Stateless implementation. Every method is static.
 * The {@see ImapFetchQuery} is passed in only to map an incoming `BODY[HEADER.FIELDS (...)]` key back to the
 * caller label it was requested under.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapFetchParser
{
    private function __construct() {}

    /**
     * @return array{seq: int, result: ImapFetchResult}|null Null when the
     *         response is not an `* n FETCH (...)` line (the caller skips
     *         it. A mailbox may interleave EXPUNGE/EXISTS/etc. among the
     *         FETCH replies).
     *
     * @throws ImapProtocolException On a malformed FETCH structure.
     */
    public static function parse(ImapResponse $response, ImapFetchQuery $query): ?array
    {
        $data = $response->data;

        if (
            !$response->isUntagged()
            || count($data) < 3
            || !is_string($data[0])
            || !ctype_digit($data[0])
            || !is_string($data[1])
            || strtoupper($data[1]) !== 'FETCH'
            || !is_array($data[2])
        ) {
            return null;
        }

        $seq = (int) $data[0];
        $result = new ImapFetchResult($seq);
        self::walkItems($data[2], $result, $query);

        return ['seq' => $seq, 'result' => $result];
    }

    /**
     * @param list<mixed> $items Flat name/value list from the FETCH data
     *                            list.
     */
    private static function walkItems(array $items, ImapFetchResult $result, ImapFetchQuery $query): void
    {
        $count = count($items);
        $i = 0;

        while ($i < $count) {
            $name = $items[$i];

            if (!is_string($name)) {
                throw new ImapProtocolException('Malformed FETCH item name.');
            }

            // A `BODY[HEADER.FIELDS (...)]` item is split by the tokenizer
            // on the space before the parenthesized field list, arriving
            // as `BODY[HEADER.FIELDS`, `(FROM TO)`, `]` then the value.
            // Reassemble the full section key before pairing it with a
            // value. Every other section (`BODY[HEADER]`, `BODY[1.TEXT]`,
            // `BODY[]<0>`, ...) is a single atom and needs no reassembly.
            if (self::isUnterminatedSection($name)) {
                [$name, $i] = self::reassembleSectionKey($items, $i, $count);
            }

            $value = $items[$i + 1] ?? null;
            self::dispatch(strtoupper($name), $name, $value, $result, $query);
            $i += 2;
        }
    }

    /**
     * A `BODY[`/`BINARY[` token whose closing `]` was split off by the
     * tokenizer (an embedded parenthesized field list forced a break).
     */
    private static function isUnterminatedSection(string $name): bool
    {
        return (str_starts_with($name, 'BODY[') || str_starts_with($name, 'BINARY['))
            && !str_contains($name, ']');
    }

    /**
     * Rebuild a section key split across tokens, folding the field-list
     * members back inside their brackets. Returns the reconstructed key
     * and the index of its last consumed token (the closing `]`).
     *
     * @param list<mixed> $items
     *
     * @return array{0: string, 1: int}
     */
    private static function reassembleSectionKey(array $items, int $start, int $count): array
    {
        $key = (string) $items[$start];
        $j = $start + 1;

        for (; $j < $count; ++$j) {
            $token = $items[$j];

            if (is_array($token)) {
                // The parenthesized field list, rendered back verbatim.
                $key .= ' (' . implode(' ', array_map(self::asString(...), $token)) . ')';

                continue;
            }

            if (is_string($token)) {
                // The token that carries the closing bracket ends the key.
                // It may also carry a trailing `<partial>` suffix.
                $key .= $token;

                if (str_contains($token, ']')) {
                    break;
                }
            }
        }

        return [$key, $j];
    }

    private static function dispatch(
        string $upper,
        string $original,
        mixed $value,
        ImapFetchResult $result,
        ImapFetchQuery $query,
    ): void {
        // RFC822 aliases resolve to their BODY[...] equivalents before
        // dispatch. RFC 3501 §7.4.2 keeps them for backward compatibility.
        $upper = match ($upper) {
            'RFC822' => 'BODY[]',
            'RFC822.HEADER' => 'BODY[HEADER]',
            'RFC822.TEXT' => 'BODY[TEXT]',
            default => $upper,
        };

        switch ($upper) {
            case 'FLAGS':
                $result->setFlags(self::stringList($value));

                return;

            case 'ENVELOPE':
                $result->setEnvelope(self::parseEnvelope(self::asList($value)));

                return;

            case 'INTERNALDATE':
                $result->setImapDate(self::parseInternalDate(self::asString($value)));

                return;

            case 'RFC822.SIZE':
                $result->setSize((int) self::asString($value));

                return;

            case 'UID':
                $uid = self::asString($value);
                $result->setUid(ctype_digit($uid) ? (int) $uid : $uid);

                return;

            case 'MODSEQ':
                // Sent as a one-element parenthesized list: `MODSEQ (n)`.
                $modseq = self::asList($value)[0] ?? null;

                if (is_string($modseq) && $modseq !== '') {
                    $result->setModSeq((int) $modseq);
                }

                return;

            case 'BODYSTRUCTURE':
            case 'BODY':
                // A bare `BODY` (no `[...]`) carries a non-extension
                // bodystructure. Treat it the same as BODYSTRUCTURE.
                $result->setStructure(self::parseBodyStructure(self::asList($value)));

                return;
        }

        if (str_starts_with($upper, 'BODY[') || str_starts_with($upper, 'BINARY[')) {
            self::dispatchSection($upper, $value, $result, $query);
        }

        // Anything else is ignored (not implemented or unknown).
    }

    private static function dispatchSection(
        string $upper,
        mixed $value,
        ImapFetchResult $result,
        ImapFetchQuery $query,
    ): void {
        // HEADER.FIELDS keys carry their field list inside the brackets.
        // Rebuild the section signature and map it back to the caller
        // label (H5's fetch_lookup round-trip).
        if (str_contains($upper, 'HEADER.FIELDS')) {
            $signature = self::sectionOf($upper);
            $label = $query->headerLabelFor($signature);

            if ($label !== null) {
                $result->setHeaders($label, self::asString($value));
            }

            return;
        }

        // Strip everything from the last ']' onward. This drops the
        // closing bracket and any `<partial>` octet-offset suffix so `BODY[1.TEXT]<0>` reduces to the section `1.TEXT`.
        $section = self::sectionOf($upper);

        if (str_starts_with($upper, 'BINARY[')) {
            // No BINARY decoding yet. Store the raw
            // part body under its id, same slot a BODY[id] would use.
            $result->setBodyPart($section, self::asString($value));

            return;
        }

        if ($section === '') {
            $result->setFullMsg(self::asString($value));

            return;
        }

        // A section ending in a bare number (`2`, `1.3`) is a raw body
        // part. A section with a trailing keyword is header/text/mime.
        $lastDot = strrpos($section, '.');
        $tail = $lastDot === false ? $section : substr($section, $lastDot + 1);
        $tailUpper = strtoupper($tail);

        [$id, $keyword] = match ($tailUpper) {
            'HEADER', 'TEXT', 'MIME' => [
                $lastDot === false ? 0 : substr($section, 0, $lastDot),
                $tailUpper,
            ],
            default => [$section, null],
        };

        match ($keyword) {
            'HEADER' => $result->setHeaderText($id, self::asString($value)),
            'TEXT' => $result->setBodyText($id, self::asString($value)),
            'MIME' => $result->setMimeHeader($id, self::asString($value)),
            default => $result->setBodyPart($section, self::asString($value)),
        };
    }

    /**
     * The section text inside `BODY[...]`/`BINARY[...]`, i.e. everything
     * between the first `[` and the last `]`, dropping any trailing
     * `<partial>` suffix.
     */
    private static function sectionOf(string $item): string
    {
        $open = strpos($item, '[');
        $close = strrpos($item, ']');

        if ($open === false || $close === false || $close < $open) {
            return '';
        }

        return substr($item, $open + 1, $close - $open - 1);
    }

    /**
     * Parse an ENVELOPE list (RFC 3501 §7.4.2). Ten positional fields.
     * `sender`/`reply-to` fall back to `from` when NIL.
     *
     * @param list<mixed> $tokens
     */
    private static function parseEnvelope(array $tokens): ImapEnvelope
    {
        $date = self::nstring($tokens[0] ?? null);
        $subject = self::nstring($tokens[1] ?? null);
        $from = self::parseAddressList($tokens[2] ?? null);
        $to = self::parseAddressList($tokens[5] ?? null);
        $cc = self::parseAddressList($tokens[6] ?? null);
        $bcc = self::parseAddressList($tokens[7] ?? null);
        $inReplyTo = self::nstring($tokens[8] ?? null);
        $messageId = self::nstring($tokens[9] ?? null);

        // RFC 3501 §7.4.2: A NIL sender/reply-to means "same as from field".
        $sender = ($tokens[3] ?? null) === null
            ? $from
            : self::parseAddressList($tokens[3]);
        $replyTo = ($tokens[4] ?? null) === null
            ? $from
            : self::parseAddressList($tokens[4]);

        return new ImapEnvelope(
            date: $date,
            subject: $subject,
            from: $from,
            sender: $sender,
            replyTo: $replyTo,
            to: $to,
            cc: $cc,
            bcc: $bcc,
            inReplyTo: $inReplyTo,
            messageId: $messageId,
        );
    }

    /**
     * Parse an address-list token: A list of 4-element `(name adl mailbox
     * host)` sub-lists (RFC 3501 §7.4.2), honoring the group start/end
     * markers (host NIL + mailbox non-NIL starts a group. Both NIL ends
     * it).
     */
    private static function parseAddressList(mixed $token): AddressList
    {
        $list = new AddressList();

        if (!is_array($token)) {
            return $list;
        }

        $group = null;
        $groupItems = null;

        foreach ($token as $address) {
            if (!is_array($address)) {
                continue;
            }

            $personal = self::nstringOrNull($address[0] ?? null);
            $mailbox = self::nstringOrNull($address[2] ?? null);
            $host = self::nstringOrNull($address[3] ?? null);

            if ($host === null && $mailbox !== null) {
                // Group start: flush any prior group, open a new one.
                if ($group !== null && $groupItems !== null) {
                    $list->add(new Group($group, $groupItems));
                }

                $group = $mailbox;
                $groupItems = new AddressList(groupsAllowed: false);

                continue;
            }

            if ($host === null && $mailbox === null) {
                // Group end.
                if ($group !== null && $groupItems !== null) {
                    $list->add(new Group($group, $groupItems));
                }

                $group = null;
                $groupItems = null;

                continue;
            }

            $addr = new Address(
                mailbox: $mailbox ?? '',
                host: $host,
                personal: $personal,
            );

            if ($groupItems !== null) {
                $groupItems->add($addr);
            } else {
                $list->add($addr);
            }
        }

        // A group left open by a missing end marker still gets flushed.
        if ($group !== null && $groupItems !== null) {
            $list->add(new Group($group, $groupItems));
        }

        return $list;
    }

    /**
     * Recursively parse a BODYSTRUCTURE list into a {@see Part} tree
     * (RFC 3501 §7.4.2). Multipart is distinguished by its first token
     * being a nested list (a child part) rather than a type string.
     *
     * MIME-id numbering is deliberately left to the {@see Part} tree
     * itself: iterating the built tree ({@see ImapFetchResult::getParts()}
     * uses `iterate(false)`) yields the canonical RFC 3501 §6.4.5 section
     * ids (`1`, `1.1`, `2`, ...) as iterator keys, so the parser does not
     * stamp them a second time. Doing so would duplicate the tree's own
     * numbering and, for a top-level multipart, disagree with it.
     *
     * @param list<mixed> $tokens
     */
    private static function parseBodyStructure(array $tokens): Part
    {
        if (isset($tokens[0]) && is_array($tokens[0])) {
            return self::parseMultipart($tokens);
        }

        return self::parseSinglePart($tokens);
    }

    /**
     * @param list<mixed> $tokens
     */
    private static function parseMultipart(array $tokens): Part
    {
        $children = [];
        $i = 0;

        while (isset($tokens[$i]) && is_array($tokens[$i])) {
            $children[] = self::parseBodyStructure($tokens[$i]);
            ++$i;
        }

        $subtype = self::nstring($tokens[$i] ?? null);
        $rawHeaders = 'Content-Type: multipart/' . ($subtype !== '' ? $subtype : 'mixed') . "\r\n";

        return new Part(
            headers: HeaderCollection::parse($rawHeaders),
            children: $children,
        );
    }

    /**
     * @param list<mixed> $tokens
     */
    private static function parseSinglePart(array $tokens): Part
    {
        $type = self::nstring($tokens[0] ?? null);
        $subtype = self::nstring($tokens[1] ?? null);
        $params = self::parseStructureParams($tokens[2] ?? null);
        $contentId = self::nstringOrNull($tokens[3] ?? null);
        $description = self::nstringOrNull($tokens[4] ?? null);
        $encoding = self::nstringOrNull($tokens[5] ?? null);
        $bytes = self::nstringOrNull($tokens[6] ?? null);

        $fullType = ($type !== '' ? $type : 'application') . '/' . ($subtype !== '' ? $subtype : 'octet-stream');

        $headerLines = 'Content-Type: ' . $fullType . self::formatParams($params) . "\r\n";

        if ($encoding !== null && $encoding !== '') {
            $headerLines .= 'Content-Transfer-Encoding: ' . $encoding . "\r\n";
        }

        if ($contentId !== null && $contentId !== '') {
            $headerLines .= 'Content-ID: ' . $contentId . "\r\n";
        }

        if ($description !== null && $description !== '') {
            $headerLines .= 'Content-Description: ' . $description . "\r\n";
        }

        $sizeHint = ($bytes !== null && ctype_digit($bytes)) ? (int) $bytes : null;

        return new Part(
            headers: HeaderCollection::parse($headerLines),
            sizeHint: $sizeHint,
        );
    }

    /**
     * Parse a list (`("charset" "utf-8" ...)`) into a name => value map, lower-casing names.
     * RFC 2045 params are case-insensitive.
     *
     * @return array<string, string>
     */
    private static function parseStructureParams(mixed $token): array
    {
        if (!is_array($token)) {
            return [];
        }

        $params = [];
        $count = count($token);

        for ($i = 0; $i + 1 < $count; $i += 2) {
            $name = $token[$i];
            $value = $token[$i + 1];

            if (is_string($name) && is_string($value)) {
                $params[strtolower($name)] = $value;
            }
        }

        return $params;
    }

    /**
     * @param array<string, string> $params
     */
    private static function formatParams(array $params): string
    {
        $out = '';

        foreach ($params as $name => $value) {
            $out .= '; ' . $name . '="' . $value . '"';
        }

        return $out;
    }

    private static function parseInternalDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            // IMAP INTERNALDATE: "dd-Mon-yyyy HH:MM:SS +ZZZZ".
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @return list<mixed>
     */
    private static function asList(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * An RFC 3501 §4.5 `nstring`: a string or NIL rendered as an empty string.
     */
    private static function nstring(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function nstringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
