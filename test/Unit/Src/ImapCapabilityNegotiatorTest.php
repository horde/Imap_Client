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

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\ImapCapability;
use Horde\Imap\Client\ImapCapabilityNegotiator;
use Horde\Imap\Client\ImapConnection;
use Horde\Imap\Client\ImapInteraction;
use Horde\Imap\Client\ImapResponseParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapCapabilityNegotiator::class)]
class ImapCapabilityNegotiatorTest extends TestCase
{
    private function negotiator(string ...$lines): array
    {
        $socket = InMemoryImapSocket::fromParts(
            ...array_map(InMemoryImapSocket::line(...), $lines),
        );
        $interaction = new ImapInteraction(new ImapConnection($socket));

        return [new ImapCapabilityNegotiator($interaction), $socket];
    }

    public function testFetchParsesUntaggedCapabilityResponse(): void
    {
        [$negotiator] = $this->negotiator(
            '* CAPABILITY IMAP4rev2 UIDPLUS AUTH=PLAIN',
            'A1 OK CAPABILITY completed.',
        );

        $capability = $negotiator->fetch();

        self::assertTrue($capability->query('IMAP4REV2'));
        self::assertTrue($capability->query('UIDPLUS'));
        self::assertTrue($capability->query('AUTH', 'PLAIN'));
    }

    public function testFetchSendsTheCapabilityCommand(): void
    {
        [$negotiator, $socket] = $this->negotiator(
            '* CAPABILITY IMAP4rev1',
            'A1 OK CAPABILITY completed.',
        );

        $negotiator->fetch();

        self::assertSame(['A1 CAPABILITY'], $socket->written);
    }

    public function testFetchAlsoReadsCapabilityResponseCodeOnTaggedCompletion(): void
    {
        [$negotiator] = $this->negotiator(
            'A1 OK [CAPABILITY IMAP4rev1 IDLE] CAPABILITY completed.',
        );

        $capability = $negotiator->fetch();

        self::assertTrue($capability->query('IMAP4REV1'));
        self::assertTrue($capability->query('IDLE'));
    }

    public function testEnableRecordsAcknowledgedExtensions(): void
    {
        [$negotiator] = $this->negotiator(
            '* ENABLED IMAP4rev2',
            'A1 OK ENABLE completed.',
        );
        $capability = new ImapCapability();

        $enabled = $negotiator->enable($capability, ['IMAP4rev2']);

        self::assertSame(['IMAP4REV2'], $enabled);
        self::assertTrue($capability->isEnabled('IMAP4REV2'));
    }

    public function testEnableSendsRequestedExtensionsAsArguments(): void
    {
        [$negotiator, $socket] = $this->negotiator(
            '* ENABLED CONDSTORE QRESYNC',
            'A1 OK ENABLE completed.',
        );

        $negotiator->enable(new ImapCapability(), ['CONDSTORE', 'QRESYNC']);

        self::assertSame(['A1 ENABLE CONDSTORE QRESYNC'], $socket->written);
    }

    public function testEnableWithNoAcknowledgedExtensionsReturnsEmptyList(): void
    {
        [$negotiator] = $this->negotiator(
            '* ENABLED',
            'A1 OK ENABLE completed.',
        );

        $enabled = $negotiator->enable(new ImapCapability(), ['UNSUPPORTED']);

        self::assertSame([], $enabled);
    }

    public function testNegotiateRev2EnablesImap4Rev2WhenOffered(): void
    {
        [$negotiator] = $this->negotiator(
            '* ENABLED IMAP4rev2',
            'A1 OK ENABLE completed.',
        );
        $capability = new ImapCapability();
        $capability->add('IMAP4rev2');
        $capability->add('UTF8', 'ACCEPT');

        $result = $negotiator->negotiateRev2($capability);

        self::assertSame('IMAP4REV2', $result);
        self::assertTrue($capability->isEnabled('IMAP4REV2'));
    }

    public function testNegotiateRev2FallsBackToUtf8AcceptForRev1Server(): void
    {
        [$negotiator, $socket] = $this->negotiator(
            '* ENABLED UTF8=ACCEPT',
            'A1 OK ENABLE completed.',
        );
        $capability = new ImapCapability();
        $capability->add('IMAP4rev1');
        $capability->add('UTF8', 'ACCEPT');

        $result = $negotiator->negotiateRev2($capability);

        self::assertSame('UTF8=ACCEPT', $result);
        self::assertSame(['A1 ENABLE UTF8=ACCEPT'], $socket->written);
    }

    public function testNegotiateRev2DoesNothingWithoutRev2OrUtf8Accept(): void
    {
        [$negotiator, $socket] = $this->negotiator('');
        $capability = new ImapCapability();
        $capability->add('IMAP4rev1');

        $result = $negotiator->negotiateRev2($capability);

        self::assertNull($result);
        self::assertSame([], $socket->written);
    }

    public function testMergeFromResponseReturnsFalseWhenNoCapabilityData(): void
    {
        $response = ImapResponseParser::parse(['*', '1', 'EXISTS']);
        $capability = new ImapCapability();

        $merged = ImapCapabilityNegotiator::mergeFromResponse($response, $capability);

        self::assertFalse($merged);
    }

    public function testMergeFromResponseParsesGreetingCapabilityCode(): void
    {
        $response = ImapResponseParser::parse(['*', 'OK', '[CAPABILITY', 'IMAP4rev1]', 'Server', 'ready.']);
        $capability = new ImapCapability();

        $merged = ImapCapabilityNegotiator::mergeFromResponse($response, $capability);

        self::assertTrue($merged);
        self::assertTrue($capability->query('IMAP4REV1'));
    }
}
