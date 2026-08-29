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
use Horde\Imap\Client\ImapCapabilityParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapCapabilityParser::class)]
class ImapCapabilityParserTest extends TestCase
{
    public function testParsesBareCapabilityNames(): void
    {
        $capability = ImapCapabilityParser::parse(['IMAP4rev2', 'UIDPLUS', 'IDLE']);

        self::assertTrue($capability->query('IMAP4REV2'));
        self::assertTrue($capability->query('UIDPLUS'));
        self::assertTrue($capability->query('IDLE'));
        self::assertFalse($capability->query('QRESYNC'));
    }

    public function testParsesParameterizedCapabilities(): void
    {
        $capability = ImapCapabilityParser::parse(['AUTH=PLAIN', 'AUTH=SCRAM-SHA-256', 'UTF8=ACCEPT']);

        self::assertTrue($capability->query('AUTH', 'PLAIN'));
        self::assertTrue($capability->query('AUTH', 'SCRAM-SHA-256'));
        self::assertTrue($capability->query('UTF8', 'ACCEPT'));
        self::assertFalse($capability->query('AUTH', 'LOGIN'));
    }

    public function testIgnoresNestedListTokens(): void
    {
        $capability = ImapCapabilityParser::parse(['IMAP4rev1', ['nested', 'list']]);

        self::assertTrue($capability->query('IMAP4REV1'));
    }

    public function testMergesIntoAnExistingCapabilityInstance(): void
    {
        $capability = new ImapCapability();
        $capability->add('IDLE');

        ImapCapabilityParser::parse(['UIDPLUS'], $capability);

        self::assertTrue($capability->query('IDLE'));
        self::assertTrue($capability->query('UIDPLUS'));
    }

    public function testEmptyTokenListReturnsEmptyCapability(): void
    {
        $capability = ImapCapabilityParser::parse([]);

        self::assertFalse($capability->query('IMAP4REV1'));
    }
}
