<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src\Auth;

use Horde\Sasl\Credentials\ScramCredential;
use Horde\Sasl\Credentials\ScramCredentialLookup;
use Horde\Sasl\Exception\AuthenticationFailedException;

/**
 * A minimal in-memory {@see ScramCredentialLookup} for tests.
 *
 * Derives records from a plaintext password via
 * {@see ScramCredential::fromPassword()} purely for test convenience; a
 * real implementation would persist pre-derived StoredKey/ServerKey pairs.
 */
final class InMemoryScramCredentialLookup implements ScramCredentialLookup
{
    /**
     * @param array<string, array{password: string, salt: string, iterations: int}> $users
     */
    public function __construct(
        private readonly array $users,
    ) {}

    public function lookup(string $authcid, string $hashAlgo): ScramCredential
    {
        if (!isset($this->users[$authcid])) {
            throw new AuthenticationFailedException('Unknown user.');
        }

        $user = $this->users[$authcid];

        return ScramCredential::fromPassword(
            $user['password'],
            $user['salt'],
            $user['iterations'],
            $hashAlgo,
        );
    }
}
