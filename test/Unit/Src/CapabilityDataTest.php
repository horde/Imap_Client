<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\Capability;
use Horde\Imap\Client\CapabilityData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CapabilityData::class)]
class CapabilityDataTest extends TestCase
{
    private function newCapability(): Capability
    {
        return new class implements Capability {
            use CapabilityData;
        };
    }

    public function testAddWithoutParamsThenQuery(): void
    {
        $cap = $this->newCapability();
        $cap->add('IMAP4rev1');

        $this->assertTrue($cap->query('IMAP4rev1'));
        $this->assertFalse($cap->query('CONDSTORE'));
    }

    public function testQueryAndAddAreCaseInsensitive(): void
    {
        $cap = $this->newCapability();
        $cap->add('imap4rev1');

        $this->assertTrue($cap->query('IMAP4REV1'));
        $this->assertTrue($cap->query('ImAp4reV1'));
    }

    public function testAddWithSingleParameter(): void
    {
        $cap = $this->newCapability();
        $cap->add('AUTH', 'PLAIN');

        $this->assertTrue($cap->query('AUTH'));
        $this->assertTrue($cap->query('AUTH', 'PLAIN'));
        $this->assertFalse($cap->query('AUTH', 'LOGIN'));
        $this->assertSame(['PLAIN'], $cap->getParams('AUTH'));
    }

    public function testAddWithParameterListInOneCall(): void
    {
        $cap = $this->newCapability();
        $cap->add('AUTH', ['PLAIN', 'LOGIN']);

        $this->assertSame(['PLAIN', 'LOGIN'], $cap->getParams('AUTH'));
    }

    public function testRepeatedAddMergesParametersRatherThanReplacing(): void
    {
        // A real CAPABILITY response lists AUTH=PLAIN and AUTH=LOGIN as
        // separate tokens under the same capability name.
        $cap = $this->newCapability();
        $cap->add('AUTH', 'PLAIN');
        $cap->add('AUTH', 'LOGIN');

        $this->assertSame(['PLAIN', 'LOGIN'], $cap->getParams('AUTH'));
    }

    public function testAddParametersAreUppercased(): void
    {
        $cap = $this->newCapability();
        $cap->add('AUTH', 'plain');

        $this->assertTrue($cap->query('AUTH', 'PLAIN'));
        $this->assertSame(['PLAIN'], $cap->getParams('AUTH'));
    }

    public function testAddWithoutParamsIsIdempotentAndDoesNotClobberExistingParams(): void
    {
        $cap = $this->newCapability();
        $cap->add('AUTH', 'PLAIN');
        $cap->add('AUTH');

        $this->assertSame(['PLAIN'], $cap->getParams('AUTH'));
    }

    public function testGetParamsIsEmptyForUnknownOrParameterlessCapability(): void
    {
        $cap = $this->newCapability();
        $cap->add('IMAP4rev1');

        $this->assertSame([], $cap->getParams('IMAP4rev1'));
        $this->assertSame([], $cap->getParams('UNKNOWN'));
    }

    public function testRemoveWholeCapability(): void
    {
        $cap = $this->newCapability();
        $cap->add('IMAP4rev1');
        $cap->remove('IMAP4rev1');

        $this->assertFalse($cap->query('IMAP4rev1'));
    }

    public function testRemoveNonExistentCapabilityIsANoop(): void
    {
        $cap = $this->newCapability();
        $cap->remove('IMAP4rev1');

        $this->assertFalse($cap->query('IMAP4rev1'));
    }

    public function testRemoveOneParameterLeavesOthersIntact(): void
    {
        $cap = $this->newCapability();
        $cap->add('AUTH', ['PLAIN', 'LOGIN']);
        $cap->remove('AUTH', 'PLAIN');

        $this->assertTrue($cap->query('AUTH'));
        $this->assertFalse($cap->query('AUTH', 'PLAIN'));
        $this->assertTrue($cap->query('AUTH', 'LOGIN'));
        $this->assertSame(['LOGIN'], $cap->getParams('AUTH'));
    }

    public function testRemoveLastParameterDropsTheWholeCapability(): void
    {
        $cap = $this->newCapability();
        $cap->add('AUTH', 'PLAIN');
        $cap->remove('AUTH', 'PLAIN');

        $this->assertFalse($cap->query('AUTH'));
    }

    public function testToArrayReturnsRawUppercasedData(): void
    {
        $cap = $this->newCapability();
        $cap->add('imap4rev1');
        $cap->add('AUTH', 'plain');

        $this->assertSame(
            ['IMAP4REV1' => true, 'AUTH' => ['PLAIN']],
            $cap->toArray(),
        );
    }

    public function testSerializeRoundTrip(): void
    {
        $cap = $this->newCapability();
        $cap->add('IMAP4rev1');
        $cap->add('AUTH', ['PLAIN', 'LOGIN']);

        $restored = $this->newCapability();
        $restored->__unserialize($cap->__serialize());

        $this->assertSame($cap->toArray(), $restored->toArray());
        $this->assertTrue($restored->query('AUTH', 'LOGIN'));
    }
}
