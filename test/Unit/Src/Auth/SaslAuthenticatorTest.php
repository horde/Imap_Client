<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src\Auth;

use Horde\Imap\Client\Auth\SaslAuthenticator;
use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\Event\AuthenticationFailed;
use Horde\Imap\Client\Event\AuthenticationSucceeded;
use Horde\Imap\Client\Exception\AuthenticationException;
use Horde\Sasl\Credentials\AnonymousCredentials;
use Horde\Sasl\Credentials\ExternalCredentials;
use Horde\Sasl\Credentials\PasswordCredentials;
use Horde\Sasl\Credentials\PlainSecret;
use Horde\Sasl\Credentials\ScramCredential;
use Horde\Sasl\Credentials\ScramCredentialLookup;
use Horde\Sasl\Credentials\TokenCredentials;
use Horde\Sasl\Exception\AuthenticationFailedException;
use Horde\Sasl\Mechanism\CramMd5ServerMechanism;
use Horde\Sasl\Mechanism\PlainServerMechanism;
use Horde\Sasl\Mechanism\ScramServerMechanism;
use Horde\Sasl\MechanismName;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(SaslAuthenticator::class)]
class SaslAuthenticatorTest extends TestCase
{
    private function credentials(string $user = 'alice', string $password = 'password123'): PasswordCredentials
    {
        return new PasswordCredentials($user, new PlainSecret($password));
    }

    private function config(?SaslPolicy $policy = null): ConnectionConfig
    {
        return new ConnectionConfig(saslPolicy: $policy ?? SaslPolicy::legacyCompatible());
    }

    public function testPlainAuthenticationSucceeds(): void
    {
        $lookup = new class implements \Horde\Sasl\Credentials\PasswordLookup {
            public function password(string $authcid): \Horde\Sasl\Credentials\Secret
            {
                return new PlainSecret('password123');
            }
        };
        $server = new PlainServerMechanism($lookup);
        $channel = new FakeServerChannel($server);

        $events = [];
        $dispatcher = $this->dispatcherRecording($events);

        $auth = new SaslAuthenticator($this->config(), $this->credentials(), null, $dispatcher);
        $auth->authenticate($channel, [MechanismName::Plain->value], tlsActive: false);

        $this->assertSame(MechanismName::Plain->value, $channel->sentMechanismName);
        $this->assertFalse($channel->cancelled);
        $this->assertCount(1, $events);
        $this->assertInstanceOf(AuthenticationSucceeded::class, $events[0]);
    }

    public function testPlainAuthenticationRejectedByServer(): void
    {
        $lookup = new class implements \Horde\Sasl\Credentials\PasswordLookup {
            public function password(string $authcid): \Horde\Sasl\Credentials\Secret
            {
                throw new AuthenticationFailedException('Unknown user.');
            }
        };
        $server = new PlainServerMechanism($lookup);
        $channel = new FakeServerChannel($server);

        $events = [];
        $dispatcher = $this->dispatcherRecording($events);

        $auth = new SaslAuthenticator($this->config(), $this->credentials(), null, $dispatcher);

        $this->expectException(AuthenticationException::class);

        try {
            $auth->authenticate($channel, [MechanismName::Plain->value], tlsActive: false);
        } finally {
            // A tagged NO is the server's own final answer. Nothing to do
            // for a client-side cancel().
            $this->assertFalse($channel->cancelled);
            $this->assertCount(1, $events);
            $this->assertInstanceOf(AuthenticationFailed::class, $events[0]);
        }
    }

