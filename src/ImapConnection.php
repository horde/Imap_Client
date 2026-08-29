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

use Horde\Imap\Client\Exception\ServerResponseException;
use Horde\Socket\Client\ClientInterface;

/**
 * The IMAP line/literal protocol (RFC 3501 §2.2, §4.3) spoken over a
 * `Horde\Socket\Client\ClientInterface`.
 *
 * Writing an {@see ImapCommand}'s segments in
 * order driving the `{n}`/`+`/raw-bytes exchange a literal argument
 * needs.
 * Reading the next response line off the wire and handing
 * it to {@see ImapResponseParser}.
 *
 * It has no opinion on what a command
 * or a response means and no notion of which command a given tagged
 * response belongs to. Matching a tagged response to the command it
 * completes is {@see ImapPipeline}'s job.
 *
 * Mirrors how `Pop3Connection` owns POP3's line grammar without knowing what a command means.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapConnection
{
    private readonly ImapTokenizer $tokenizer;

    public function __construct(
        private readonly ClientInterface $client,
    ) {
        $this->tokenizer = new ImapTokenizer($client);
    }

    /**
     * Write a command's segments, handles any literal arguments'
     * `{n}`/`+`/raw-bytes exchange as they come up.
     *
     * @param bool $nonSynchronizingLiterals Send literals as `{n+}` (RFC
     *             7888) instead of waiting for a `+` continuation.
     *             Only safe once the server's `LITERAL+`/`LITERAL-`
     *             support is confirmed.
     *
     * @return list<ImapResponse> Untagged responses seen while awaiting
     *                             a continuation. A server may
     *                             send one. Rare occurrence e.g. an alert.
     *
     * @throws ServerResponseException If the server responds to a
     *                                   literal announcement with
     *                                   anything but a continuation
     *                                   (a tagged rejection of the
     *                                   command, most commonly).
     */
    public function sendCommand(ImapCommand $command, bool $nonSynchronizingLiterals = false): array
    {
        $untagged = [];
        $buffer = '';

        foreach ($command->segments() as $segment) {
            if (!$segment->isLiteral) {
                $buffer .= $segment->text;
                continue;
            }

            $marker = ($segment->isBinary ? '~' : '')
                . '{' . $segment->length() . ($nonSynchronizingLiterals ? '+' : '') . "}\r\n";
            $this->client->write($buffer . $marker);
            $buffer = '';

            if (!$nonSynchronizingLiterals) {
                array_push($untagged, ...$this->awaitContinuation($command));
            }

            $this->client->write($segment->bytes);
        }

        $this->client->write($buffer . "\r\n");

        return $untagged;
    }

    /**
     * @return list<ImapResponse>
     */
    private function awaitContinuation(ImapCommand $command): array
    {
        $untagged = [];

        while (true) {
            $response = $this->readResponse();

            if ($response->isContinuation()) {
                return $untagged;
            }

            if ($response->isUntagged()) {
                $untagged[] = $response;
                continue;
            }

            throw new ServerResponseException(
                "Server rejected command '{$command->name}' before sending a literal continuation.",
                0,
                null,
                $command->name,
                null,
                $response->text,
            );
        }
    }

    /**
     * Write a bare line (no tag, no command name) such as a SASL
     * continuation reply or its `*` cancellation (RFC 3501 §6.2.2).
     */
    public function writeLine(string $line): void
    {
        $this->client->write($line . "\r\n");
    }

    /**
     * Read and parse the next response line.
     */
    public function readResponse(): ImapResponse
    {
        return ImapResponseParser::parse($this->tokenizer->readLine());
    }
}
