<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\Exception\AuthenticationException;
use Horde\Imap\Client\Exception\CapabilityNotSupportedException;
use Horde\Imap\Client\Exception\ConnectionException;
use Horde\Imap\Client\Exception\ImapProtocolException;
use Horde\Imap\Client\Exception\MailboxNotFoundException;
use Horde\Imap\Client\Exception\MailboxProtocolException;
use Horde\Imap\Client\Exception\Pop3ProtocolException;
use Horde\Imap\Client\Exception\ServerResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MailboxProtocolException::class)]
#[CoversClass(ConnectionException::class)]
#[CoversClass(AuthenticationException::class)]
#[CoversClass(ImapProtocolException::class)]
#[CoversClass(MailboxNotFoundException::class)]
#[CoversClass(CapabilityNotSupportedException::class)]
#[CoversClass(Pop3ProtocolException::class)]
#[CoversClass(ServerResponseException::class)]
class ExceptionHierarchyTest extends TestCase
{
    public function testBaseExtendsRuntimeException(): void
    {
        $e = new MailboxProtocolException('test');
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function testConnectionExtendsBase(): void
    {
        $e = new ConnectionException('conn');
        $this->assertInstanceOf(MailboxProtocolException::class, $e);
    }

    public function testAuthenticationExtendsBase(): void
    {
        $e = new AuthenticationException('auth');
        $this->assertInstanceOf(MailboxProtocolException::class, $e);
    }

    public function testImapProtocolExtendsBase(): void
    {
        $e = new ImapProtocolException('imap');
        $this->assertInstanceOf(MailboxProtocolException::class, $e);
    }

    public function testMailboxNotFoundExtendsImapProtocol(): void
    {
        $e = new MailboxNotFoundException('mbox');
        $this->assertInstanceOf(ImapProtocolException::class, $e);
        $this->assertInstanceOf(MailboxProtocolException::class, $e);
    }

    public function testCapabilityNotSupportedExtendsImapProtocol(): void
    {
        $e = new CapabilityNotSupportedException('cap');
        $this->assertInstanceOf(ImapProtocolException::class, $e);
    }

    public function testPop3ProtocolExtendsBase(): void
    {
        $e = new Pop3ProtocolException('pop3');
        $this->assertInstanceOf(MailboxProtocolException::class, $e);
    }

    public function testServerResponseCarriesData(): void
    {
        $e = new ServerResponseException(
            message: 'NO [TRYCREATE]',
            code: 0,
            previous: null,
            command: 'APPEND',
            status: 'NO',
            responseText: '[TRYCREATE] Mailbox does not exist',
        );

        $this->assertSame('APPEND', $e->command);
        $this->assertSame('NO', $e->status);
        $this->assertSame('[TRYCREATE] Mailbox does not exist', $e->responseText);
        $this->assertSame('NO [TRYCREATE]', $e->getMessage());
        $this->assertInstanceOf(MailboxProtocolException::class, $e);
    }

    public function testServerResponseDefaults(): void
    {
        $e = new ServerResponseException();
        $this->assertNull($e->command);
        $this->assertNull($e->status);
        $this->assertNull($e->responseText);
        $this->assertSame('', $e->getMessage());
    }

    /**
     * Catching MailboxProtocolException must catch all subtypes.
     */
    public function testBroadCatchCoversAll(): void
    {
        $exceptions = [
            new ConnectionException(),
            new AuthenticationException(),
            new ImapProtocolException(),
            new MailboxNotFoundException(),
            new CapabilityNotSupportedException(),
            new Pop3ProtocolException(),
            new ServerResponseException(),
        ];

        foreach ($exceptions as $e) {
            $caught = false;
            try {
                throw $e;
            } catch (MailboxProtocolException) {
                $caught = true;
            }
            $this->assertTrue($caught, get_class($e) . ' not caught by MailboxProtocolException');
        }
    }
}
