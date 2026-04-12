<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Integration\Src;

use Generator;
use Horde\Imap\Client\ParsedAccess;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversNothing]
class ParsedAccessTest extends TestCase
{
    private function createImplementation(): ParsedAccess
    {
        return new class implements ParsedAccess {
            public function getEnvelope(): object
            {
                return new stdClass();
            }

            public function getHeaders(string $label): object
            {
                return new stdClass();
            }

            public function getHeadersIterator(string $label): Generator
            {
                yield new stdClass();
                yield new stdClass();
                yield new stdClass();
            }

            public function getStructure(): object
            {
                return new stdClass();
            }
        };
    }

    public function testGetEnvelopeReturnsObject(): void
    {
        $this->assertIsObject($this->createImplementation()->getEnvelope());
    }

    public function testGetHeadersReturnsObject(): void
    {
        $this->assertIsObject($this->createImplementation()->getHeaders('from'));
    }

    public function testGetHeadersIteratorReturnsGenerator(): void
    {
        $gen = $this->createImplementation()->getHeadersIterator('to');
        $this->assertInstanceOf(Generator::class, $gen);
    }

    public function testGetStructureReturnsObject(): void
    {
        $this->assertIsObject($this->createImplementation()->getStructure());
    }

    public function testGetHeadersIteratorYieldsMultipleHeaders(): void
    {
        $items = iterator_to_array($this->createImplementation()->getHeadersIterator('cc'));
        $this->assertCount(3, $items);
    }
}
