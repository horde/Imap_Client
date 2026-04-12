<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Error;
use Horde\Imap\Client\Exception\MailboxProtocolException;
use Horde\Imap\Client\Exception\ServerResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[CoversClass(ServerResponseException::class)]
class ServerResponseExceptionTest extends TestCase
{
    public function testPreviousExceptionChaining(): void
    {
        $prev = new RuntimeException('root cause');
        $e = new ServerResponseException('fail', 0, $prev);

        $this->assertSame($prev, $e->getPrevious());
        $this->assertSame('root cause', $e->getPrevious()->getMessage());
    }

    public function testErrorCode(): void
    {
        $e = new ServerResponseException('fail', 42);
        $this->assertSame(42, $e->getCode());
    }

    public function testAllPropertiesSimultaneously(): void
    {
        $e = new ServerResponseException(
            message: 'NO access denied',
            code: 99,
            previous: null,
            command: 'SELECT',
            status: 'NO',
            responseText: 'Access denied',
        );

        $this->assertSame('NO access denied', $e->getMessage());
        $this->assertSame(99, $e->getCode());
        $this->assertNull($e->getPrevious());
        $this->assertSame('SELECT', $e->command);
        $this->assertSame('NO', $e->status);
        $this->assertSame('Access denied', $e->responseText);
    }

    public function testCommandNullByDefault(): void
    {
        $e = new ServerResponseException('msg');
        $this->assertNull($e->command);
    }

    public function testStatusNullByDefault(): void
    {
        $e = new ServerResponseException('msg');
        $this->assertNull($e->status);
    }

    public function testResponseTextNullByDefault(): void
    {
        $e = new ServerResponseException('msg');
        $this->assertNull($e->responseText);
    }

    public function testReadonlyCommandThrowsError(): void
    {
        $e = new ServerResponseException('msg', 0, null, 'SELECT');
        $this->expectException(Error::class);
        $e->command = 'FETCH';
    }

    public function testReadonlyStatusThrowsError(): void
    {
        $e = new ServerResponseException('msg', 0, null, null, 'NO');
        $this->expectException(Error::class);
        $e->status = 'OK';
    }

    public function testReadonlyResponseTextThrowsError(): void
    {
        $e = new ServerResponseException('msg', 0, null, null, null, 'text');
        $this->expectException(Error::class);
        $e->responseText = 'other';
    }

    public function testEmptyStringProperties(): void
    {
        $e = new ServerResponseException('', 0, null, '', '', '');
        $this->assertSame('', $e->command);
        $this->assertSame('', $e->status);
        $this->assertSame('', $e->responseText);
        $this->assertSame('', $e->getMessage());
    }

    public function testIsThrowable(): void
    {
        $this->assertInstanceOf(Throwable::class, new ServerResponseException());
    }

    public function testCatchByMailboxProtocolException(): void
    {
        $caught = false;
        try {
            throw new ServerResponseException('test');
        } catch (MailboxProtocolException $e) {
            $caught = true;
            $this->assertSame('test', $e->getMessage());
        }
        $this->assertTrue($caught);
    }
}
