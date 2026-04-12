<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Integration\Src;

use Generator;
use Horde\Imap\Client\PartAccess;
use Horde_Stream;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversNothing]
class PartAccessTest extends TestCase
{
    private function createImplementation(): PartAccess
    {
        return new class implements PartAccess {
            public function getBodyPart(string $id): Horde_Stream
            {
                return new Horde_Stream();
            }

            public function getMimeHeader(string $id): Horde_Stream
            {
                return new Horde_Stream();
            }

            public function getParts(): Generator
            {
                yield '1' => new stdClass();
                yield '1.1' => new stdClass();
            }
        };
    }

    public function testGetBodyPartReturnsHordeStream(): void
    {
        $this->assertInstanceOf(Horde_Stream::class, $this->createImplementation()->getBodyPart('1.1'));
    }

    public function testGetMimeHeaderReturnsHordeStream(): void
    {
        $this->assertInstanceOf(Horde_Stream::class, $this->createImplementation()->getMimeHeader('1'));
    }

    public function testGetPartsReturnsGenerator(): void
    {
        $gen = $this->createImplementation()->getParts();
        $this->assertInstanceOf(Generator::class, $gen);
        $this->assertCount(2, iterator_to_array($gen));
    }

    public function testGetPartsEmptyGenerator(): void
    {
        $stub = new class implements PartAccess {
            public function getBodyPart(string $id): Horde_Stream
            {
                return new Horde_Stream();
            }

            public function getMimeHeader(string $id): Horde_Stream
            {
                return new Horde_Stream();
            }

            public function getParts(): Generator
            {
                yield from [];
            }
        };

        $this->assertSame([], iterator_to_array($stub->getParts()));
    }
}
