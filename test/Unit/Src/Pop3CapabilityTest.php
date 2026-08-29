<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\Capability;
use Horde\Imap\Client\Pop3Capability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pop3Capability::class)]
class Pop3CapabilityTest extends TestCase
{
    public function testImplementsCapabilityInterface(): void
    {
        $this->assertInstanceOf(Capability::class, new Pop3Capability());
    }

    public function testBehavesLikeTheGenericCapabilityData(): void
    {
        // POP3 (RFC 2449) has no implication rules or ENABLE state. The generic engine is exposed as the Pop3 type.
        $cap = new Pop3Capability();
        $cap->add('SASL', ['PLAIN', 'CRAM-MD5']);
        $cap->add('PIPELINING');
        $cap->add('UIDL');

        $this->assertTrue($cap->query('SASL', 'PLAIN'));
        $this->assertFalse($cap->query('SASL', 'LOGIN'));
        $this->assertTrue($cap->query('PIPELINING'));
        $this->assertSame(['PLAIN', 'CRAM-MD5'], $cap->getParams('SASL'));
    }
}
