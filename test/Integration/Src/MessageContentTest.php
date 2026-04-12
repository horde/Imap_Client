<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Integration\Src;

use Horde\Imap\Client\MessageContent;
use Horde_Stream;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class MessageContentTest extends TestCase
{
    private function createImplementation(): MessageContent
    {
        return new class implements MessageContent {
            public function getFullMsg(): Horde_Stream
            {
                return new Horde_Stream();
            }

            public function getHeaderText(string|int $id = 0): Horde_Stream
            {
                $s = new Horde_Stream();
                $s->add("Subject: Test $id");
                return $s;
            }

            public function getBodyText(string|int $id = 0): Horde_Stream
            {
                return new Horde_Stream();
            }
        };
    }

    public function testGetFullMsgReturnsHordeStream(): void
    {
        $this->assertInstanceOf(Horde_Stream::class, $this->createImplementation()->getFullMsg());
    }

    public function testGetHeaderTextWithDefaultId(): void
    {
        $this->assertInstanceOf(Horde_Stream::class, $this->createImplementation()->getHeaderText());
    }

    public function testGetHeaderTextWithIntId(): void
    {
        $this->assertInstanceOf(Horde_Stream::class, $this->createImplementation()->getHeaderText(1));
    }

    public function testGetHeaderTextWithStringId(): void
    {
        $this->assertInstanceOf(Horde_Stream::class, $this->createImplementation()->getHeaderText('1.2'));
    }

    public function testGetBodyTextReturnsHordeStream(): void
    {
        $this->assertInstanceOf(Horde_Stream::class, $this->createImplementation()->getBodyText());
    }

    public function testStreamContainsContent(): void
    {
        $stub = $this->createImplementation();
        $stream = $stub->getHeaderText(5);
        $this->assertStringContainsString('Subject: Test 5', (string) $stream);
    }
}
