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
use Horde\Socket\Client\ClientInterface;

/**
 * Parses one full IMAP response line (RFC 3501 §4) off a
 * `Horde\Socket\Client\ClientInterface`.
 *
 * A "line" here means one logical server response, which is not always
 * one physical line: A literal (`{n}` or, for RFC 3516 binary content,
 * `~{n}`) always sits at the very end of a physical line with its `n`
 * raw bytes following immediately and the response's remaining tokens
 * continuing on the physical line after that. Note: a `FETCH` data list almost always has more after the
 * literal. This class hides that from callers: It pulls as many physical lines and literal
 * payloads as a response needs and returns one token tree.
 *
 * A parenthesized list becomes a nested array. An atom, quoted string
 * or literal becomes a string (PHP strings are binary-safe so a
 * literal's raw bytes need no special wrapper). `NIL` becomes `null`.
 *
 * This class only resolves the generic data grammar (atom/string/
 * literal/list). It has no opinion on what a response line means.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapTokenizer
{
    /**
     * Generous upper bound for a single physical line read. IMAP
     * command/response lines are usually short; literals carry the
     * actual bulk data and are read separately, by exact byte count.
     */
    private const LINE_BUFFER = 65536;

    private string $buffer = '';

    private int $pos = 0;

    public function __construct(
        private readonly ClientInterface $client,
    ) {}

    /**
     * Read and parse one full logical response line.
     *
     * @return list<mixed> Top-level tokens: strings, null (for `NIL`),
     *                      and nested arrays for parenthesized lists.
     */
    public function readLine(): array
    {
        $this->buffer = '';
        $this->pos = 0;
        $this->fill();

        return $this->parseTokens(0);
    }

    /**
     * @return list<mixed>
     */
    private function parseTokens(int $depth): array
    {
        $tokens = [];

        while (true) {
            $this->skipSpaces();

            if ($this->pos >= strlen($this->buffer)) {
                // A line ends here unless we are inside an unclosed
                // list, in which case the server has more of this
                // response coming on the next physical line.
                if ($depth === 0) {
                    return $tokens;
                }

                $this->fill();
                continue;
            }

            $char = $this->buffer[$this->pos];

            if ($char === ')') {
                ++$this->pos;

                return $tokens;
            }

            if ($char === '(') {
                ++$this->pos;
                $tokens[] = $this->parseTokens($depth + 1);
                continue;
            }

            $tokens[] = $this->parseToken();
        }
    }

    private function parseToken(): ?string
    {
        if ($this->buffer[$this->pos] === '"') {
            return $this->parseQuotedString();
        }

        return $this->parseAtomOrLiteral();
    }

    private function parseQuotedString(): string
    {
        ++$this->pos;
        $result = '';

        while (true) {
            if ($this->pos >= strlen($this->buffer)) {
                // A quoted string should never span physical lines but
                // stay lenient and just ask for more rather than fail.
                $this->fill();
                continue;
            }

            $char = $this->buffer[$this->pos];

            if ($char === '\\') {
                $next = $this->buffer[$this->pos + 1] ?? null;

                if ($next === null) {
                    $this->fill();
                    continue;
                }

                $result .= $next;
                $this->pos += 2;
                continue;
            }

            if ($char === '"') {
                ++$this->pos;

                return $result;
            }

            $result .= $char;
            ++$this->pos;
        }
    }

    private function parseAtomOrLiteral(): ?string
    {
        $text = '';

        while (true) {
            if ($this->pos >= strlen($this->buffer)) {
                $literal = $this->resolvePendingLiteral($text);

                if ($literal !== null) {
                    return $literal;
                }

                // End of the physical line, and not a literal
                // announcement: The token is simply complete. Whether
                // the overall response needs another physical line
                // (because we're inside an unclosed list) is decided
                // by the caller.
                break;
            }

            $char = $this->buffer[$this->pos];

            if ($char === ' ' || $char === '(' || $char === ')') {
                break;
            }

            $text .= $char;
            ++$this->pos;
        }

        return $this->interpretAtom($text);
    }

    private function interpretAtom(string $text): ?string
    {
        return strcasecmp($text, 'NIL') === 0 ? null : $text;
    }

    /**
     * If the token accumulated so far is a complete literal
     * announcement (`{n}` or `~{n}`), read its payload and return it.
     * Otherwise, return null. The caller should fetch more data and
     * keep accumulating.
     */
    private function resolvePendingLiteral(string $text): ?string
    {
        if (preg_match('/^(~?)\{(\d+)\+?\}$/', $text, $matches) !== 1) {
            return null;
        }

        $length = (int) $matches[2];

        return $this->client->read($length);
    }

    private function skipSpaces(): void
    {
        while (($this->buffer[$this->pos] ?? '') === ' ') {
            ++$this->pos;
        }
    }

    private function fill(): void
    {
        $line = $this->client->gets(self::LINE_BUFFER);

        if ($line === '') {
            throw new ImapProtocolException('Connection closed while reading an IMAP response.');
        }

        $this->buffer .= rtrim($line, "\r\n");
    }
}
