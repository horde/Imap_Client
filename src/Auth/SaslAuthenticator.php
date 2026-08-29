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

namespace Horde\Imap\Client\Auth;

use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\Event\AuthenticationFailed;
use Horde\Imap\Client\Event\AuthenticationSucceeded;
use Horde\Imap\Client\Exception\AuthenticationException;
use Horde\Sasl\ChannelBinding\ChannelBindingProvider;
use Horde\Sasl\ClientMechanism;
use Horde\Sasl\Credentials\Credentials;
use Horde\Sasl\Data\AdditionalData;
use Horde\Sasl\Data\Challenge;
use Horde\Sasl\Exception\AuthenticationFailedException;
use Horde\Sasl\Exception\DowngradeDetectedException;
use Horde\Sasl\Exception\MechanismException;
use Horde\Sasl\Exception\PolicyViolationException;
use Horde\Sasl\Exception\UnsupportedMechanismException;
use Horde\Sasl\MechanismName;
use Horde\Sasl\Negotiation\ClientMechanismFactory;
use Horde\Sasl\Negotiation\DefaultClientMechanismFactory;
use Horde\Sasl\Negotiation\Negotiator;
use Horde\Sasl\Negotiation\SaslPolicy;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Drives an IMAP `AUTHENTICATE` exchange over horde/Sasl.
 *
 * Maps `Horde\Sasl\ClientMechanism` onto IMAP's continuation protocol via the
 * minimal {@see AuthenticationChannel} seam, so this class works whether the
 * channel is backed by a fake in-memory transport (tests) or the real
 * command pipeline once it exists.
 *
 * Handles the one genuine ambiguity in that mapping: IMAP's wire cannot
 * distinguish "next challenge" from "final additional-data". Both are an
 * identical `+ <base64>` continuation. This is resolved with a static,
 * mechanism-intrinsic fact (the SCRAM family and DIGEST-MD5 expect exactly
 * one more continuation after their terminal `step()` call. Every other
 * mechanism does not), kept as a small internal lookup table rather than a
 * `horde/Sasl` interface addition
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class SaslAuthenticator
{
    /**
     * Mechanisms whose exchange delivers server-verification data via
     * `consumeAdditionalData()` after their one-and-only `step()` call
     * (RFC 5802 §3 SCRAM server signature).
     */
    private const EXPECTS_ADDITIONAL_DATA = [
        MechanismName::ScramSha1,
        MechanismName::ScramSha1Plus,
        MechanismName::ScramSha256,
        MechanismName::ScramSha256Plus,
        MechanismName::ScramSha512,
        MechanismName::ScramSha512Plus,
    ];

    private ?Credentials $credentials;

    public function __construct(
        private readonly ConnectionConfig $config,
        ?Credentials $credentials = null,
        private readonly ?ChannelBindingProvider $binding = null,
        private readonly ?EventDispatcherInterface $dispatcher = null,
        private readonly ClientMechanismFactory $factory = new DefaultClientMechanismFactory(),
        private readonly Negotiator $negotiator = new Negotiator(),
    ) {
        $this->credentials = $credentials;
    }

    /**
     * Run one full `AUTHENTICATE` exchange to completion.
     *
     * @param list<string>  $offeredMechanisms The mechanism names the server
     *                                        advertised (e.g. from
     *                                        `Capability::getParams('AUTH')`).
     * @param bool          $tlsActive         Whether the transport currently
     *                                        has an active TLS session.
     * @param Credentials|null $credentials    Supply (or replace) the
     *                                        credential for this call. For
     *                                        STARTTLS-deferred auth or a
     *                                        refreshed token. Falls back to
     *                                        the credential supplied at
     *                                        construction if omitted.
     *
     * @throws AuthenticationException If no mechanism can be negotiated, the
     *                                the server rejects the exchange, or the
     *                                exchange is otherwise malformed.
     */
    public function authenticate(
        AuthenticationChannel $channel,
        array $offeredMechanisms,
        bool $tlsActive,
        ?Credentials $credentials = null,
    ): void {
        if ($credentials !== null) {
            $this->credentials = $credentials;
        }

        if ($this->credentials === null) {
            throw new AuthenticationException(
                'No credentials supplied: pass them to the constructor or to authenticate().'
            );
        }

        $policy = $this->config->saslPolicy ?? SaslPolicy::secureDefaults();

        [$mechanismName, $mechanism] = $this->negotiate($offeredMechanisms, $this->credentials, $policy, $tlsActive);

        $initial = $mechanism->initialResponse();
        $channel->sendAuthenticate(
            $mechanismName->value,
            $initial->hasData() ? $initial->octets() : null,
        );

        $this->runExchange($channel, $mechanism, $mechanismName);
    }

    /**
     * @return array{0: MechanismName, 1: ClientMechanism}
     */
    private function negotiate(
        array $offeredMechanisms,
        Credentials $credentials,
        SaslPolicy $policy,
        bool $tlsActive,
    ): array {
        try {
            $mechanismName = $this->negotiator->select(
                $offeredMechanisms,
                $this->factory,
                $credentials,
                $policy,
                $tlsActive,
                // Downgrade-attack (TOFU pinning) detection deferred. No
                // persistence point exists yet.
                null,
            );

            $binding = $mechanismName->usesChannelBinding() ? $this->binding : null;
            $mechanism = $this->factory->create($mechanismName, $credentials, $binding);
        } catch (UnsupportedMechanismException | PolicyViolationException | DowngradeDetectedException $e) {
            $this->dispatch(new AuthenticationFailed($e->getMessage()));

            throw new AuthenticationException($e->getMessage(), 0, $e);
        }

        return [$mechanismName, $mechanism];
    }

    private function runExchange(
        AuthenticationChannel $channel,
        ClientMechanism $mechanism,
        MechanismName $mechanismName,
    ): void {
        $awaitingAdditionalData = false;

        try {
            while (true) {
                $event = $channel->nextEvent();

                if ($event->isOutcome()) {
                    if ($event->isSuccess() && $awaitingAdditionalData) {
                        // The server skipped the additional-data round
                        // (RFC 5802's server-final message) and jumped
                        // straight to OK. The server signature was never
                        // verified and must be treated as untrusted (potential MITM).
                        throw new AuthenticationFailedException(
                            'Server reported success without sending the expected additional-data'
                                . ' round. Its proof could not be verified.'
                        );
                    }

                    $this->concludeExchange($event, $mechanismName);

                    return;
                }

                if ($awaitingAdditionalData) {
                    $mechanism->consumeAdditionalData(AdditionalData::bytes($event->payload()));
                    $channel->sendResponse('');
                    $awaitingAdditionalData = false;

                    continue;
                }

                // Every mechanism's own step() already rejects an unexpected
                // continuation with its most specific exception. A plain
                // MechanismException for PLAIN/LOGIN/EXTERNAL/ANONYMOUS, but
                // an AuthenticationFailedException carrying the server's JSON
                // error detail for OAUTHBEARER/XOAUTH2 (RFC 7628 §3.1's
                // failure challenge). A generic pre-guard here would swallow
                // that detail before step() ever got to raise it.
                $response = $mechanism->step(Challenge::bytes($event->payload()));
                $channel->sendResponse($response->hasData() ? $response->octets() : '');

                if (in_array($mechanismName, self::EXPECTS_ADDITIONAL_DATA, true)) {
                    // SCRAM's client mechanism sends exactly one step()
                    // response (the client-final message) and only
                    // reaches isComplete() after consumeAdditionalData()
                    // verifies the server signature. So the next
                    // continuation is always routed there unconditionally.
                    $awaitingAdditionalData = true;
                }
            }
        } catch (MechanismException | AuthenticationFailedException $e) {
            $channel->cancel();
            $this->dispatch(new AuthenticationFailed($e->getMessage()));

            throw new AuthenticationException($e->getMessage(), 0, $e);
        }
    }

    private function concludeExchange(ChannelEvent $event, MechanismName $mechanismName): void
    {
        if (!$event->isSuccess()) {
            $this->dispatch(new AuthenticationFailed($event->text()));

            throw new AuthenticationException(
                $event->text() !== '' ? $event->text() : 'Authentication failed.'
            );
        }

        $this->dispatch(new AuthenticationSucceeded($mechanismName->value));
    }

    private function dispatch(object $event): void
    {
        $this->dispatcher?->dispatch($event);
    }
}