    public function testScramSha256RoundTripSucceedsAndVerifiesServerSignature(): void
    {
        $lookup = new InMemoryScramCredentialLookup([
            'alice' => ['password' => 'password123', 'salt' => random_bytes(16), 'iterations' => 4096],
        ]);
        $server = new ScramServerMechanism(MechanismName::ScramSha256, $lookup);
        $channel = new FakeServerChannel($server);

        $events = [];
        $dispatcher = $this->dispatcherRecording($events);

        $auth = new SaslAuthenticator($this->config(), $this->credentials(), null, $dispatcher);
        $auth->authenticate($channel, [MechanismName::ScramSha256->value], tlsActive: true);

        $this->assertSame(MechanismName::ScramSha256->value, $channel->sentMechanismName);
        // client-first (inlined), client-final, and the empty ack of the
        // server's additional-data continuation.
        $this->assertCount(2, $channel->sentResponses);
        $this->assertSame('', $channel->sentResponses[1]);
        $this->assertFalse($channel->cancelled);
        $this->assertInstanceOf(AuthenticationSucceeded::class, $events[0]);
    }

    public function testScramWrongPasswordIsRejectedByServerAsTaggedFailure(): void
    {
        $lookup = new InMemoryScramCredentialLookup([
            'alice' => ['password' => 'correct-password', 'salt' => random_bytes(16), 'iterations' => 4096],
        ]);
        $server = new ScramServerMechanism(MechanismName::ScramSha256, $lookup);
        $channel = new FakeServerChannel($server);

        $auth = new SaslAuthenticator($this->config(), $this->credentials('alice', 'wrong-password'));

        $this->expectException(AuthenticationException::class);

        try {
            $auth->authenticate($channel, [MechanismName::ScramSha256->value], tlsActive: true);
        } finally {
            // The server rejects the bad proof as a tagged NO. A normal
            // credentials failure, not a client-side abort.
            $this->assertFalse($channel->cancelled);
        }
    }

    public function testForgedServerSignatureTriggersClientSideCancel(): void
    {
        // A server that completes successfully but never sends a genuine
        // additional-data verifier. Simulating a MITM that cannot produce
        // a valid SCRAM server signature. The client mechanism's own
        // consumeAdditionalData() must reject it and the authenticator
        // must cancel() client-side rather than trust a bare OK.
        $server = new class implements \Horde\Sasl\ServerMechanism {
            private bool $firstStepDone = false;

            private bool $complete = false;

            public function name(): MechanismName
            {
                return MechanismName::ScramSha256;
            }

            public function role(): \Horde\Sasl\Role
            {
                return \Horde\Sasl\Role::Server;
            }

            public function isComplete(): bool
            {
                return $this->complete;
            }

            public function usesChannelBinding(): bool
            {
                return false;
            }

            public function initialChallenge(): \Horde\Sasl\Data\Challenge
            {
                return \Horde\Sasl\Data\Challenge::none();
            }

            public function step(\Horde\Sasl\Data\Response $response): \Horde\Sasl\Data\Challenge
            {
                if (!$this->firstStepDone) {
                    $this->firstStepDone = true;

                    return \Horde\Sasl\Data\Challenge::bytes('r=fakenonce,s=' . base64_encode('salt') . ',i=4096');
                }

                $this->complete = true;

                return \Horde\Sasl\Data\Challenge::none();
            }

            public function additionalData(): \Horde\Sasl\Data\AdditionalData
            {
                // A forged, garbage server signature. The client mechanism
                // must reject this itself.
                return \Horde\Sasl\Data\AdditionalData::bytes('v=' . base64_encode('not-the-real-signature'));
            }

            public function authorizationId(): string
            {
                return 'alice';
            }
        };
        $channel = new FakeServerChannel($server);

        $auth = new SaslAuthenticator($this->config(), $this->credentials());

        $this->expectException(AuthenticationException::class);

        try {
            $auth->authenticate($channel, [MechanismName::ScramSha256->value], tlsActive: true);
        } finally {
            $this->assertTrue($channel->cancelled);
        }
    }

    public function testCramMd5ServerFirstMechanism(): void
    {
        $lookup = new class implements \Horde\Sasl\Credentials\PasswordLookup {
            public function password(string $authcid): \Horde\Sasl\Credentials\Secret
            {
                return new PlainSecret('password123');
            }
        };
        $server = new CramMd5ServerMechanism($lookup, 'fixed-test-challenge');
        $channel = new FakeServerChannel($server);

        $auth = new SaslAuthenticator($this->config(), $this->credentials());
        $auth->authenticate($channel, [MechanismName::CramMd5->value], tlsActive: false);

        // Server-first: no initial response is inlined.
        $this->assertNull($channel->sentInitialResponse);
        $this->assertFalse($channel->cancelled);
    }

