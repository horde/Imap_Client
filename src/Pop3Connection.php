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

use Generator;
use Horde\Imap\Client\Exception\Pop3ProtocolException;
use Horde\Socket\Client\ClientInterface;

/**
 * The POP3 line protocol (RFC 1939 §3, RFC 2449 §8) spoken over a
 * `Horde\Socket\Client\ClientInterface`.
 *
 * POP3 is almost entirely line-oriented: every response starts with `+OK`,
 * `-ERR`, or `+` (a SASL continuation, RFC 5034), and a handful of commands
 * (CAPA, LIST, UIDL, RETR, TOP) are followed by a `.`-terminated multiline
 * block using byte-stuffing (a line starting with `.` in the data is sent
 * as `..`). This class owns exactly that grammar. It has no opinion on
 * command semantics.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class Pop3Connection
{
    /**
     * Generous upper bound for a single line read. POP3 command/status
     * lines are capped at 255 octets (RFC 1939 §3), but multiline data
     * (RETR/TOP message content) has no such limit in practice.
     */
    private const LINE_BUFFER = 65536;

    public function __construct(
        private readonly ClientInterface $client,
    ) {}

    /**
     * Send a command and append CRLF.
     *
     * TODO: Should we check if CRLF is already present and avoid double-CRLF?
     */
    public function sendLine(string $command): void
    {
        $this->client->write($command . "\r\n");
    }

    /**
     * Read and parse the next status line, without raising an exception
     * for `-ERR`. The caller decides what a failure means to it. An
     * ordinary command throws via {@see expectOk()}; a SASL exchange
     * routes it into a {@see Auth\ChannelEvent} failure instead.
     *
     * @throws Pop3ProtocolException On a line that is none of `+OK`,
     *                                `-ERR`, or `+`.
     */
    public function readStatusLine(): Pop3StatusLine
    {
        $raw = rtrim($this->client->gets(self::LINE_BUFFER), "\r\n");
        $parts = explode(' ', $raw, 2);
        $indicator = $parts[0];
        $text = $parts[1] ?? '';

        $kind = match ($indicator) {
            '+OK' => Pop3ResponseKind::Ok,
            '-ERR' => Pop3ResponseKind::Error,
            '+' => Pop3ResponseKind::Continuation,
            default => throw new Pop3ProtocolException(
                'Error when communicating with the mail server.',
            ),
        };

        return new Pop3StatusLine($kind, $text);
    }

    /**
     * Read the next status line and require it to be `+OK`.
     *
     * @throws Pop3ProtocolException On `-ERR` (the response text becomes
     *                                the exception message).
     */
    public function expectOk(): Pop3StatusLine
    {
        $line = $this->readStatusLine();

        if ($line->isError()) {
            throw new Pop3ProtocolException(
                $line->text === '' ? 'POP3 error reported by server.' : $line->text,
            );
        }

        return $line;
    }

    /**
     * Send a command and require the response to be `+OK`.
     *
     * @throws Pop3ProtocolException On `-ERR`.
     */
    public function expectOkFor(string $command): Pop3StatusLine
    {
        $this->sendLine($command);

        return $this->expectOk();
    }

    /**
     * Read a `.`-terminated multiline block, yielding one un-byte-stuffed
     * line at a time (the terminating `.` line itself is consumed but not
     * yielded).
     *
     * @return Generator<int, string>
     */
    public function readMultiline(): Generator
    {
        while (true) {
            $raw = rtrim($this->client->gets(self::LINE_BUFFER), "\r\n");

            if ($raw === '.') {
                return;
            }

            yield str_starts_with($raw, '..') ? substr($raw, 1) : $raw;
        }
    }
}
