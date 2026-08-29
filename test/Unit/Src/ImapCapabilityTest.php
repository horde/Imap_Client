<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\Capability;
use Horde\Imap\Client\ImapCapability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapCapability::class)]
class ImapCapabilityTest extends TestCase
{
    public function testImplementsCapabilityInterface(): void
    {
        $this->assertInstanceOf(Capability::class, new ImapCapability());
    }

    public function testPlainQueryBehavesLikeTheGenericSet(): void
    {
        $cap = new ImapCapability();
        $cap->add('IMAP4rev1');
        $cap->add('AUTH', ['PLAIN', 'LOGIN']);

        $this->assertTrue($cap->query('IMAP4rev1'));
        $this->assertTrue($cap->query('AUTH', 'PLAIN'));
        $this->assertFalse($cap->query('AUTH', 'CRAM-MD5'));
    }

    public function testQresyncImpliesCondstore(): void
    {
        $cap = new ImapCapability();
        $cap->add('QRESYNC');

        // CONDSTORE is never added as raw data / it is only implied via query().
        $this->assertArrayNotHasKey('CONDSTORE', $cap->toArray());
        $this->assertTrue($cap->query('CONDSTORE'));
    }

    public function testQresyncImpliesEnableCapabilityButOnlyWithoutAParameter(): void
    {
        $cap = new ImapCapability();
        $cap->add('QRESYNC');

        $this->assertTrue($cap->query('ENABLE'));
    }

    public function testCondstoreAloneDoesNotImplyQresync(): void
    {
        $cap = new ImapCapability();
        $cap->add('CONDSTORE');

        $this->assertTrue($cap->query('CONDSTORE'));
        $this->assertFalse($cap->query('QRESYNC'));
        $this->assertFalse($cap->query('ENABLE'));
    }

    public function testUtf8OnlyImpliesUtf8Accept(): void
    {
        $cap = new ImapCapability();
        $cap->add('UTF8', 'ONLY');

        $this->assertTrue($cap->query('UTF8', 'ONLY'));
        $this->assertTrue($cap->query('UTF8', 'ACCEPT'));
    }

    public function testUtf8AcceptAloneDoesNotImplyOnly(): void
    {
        $cap = new ImapCapability();
        $cap->add('UTF8', 'ACCEPT');

        $this->assertTrue($cap->query('UTF8', 'ACCEPT'));
        $this->assertFalse($cap->query('UTF8', 'ONLY'));
    }

    public function testEnableRecordsAndQueriesEnabledState(): void
    {
        $cap = new ImapCapability();
        $cap->add('CONDSTORE');

        $this->assertFalse($cap->isEnabled('CONDSTORE'));

        $cap->enable('CONDSTORE');

        $this->assertTrue($cap->isEnabled('CONDSTORE'));
        $this->assertSame(['CONDSTORE'], $cap->enabled());
    }

    public function testEnablingQresyncAlsoEnablesCondstore(): void
    {
        $cap = new ImapCapability();
        $cap->add('QRESYNC');

        $cap->enable('QRESYNC');

        $this->assertTrue($cap->isEnabled('QRESYNC'));
        $this->assertTrue($cap->isEnabled('CONDSTORE'));
    }

    public function testDisablingRemovesFromEnabledState(): void
    {
        $cap = new ImapCapability();
        $cap->add('CONDSTORE');
        $cap->enable('CONDSTORE');
        $cap->enable('CONDSTORE', false);

        $this->assertFalse($cap->isEnabled('CONDSTORE'));
        $this->assertSame([], $cap->enabled());
    }

    public function testEnableIsIdempotent(): void
    {
        $cap = new ImapCapability();
        $cap->add('CONDSTORE');
        $cap->enable('CONDSTORE');
        $cap->enable('CONDSTORE');

        $this->assertSame(['CONDSTORE'], $cap->enabled());
    }

    public function testCmdLengthDefaultsToTwoThousand(): void
    {
        $cap = new ImapCapability();
        $cap->add('IMAP4rev1');

        $this->assertSame(2000, $cap->cmdLength());
    }

    public function testCmdLengthIsEightThousandWithCondstore(): void
    {
        $cap = new ImapCapability();
        $cap->add('CONDSTORE');

        $this->assertSame(8000, $cap->cmdLength());
    }

    public function testCmdLengthIsEightThousandWithQresync(): void
    {
        $cap = new ImapCapability();
        $cap->add('QRESYNC');

        $this->assertSame(8000, $cap->cmdLength());
    }
}