    public function testMissingCredentialsThrows(): void
    {
        $auth = new SaslAuthenticator($this->config());
        $server = new PlainServerMechanism(new class implements \Horde\Sasl\Credentials\PasswordLookup {
            public function password(string $authcid): \Horde\Sasl\Credentials\Secret
            {
                return new PlainSecret('x');
            }
        });
        $channel = new FakeServerChannel($server);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No credentials supplied');

        $auth->authenticate($channel, [MechanismName::Plain->value], tlsActive: false);
    }

    public function testDeferredCredentialsSuppliedAtAuthenticateCall(): void
    {
        $lookup = new class implements \Horde\Sasl\Credentials\PasswordLookup {
            public function password(string $authcid): \Horde\Sasl\Credentials\Secret
            {
                return new PlainSecret('password123');
            }
        };
        $server = new PlainServerMechanism($lookup);
        $channel = new FakeServerChannel($server);

        // No credential at construction (e.g. STARTTLS deferred).
        $auth = new SaslAuthenticator($this->config());
        $auth->authenticate(
            $channel,
            [MechanismName::Plain->value],
            tlsActive: true,
            credentials: $this->credentials(),
        );

        $this->assertFalse($channel->cancelled);
    }

    public function testUnsupportedMechanismListThrowsAuthenticationException(): void
    {
        $auth = new SaslAuthenticator($this->config(), $this->credentials());
        $server = new PlainServerMechanism(new class implements \Horde\Sasl\Credentials\PasswordLookup {
            public function password(string $authcid): \Horde\Sasl\Credentials\Secret
            {
                return new PlainSecret('x');
            }
        });
        $channel = new FakeServerChannel($server);

        $this->expectException(AuthenticationException::class);

        // Server only offers a mechanism this build/credential combination
        // cannot use.
        $auth->authenticate($channel, ['GSSAPI'], tlsActive: true);
    }

    public function testSecureDefaultsPolicyRejectsPlainEvenWithTls(): void
    {
        // secureDefaults() sets a minimum mechanism strength of Token,
        // which excludes Plaintext-strength PLAIN/LOGIN unconditionally.
        // Not merely "without TLS". Confirms the policy is actually wired
        // through, not just passed and ignored.
        $auth = new SaslAuthenticator($this->config(SaslPolicy::secureDefaults()), $this->credentials());
        $server = new PlainServerMechanism(new class implements \Horde\Sasl\Credentials\PasswordLookup {
            public function password(string $authcid): \Horde\Sasl\Credentials\Secret
            {
                return new PlainSecret('x');
            }
        });
        $channel = new FakeServerChannel($server);

        $this->expectException(AuthenticationException::class);

        $auth->authenticate($channel, [MechanismName::Plain->value], tlsActive: true);
    }

    public function testSecureDefaultsPolicyAllowsScram(): void
    {
        $lookup = new InMemoryScramCredentialLookup([
            'alice' => ['password' => 'password123', 'salt' => random_bytes(16), 'iterations' => 4096],
        ]);
        $server = new ScramServerMechanism(MechanismName::ScramSha256, $lookup);
        $channel = new FakeServerChannel($server);

        $auth = new SaslAuthenticator($this->config(SaslPolicy::secureDefaults()), $this->credentials());
        $auth->authenticate($channel, [MechanismName::ScramSha256->value], tlsActive: true);

        $this->assertFalse($channel->cancelled);
    }

    public function testOAuthBearerAuthenticationSucceeds(): void
    {
        $server = new SingleShotFakeServerMechanism(MechanismName::OAuthBearer);
        $channel = new FakeServerChannel($server);
        $credentials = new TokenCredentials('alice', new PlainSecret('access-token'));

        $auth = new SaslAuthenticator($this->config(), $credentials);
        $auth->authenticate($channel, [MechanismName::OAuthBearer->value], tlsActive: true);

        $this->assertNotNull($channel->sentInitialResponse);
        $this->assertFalse($channel->cancelled);
    }

