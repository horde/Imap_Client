<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src\Auth;

use Horde\Imap\Client\Auth\AuthenticationChannel;
use Horde\Imap\Client\Auth\ChannelEvent;
use Horde\Sasl\Data\Response;
use Horde\Sasl\Exception\AuthenticationFailedException;
use Horde\Sasl\Exception\MechanismException;
use Horde\Sasl\ServerMechanism;

/**
 * An in-memory {@see AuthenticationChannel} that drives a real horde/Sasl
 * {@see ServerMechanism} on the other end.
 *
 * Lets tests exercise {@see \Horde\Imap\Client\Auth\SaslAuthenticator}
 * against an authentic protocol partner (real SCRAM proof verification,
 * real server-signature production) without a live IMAP server or any
 * command-pipeline scaffolding.
 *
 * Models the IMAP wire faithfully: a server mechanism's final additional
 * data (e.g. the SCRAM `v=...` signature) is surfaced as one more `+`
 * continuation, exactly as wire-ambiguous as a genuine challenge.
 * {@see \Horde\Imap\Client\Auth\SaslAuthenticator} is the one that must
 * know to route it to `consumeAdditionalData()` instead of `step()`.
 */
final class FakeServerChannel implements AuthenticationChannel
{
    public ?string $sentMechanismName = null;

    public ?string $sentInitialResponse = null;

    /** @var list<string> */
    public array $sentResponses = [];

    public bool $cancelled = false;

    /** Set once the additional-data continuation has been sent to the client. */
    private bool $additionalDataSent = false;

    private ChannelEvent $pending;

    public function __construct(
        private readonly ServerMechanism $server,
    ) {}

    public function sendAuthenticate(string $mechanismName, ?string $initialResponse): void
    {
        $this->sentMechanismName = $mechanismName;
        $this->sentInitialResponse = $initialResponse;

        if ($initialResponse !== null) {
            $this->pending = $this->advance($initialResponse);

            return;
        }

        // Server-first mechanism (e.g. CRAM-MD5): the server issues its
        // initial challenge before the client has sent anything.
        $challenge = $this->server->initialChallenge();
        $this->pending = ChannelEvent::challenge($challenge->hasData() ? $challenge->octets() : '');
    }

    public function nextEvent(): ChannelEvent
    {
        return $this->pending;
    }

    public function sendResponse(string $response): void
    {
        $this->sentResponses[] = $response;

        if ($this->additionalDataSent) {
            // The client just acknowledged the additional-data
            // continuation with an empty response; the exchange is done.
            $this->pending = ChannelEvent::success();

            return;
        }

        $this->pending = $this->advance($response);
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    /**
     * Feed one client message into the server mechanism and compute the
     * next event: another challenge, the additional-data continuation, the
     * final success outcome, or a tagged failure.
     *
     * A real server never throws a PHP exception at the wire. A rejected
     * exchange (bad credentials, malformed message) becomes a tagged `NO`.
     * This mirrors that by catching the server mechanism's own exceptions
     * and turning them into a failure event, exactly like a real server
     * would.
     */
    private function advance(string $clientMessage): ChannelEvent
    {
        try {
            $challenge = $this->server->step(Response::bytes($clientMessage));
        } catch (AuthenticationFailedException | MechanismException $e) {
            return ChannelEvent::failure(text: $e->getMessage());
        }

        if ($challenge->hasData()) {
            return ChannelEvent::challenge($challenge->octets());
        }

        if (!$this->server->isComplete()) {
            return ChannelEvent::challenge('');
        }

        $additionalData = $this->server->additionalData();
        if ($additionalData->hasData()) {
            $this->additionalDataSent = true;

            return ChannelEvent::challenge($additionalData->octets());
        }

        return ChannelEvent::success();
    }
}
