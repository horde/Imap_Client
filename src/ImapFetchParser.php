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
 * Stateless, static implementation.
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
     *         response is not an `* n FETCH (...)` line (A mailbox may interleave EXPUNGE/EXISTS/etc. among the
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
     * tokenizer.
     */
    private static function isUnterminatedSection(string $name): bool
    {
        return (str_starts_with($name, 'BODY[') || str_starts_with($name, 'BINARY['))
            && !str_contains($name, ']');
    }

    /**
     * Rebuild a section key split across tokens.
     *
     * Fold the field-list members back inside their brackets.
     *
     * Returns the reconstructed key and the index of its last consumed token.
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
                // The token which carries the closing bracket ends the key.
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

        if (str_starts_with($upper, 'BINARY.SIZE[')) {
            $size = self::asString($value);
            if ($size === '' || !ctype_digit($size)
                || strlen(ltrim($size, '0')) > strlen((string) PHP_INT_MAX)
                || (strlen(ltrim($size, '0')) === strlen((string) PHP_INT_MAX)
                    && strcmp(ltrim($size, '0'), (string) PHP_INT_MAX) > 0)) {
                throw new ImapProtocolException('Malformed or unsupported BINARY.SIZE value.');
            }
            $result->setBodyPartSize(self::sectionOf($upper), (int) $size);
            return;
        }

        if (str_starts_with($upper, 'BODY[') || str_starts_with($upper, 'BINARY[')) {
            self::dispatchSection($upper, $value, $result, $query);
        }
    }

    private static function dispatchSection(
        string $upper,
        mixed $value,
        ImapFetchResult $result,
        ImapFetchQuery $query,
    ): void {
        // HEADER.FIELDS keys carry their field list inside the brackets.
        if (str_contains($upper, 'HEADER.FIELDS')) {
            $signature = self::sectionOf($upper);
            $label = $query->headerLabelFor($signature);

            if ($label !== null) {
                $result->setHeaders($label, self::asString($value));
            }

            return;
        }

        // Strip everything from the last ']' onward. This drops the
        // closing bracket and any `<partial>` octet-offset suffix.
        // `BODY[1.TEXT]<0>` reduces to the section `1.TEXT`.
        $section = self::sectionOf($upper);

        if (str_starts_with($upper, 'BINARY[')) {
            // The server has already removed the MIME transfer encoding.
            // NIL must remain distinguishable from an empty decoded section.
            if ($value !== null && !is_string($value)) {
                throw new ImapProtocolException('Malformed BINARY section value.');
            }
            $result->setBinaryBodyPart($section, $value);

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
     * between the first `[` and the last `]`.
     * Any trailing `<partial>` suffix is dropped.
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
     * Parse an address list token: `(name adl mailbox
     * host)` sub-lists (RFC 3501 §7.4.2)
     *
     * Respects group start/end markers
     * Host NIL + mailbox non-NIL starts a group. Both NIL ends it.
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
                // Group start: Flush any prior group, open a new one.
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
     * according to RFC 3501 §7.4.2.
     * Multipart is distinguished by its first token being a nested list (a child part) rather than a type string.
     *
     * MIME-id numbering is handled by {@see ImapFetchResult::getParts()},
     * which maps the tree to IMAP section ids, including the special
     * numbering of multipart bodies inside message/rfc822 attachments.
     *
     * @see https://www.rfc-editor.org/rfc/rfc3501#section-7.4.2 BODYSTRUCTURE nesting.
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

        $extensionIndex = match (true) {
            strtolower($type) === 'text' => 8,
            strtolower($type) === 'message'
                && strtolower($subtype) === 'rfc822' => 10,
            default => 7,
        };
        foreach ([$extensionIndex, $extensionIndex + 1] as $index) {
            $disposition = $tokens[$index] ?? null;
            if (
                !is_array($disposition)
                || !in_array(
                    strtolower(self::nstring($disposition[0] ?? null)),
                    ['inline', 'attachment'],
                    true,
                )
            ) {
                continue;
            }

            $headerLines .= 'Content-Disposition: '
                . $disposition[0]
                . self::formatParams(
                    self::parseStructureParams($disposition[1] ?? null)
                )
                . "\r\n";
            break;
        }

        $sizeHint = ($bytes !== null && ctype_digit($bytes)) ? (int) $bytes : null;
        $children = [];
        if (
            strtolower($type) === 'message'
            && strtolower($subtype) === 'rfc822'
            && isset($tokens[8])
            && is_array($tokens[8])
        ) {
            $children[] = self::parseBodyStructure($tokens[8]);
        }

        return new Part(
            headers: HeaderCollection::parse($headerLines),
            children: $children,
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