    public function testOAuthBearerFailureChallengeSurfacesServerDetailAndIsNotSwallowed(): void
    {
        // RFC 7628 §3.1: on failure the server does not send a plain
        // rejection but a JSON error object as one more `+` continuation.
        // The client mechanism's own step() converts that into an
        // AuthenticationFailedException carrying the detail. Confirms that the
        // adapter's loop calls step() here.
        $server = new SingleShotFakeServerMechanism(
            MechanismName::OAuthBearer,
            shouldFail: true,
            failureAsChallenge: true,
            failureDetail: '{"status":"invalid_token"}',
        );
        $channel = new FakeServerChannel($server);
        $credentials = new TokenCredentials('alice', new PlainSecret('expired-token'));

        $auth = new SaslAuthenticator($this->config(), $credentials);

        try {
            $auth->authenticate($channel, [MechanismName::OAuthBearer->value], tlsActive: true);
            $this->fail('Expected an AuthenticationException.');
        } catch (AuthenticationException $e) {
            $this->assertStringContainsString('invalid_token', $e->getMessage());
            $this->assertTrue($channel->cancelled);
        }
    }

    public function testXoauth2AuthenticationSucceeds(): void
    {
        $server = new SingleShotFakeServerMechanism(MechanismName::Xoauth2);
        $channel = new FakeServerChannel($server);
        $credentials = new TokenCredentials('alice', new PlainSecret('access-token'));

        $auth = new SaslAuthenticator($this->config(), $credentials);
        $auth->authenticate($channel, [MechanismName::Xoauth2->value], tlsActive: true);

        $this->assertFalse($channel->cancelled);
    }

    public function testXoauth2FailureChallengeSurfacesServerDetail(): void
    {
        $server = new SingleShotFakeServerMechanism(
            MechanismName::Xoauth2,
            shouldFail: true,
            failureAsChallenge: true,
            failureDetail: '{"status":"invalid_token"}',
        );
        $channel = new FakeServerChannel($server);
        $credentials = new TokenCredentials('alice', new PlainSecret('expired-token'));

        $auth = new SaslAuthenticator($this->config(), $credentials);

        try {
            $auth->authenticate($channel, [MechanismName::Xoauth2->value], tlsActive: true);
            $this->fail('Expected an AuthenticationException.');
        } catch (AuthenticationException $e) {
            $this->assertStringContainsString('invalid_token', $e->getMessage());
            $this->assertTrue($channel->cancelled);
        }
    }

    public function testExternalAuthenticationSucceeds(): void
    {
        // EXTERNAL derives identity purely from the channel (e.g. a client
        // TLS certificate); no secret is exchanged at all.
        $server = new SingleShotFakeServerMechanism(MechanismName::External);
        $channel = new FakeServerChannel($server);
        $credentials = new ExternalCredentials('alice');

        $auth = new SaslAuthenticator($this->config(), $credentials);
        $auth->authenticate($channel, [MechanismName::External->value], tlsActive: true);

        $this->assertFalse($channel->cancelled);
    }

    public function testExternalAuthenticationRejectedByServer(): void
    {
        $server = new SingleShotFakeServerMechanism(
            MechanismName::External,
            shouldFail: true,
            failureDetail: 'no certificate presented',
        );
        $channel = new FakeServerChannel($server);
        $credentials = new ExternalCredentials('alice');

        $auth = new SaslAuthenticator($this->config(), $credentials);

        $this->expectException(AuthenticationException::class);

        $auth->authenticate($channel, [MechanismName::External->value], tlsActive: true);
    }

