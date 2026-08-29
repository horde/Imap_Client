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
use Horde\Imap\Client\Exception\ServerResponseException;

/**
 * Sends one IMAP command and collects its full response cycle. Every
 * untagged response until the tagged response that completes it (RFC 3501 §2.2.1-2.2.2).
 *
 * {@see ImapPipeline} already tracks tags for true command pipelining
 * (RFC 3501 §5.5 sending a second command before the first one's
 * tagged response arrives), but `send()` itself only awaits its own
 * command's tag: A genuinely concurrent, multi-command pipeline (where
 * a caller wants to keep issuing commands and collect their tagged
 * responses out of order) is a capability {@see ImapPipeline} leaves
 * room for future enhancements.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapInteraction
{
    private readonly ImapCommandTag $tags;

    private readonly ImapPipeline $pipeline;

    public function __construct(
        private readonly ImapConnection $connection,
        ?ImapCommandTag $tags = null,
        ?ImapPipeline $pipeline = null,
    ) {
        $this->tags = $tags ?? new ImapCommandTag();
        $this->pipeline = $pipeline ?? new ImapPipeline();
    }

    public function newTag(): string
    {
        return $this->tags->next();
    }

    /**
     * Send a command built from `$name`/`$arguments` under a fresh tag
     * and collect its response cycle.
     *
     * @param iterable<ImapWireEncodable|string> $arguments
     *
     * @throws ServerResponseException If the command's own tagged
     *                                   response is `NO` or `BAD`.
     * @throws ImapProtocolException If the wire produces a response
     *                                 this exchange cannot make sense
     *                                 of (an unexpected continuation,
     *                                 or a tagged response for a
     *                                 command genuinely still pending
     *                                 elsewhere in the pipeline).
     */
    public function send(string $name, iterable $arguments = [], bool $nonSynchronizingLiterals = false): ImapCommandResult
    {
        $command = new ImapCommand($this->newTag(), $name, $arguments);

        return $this->run($command, $nonSynchronizingLiterals);
    }

    /**
     * Send an already-built command and collect its response cycle.
     */
    public function run(ImapCommand $command, bool $nonSynchronizingLiterals = false): ImapCommandResult
    {
        $this->pipeline->enqueue($command);
        $untagged = $this->connection->sendCommand($command, $nonSynchronizingLiterals);

        while (true) {
            $response = $this->connection->readResponse();

            if ($response->isContinuation()) {
                throw new ImapProtocolException(
                    'Unexpected continuation response outside of a literal exchange.',
                );
            }

            if ($response->isUntagged()) {
                $untagged[] = $response;
                continue;
            }

            if ($response->tag !== $command->tag) {
                if ($this->pipeline->complete($response->tag) === null) {
                    // A dangling tagged response - left over from an
                    // aborted earlier exchange, or spurious server
                    // output. Ignore it and keep waiting for our own.
                    continue;
                }

                throw new ImapProtocolException(
                    "Received a tagged response for '{$response->tag}' while awaiting"
                        . " '{$command->tag}'; concurrent multi-command pipelining is"
                        . ' not supported by this call.',
                );
            }

            $this->pipeline->complete($command->tag);

            if ($response->isNo() || $response->isBad()) {
                throw new ServerResponseException(
                    $response->text === '' ? 'IMAP error reported by server.' : $response->text,
                    0,
                    null,
                    $command->name,
                    $response->status?->label(),
                    $response->text,
                    $response->responseCode,
                );
            }

            return new ImapCommandResult($response, $untagged);
        }
    }

    /**
     * Send several commands as one pipelined burst (RFC 3501 §5.5) and
     * collect each one's result, keyed by tag.
     *
     * All commands are written first, then their tagged completions are
     * read as they arrive. Untagged responses are attributed to the next
     * command to complete (servers process a pipeline in order, so a
     * command's untagged data precedes its tagged completion). Unlike
     * {@see run()}, a `NO`/`BAD` completion does not throw: it is returned
     * as that command's {@see ImapCommandResult} so one failing command
     * does not abort the batch. The caller inspects each result's tagged
     * status.
     *
     * A command that needs a synchronizing-literal continuation cannot be
     * pipelined (only one continuation may be outstanding, RFC 3501 §5.5);
     * passing one is rejected.
     *
     * @param list<ImapCommand> $commands
     *
     * @return array<string, ImapCommandResult> Keyed by command tag.
     *
     * @throws ImapProtocolException If a command needs a continuation, or
     *                                 the wire desyncs (an unexpected
     *                                 continuation, or a completion for an
     *                                 unknown tag).
     */
    public function sendPipeline(array $commands): array
    {
        if ($commands === []) {
            return [];
        }

        foreach ($commands as $command) {
            if ($command->needsContinuation()) {
                throw new ImapProtocolException(
                    "Command '{$command->name}' needs a literal continuation and cannot be pipelined (RFC 3501 §5.5)."
                );
            }
        }

        // Write every command's segments back to back. sendCommand()
        // returns no untagged here because none carries a continuation.
        foreach ($commands as $command) {
            $this->pipeline->enqueue($command);
            $this->connection->sendCommand($command);
        }

        $expected = count($commands);
        $results = [];
        $pendingUntagged = [];

        while (count($results) < $expected) {
            $response = $this->connection->readResponse();

            if ($response->isContinuation()) {
                throw new ImapProtocolException(
                    'Unexpected continuation response in a pipelined command burst.',
                );
            }

            if ($response->isUntagged()) {
                $pendingUntagged[] = $response;
                continue;
            }

            $command = $this->pipeline->complete($response->tag);

            if ($command === null) {
                // Dangling/stale tagged response; ignore and keep reading.
                continue;
            }

            $results[$response->tag] = new ImapCommandResult($response, $pendingUntagged);
            $pendingUntagged = [];
        }

        return $results;
    }
}
