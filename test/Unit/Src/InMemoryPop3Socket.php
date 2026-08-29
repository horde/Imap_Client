<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Socket\Client\ChannelBinding\ChannelBindingType;
use Horde\Socket\Client\ClientInterface;
use Horde\Socket\Client\StreamStatus;

/**
 * An in-memory {@see ClientInterface} fed with a fixed queue of lines to
 * read, recording every line written to it.
 *
 * Lets {@see \Horde\Imap\Client\Pop3Connection} and
 * {@see \Horde\Imap\Client\Pop3AuthChannel} be tested against a scripted
 * POP3 server conversation without a live socket.
 */
final class InMemoryPop3Socket implements ClientInterface
{
    /** @var list<string> */
    private array $queue;

    /** @var list<string> */
    public array $written = [];

    /**
     * @param list<string> $lines Lines to hand back from `gets()`, in
     *                            order, each without a trailing CRLF (it
     *                            is appended automatically).
     */
    public function __construct(array $lines = [])
    {
        $this->queue = $lines;
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function isSecure(): bool
    {
        return false;
    }

    public function supportsChannelBinding(ChannelBindingType $type): bool
    {
        return false;
    }

    public function channelBindingData(ChannelBindingType $type): string
    {
        return '';
    }

    public function startTls(): bool
    {
        return true;
    }

    public function close(): void
    {
    }

    public function getStatus(): StreamStatus
    {
        return new StreamStatus(false, false, $this->queue === [], 0);
    }

    public function gets(int $size): string
    {
        if ($this->queue === []) {
            throw new \RuntimeException('InMemoryPop3Socket: read past the end of the scripted queue.');
        }

        return array_shift($this->queue) . "\r\n";
    }

    public function read(int $size): string
    {
        return $this->gets($size);
    }

    public function write(string $data): void
    {
        $this->written[] = rtrim($data, "\r\n");
    }
}