    public function testAnonymousAuthenticationSucceeds(): void
    {
        // ANONYMOUS's strength (-1) is below even legacyCompatible()'s
        // minimum (Plaintext, 0). It is deliberately excluded by every
        // named policy factory. Allowing ANONYMOUS needs an explicit
        // opt-in policy.
        $server = new SingleShotFakeServerMechanism(MechanismName::Anonymous);
        $channel = new FakeServerChannel($server);
        $credentials = new AnonymousCredentials('guest@example.com');
        $policy = new SaslPolicy(
            minimumStrength: \Horde\Sasl\MechanismStrength::Anonymous,
            requireTlsForPlaintext: false,
            requireChannelBinding: false,
            denied: [],
        );

        $auth = new SaslAuthenticator($this->config($policy), $credentials);
        $auth->authenticate($channel, [MechanismName::Anonymous->value], tlsActive: false);

        $this->assertFalse($channel->cancelled);
    }

    public function testAnonymousRejectedByLegacyCompatiblePolicy(): void
    {
        // Confirms ANONYMOUS is excluded even by the permissive named
        // policy, not just by secureDefaults(). It needs the bespoke
        // opt-in policy from testAnonymousAuthenticationSucceeds().
        $server = new SingleShotFakeServerMechanism(MechanismName::Anonymous);
        $channel = new FakeServerChannel($server);
        $credentials = new AnonymousCredentials();

        $auth = new SaslAuthenticator($this->config(SaslPolicy::legacyCompatible()), $credentials);

        $this->expectException(AuthenticationException::class);

        $auth->authenticate($channel, [MechanismName::Anonymous->value], tlsActive: true);
    }

    public function testAnonymousRejectedBySecureDefaultsPolicy(): void
    {
        $server = new SingleShotFakeServerMechanism(MechanismName::Anonymous);
        $channel = new FakeServerChannel($server);
        $credentials = new AnonymousCredentials();

        $auth = new SaslAuthenticator($this->config(SaslPolicy::secureDefaults()), $credentials);

        $this->expectException(AuthenticationException::class);

        $auth->authenticate($channel, [MechanismName::Anonymous->value], tlsActive: true);
    }

    public function testNegotiatorChoosesStrongestOfMultipleOfferedMechanisms(): void
    {
        // The server offers PLAIN, CRAM-MD5, and SCRAM-SHA-256; under
        // legacyCompatible() all three are permitted, so the Negotiator
        // must select the strongest (SCRAM), not just the first offered.
        $lookup = new InMemoryScramCredentialLookup([
            'alice' => ['password' => 'password123', 'salt' => random_bytes(16), 'iterations' => 4096],
        ]);
        $server = new ScramServerMechanism(MechanismName::ScramSha256, $lookup);
        $channel = new FakeServerChannel($server);

        $auth = new SaslAuthenticator($this->config(), $this->credentials());
        $auth->authenticate(
            $channel,
            [MechanismName::Plain->value, MechanismName::CramMd5->value, MechanismName::ScramSha256->value],
            tlsActive: true,
        );

        $this->assertSame(MechanismName::ScramSha256->value, $channel->sentMechanismName);
        $this->assertFalse($channel->cancelled);
    }

    public function testDowngradeDetectionStaysDisabledWithoutPinnedMechanisms(): void
    {
        // No persistence point for trust-on-first-use pinning exists yet
        // Confirms omitting it never throws
        // DowngradeDetectedException, even when a weaker mechanism than
        // what could theoretically be offered is chosen.
        $server = new PlainServerMechanism(new class implements \Horde\Sasl\Credentials\PasswordLookup {
            public function password(string $authcid): \Horde\Sasl\Credentials\Secret
            {
                return new PlainSecret('password123');
            }
        });
        $channel = new FakeServerChannel($server);

        $auth = new SaslAuthenticator($this->config(SaslPolicy::legacyCompatible()), $this->credentials());
        $auth->authenticate($channel, [MechanismName::Plain->value], tlsActive: false);

        $this->assertFalse($channel->cancelled);
    }

    /**
     * @param list<object> $events
     */
    private function dispatcherRecording(array &$events): EventDispatcherInterface
    {
        return new class ($events) implements EventDispatcherInterface {
            public function __construct(private array &$events) {}

            public function dispatch(object $event): object
            {
                $this->events[] = $event;

                return $event;
            }
        };
    }
}
