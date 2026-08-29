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

namespace Horde\Imap\Client\Test\Unit\Src\Auth;

use Horde\Sasl\Data\AdditionalData;
use Horde\Sasl\Data\Challenge;
use Horde\Sasl\Data\Response;
use Horde\Sasl\MechanismName;
use Horde\Sasl\Role;
use Horde\Sasl\ServerMechanism;

/**
 * A minimal {@see ServerMechanism} for the client-first, single-shot
 * mechanisms that horde/Sasl does not ship a server side for (OAUTHBEARER,
 * XOAUTH2, EXTERNAL, ANONYMOUS). Real IMAP servers implement these, but
 * horde/Sasl's `ServerMechanism` roster is currently limited to what it
 * needs to test its own client mechanisms against.
 *
 * On success: accepts the client's one-and-only message, completes with no
 * additional data. On failure: mimics RFC 7628 §3.1's behavior of returning
 * the error detail as one more `+` challenge (for OAUTHBEARER/XOAUTH2) when
 * `$failureAsChallenge` is true, or otherwise rejects the response as a
 * tagged failure via a thrown exception (EXTERNAL/ANONYMOUS/any other
 * single-shot mechanism, which have no failure-challenge convention).
 */
final class SingleShotFakeServerMechanism implements ServerMechanism
{
    private bool $complete = false;

    public function __construct(
        private readonly MechanismName $mechanismName,
        private readonly bool $shouldFail = false,
        private readonly bool $failureAsChallenge = false,
        private readonly string $failureDetail = 'rejected',
    ) {}

    public function name(): MechanismName
    {
        return $this->mechanismName;
    }

    public function role(): Role
    {
        return Role::Server;
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function usesChannelBinding(): bool
    {
        return false;
    }

    public function initialChallenge(): Challenge
    {
        // Client-first: the server waits for the client's first message.
        return Challenge::none();
    }

    public function step(Response $response): Challenge
    {
        if ($this->shouldFail) {
            if ($this->failureAsChallenge) {
                return Challenge::bytes($this->failureDetail);
            }

            throw new \Horde\Sasl\Exception\AuthenticationFailedException($this->failureDetail);
        }

        $this->complete = true;

        return Challenge::none();
    }

    public function additionalData(): AdditionalData
    {
        return AdditionalData::none();
    }

    public function authorizationId(): string
    {
        return 'alice';
    }
}
