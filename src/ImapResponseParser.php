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

use Horde\Imap\Client\Exception\ImapProtocolException;

/**
 * Turns one {@see ImapTokenizer::readLine()} token tree into an
 * RFC 3501 §7 {@see ImapResponse}.
 *
 * This is the only place which decides whether a line is tagged,
 * untagged or a continuation and whether it carries a status word
 * and a response code.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapResponseParser
{
    private function __construct() {}

    /**
     * @param list<mixed> $tokens One {@see ImapTokenizer::readLine()} result.
     *
     * @throws ImapProtocolException On an empty line, or a tagged line
     *                                 with no status word.
     */
    public static function parse(array $tokens): ImapResponse
    {
        if ($tokens === []) {
            throw new ImapProtocolException('Empty server response.');
        }

        $first = $tokens[0];
        $rest = array_slice($tokens, 1);

        if ($first === '+') {
            return new ImapResponse(ImapResponseKind::Continuation, data: $rest, text: self::joinText($rest));
        }

        $kind = $first === '*' ? ImapResponseKind::Untagged : ImapResponseKind::Tagged;
        $tag = $kind === ImapResponseKind::Tagged ? $first : null;
        $status = ($rest !== [] && is_string($rest[0])) ? self::statusFor($rest[0]) : null;

        if ($status === null) {
            if ($kind === ImapResponseKind::Tagged) {
                throw new ImapProtocolException("Malformed tagged response for tag '{$tag}'.");
            }

            // Untagged data (e.g. "1 EXISTS", "CAPABILITY ...") carries
            // no status word and no response code.
            return new ImapResponse(ImapResponseKind::Untagged, data: $rest, text: self::joinText($rest));
        }

        $remaining = array_slice($rest, 1);
        $responseCode = self::extractResponseCode($remaining);

        return new ImapResponse($kind, $tag, $status, $responseCode, $remaining, self::joinText($remaining));
    }

    private static function statusFor(string $word): ?ImapResponseStatus
    {
        return match (strtoupper($word)) {
            'OK' => ImapResponseStatus::Ok,
            'NO' => ImapResponseStatus::No,
            'BAD' => ImapResponseStatus::Bad,
            'BYE' => ImapResponseStatus::Bye,
            'PREAUTH' => ImapResponseStatus::PreAuth,
            default => null,
        };
    }

    /**
     * Pull a leading `[CODE ...]` off `$tokens`, if present, consuming
     * the tokens it spans.
     *
     * @param list<mixed> $tokens
     */
    private static function extractResponseCode(array &$tokens): ?ImapResponseCode
    {
        if ($tokens === [] || !is_string($tokens[0]) || !str_starts_with($tokens[0], '[')) {
            return null;
        }

        $first = array_shift($tokens);

        if (str_ends_with($first, ']')) {
            return new ImapResponseCode(substr($first, 1, -1));
        }

        $name = substr($first, 1);
        $data = [];

        while ($tokens !== []) {
            $token = array_shift($tokens);

            if (is_string($token) && str_ends_with($token, ']')) {
                $trimmed = substr($token, 0, -1);

                if ($trimmed !== '') {
                    $data[] = $trimmed;
                }

                break;
            }

            $data[] = $token;
        }

        return new ImapResponseCode($name, $data);
    }

    /**
     * @param list<mixed> $tokens
     */
    private static function joinText(array $tokens): string
    {
        $parts = [];

        foreach ($tokens as $token) {
            $parts[] = match (true) {
                is_array($token) => '(' . self::joinText($token) . ')',
                $token === null => 'NIL',
                default => $token,
            };
        }

        return implode(' ', $parts);
    }
}
