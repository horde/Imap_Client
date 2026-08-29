<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Socket\Client\ChannelBinding\ChannelBindingType;
use Horde\Socket\Client\ClientInterface;
use Horde\Socket\Client\StreamStatus;

/**
 * An in-memory {@see ClientInterface} backed by one continuous byte
 * buffer, the way a real socket behaves: `gets()` reads up to and
 * including the next `\n`, `read()` reads an exact byte count
 * regardless of where line boundaries fall. This is what
 * {@see \Horde\Imap\Client\ImapTokenizer} needs to be tested
 * faithfully, since a literal's raw bytes can contain anything,
 * including embedded CRLFs.
 */
final class InMemoryImapSocket implements ClientInterface
{
    private int $pos = 0;

    /** @var list<string> */
    public array $written = [];

    public function __construct(
        private readonly string $buffer,
    ) {}

    /**
     * Build the byte script from response lines (each without its own
     * CRLF, which is appended automatically) and raw literal payloads
     * (passed as-is, with no CRLF appended).
     */
    public static function fromParts(string ...$parts): self
    {
        return new self(implode('', $parts));
    }

    public static function line(string $line): string
    {
        return $line . "\r\n";
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
        return new StreamStatus(false, false, $this->pos >= strlen($this->buffer), 0);
    }

    public function gets(int $size): string
    {
        if ($this->pos >= strlen($this->buffer)) {
            // Real end of stream: fgets() returns nothing more to read.
            return '';
        }

        $newline = strpos($this->buffer, "\n", $this->pos);

        if ($newline === false) {
            $chunk = substr($this->buffer, $this->pos);
            $this->pos = strlen($this->buffer);

            return $chunk;
        }

        $chunk = substr($this->buffer, $this->pos, $newline - $this->pos + 1);
        $this->pos = $newline + 1;

        return $chunk;
    }

    public function read(int $size): string
    {
        $chunk = substr($this->buffer, $this->pos, $size);
        $this->pos += strlen($chunk);

        return $chunk;
    }

    public function write(string $data): void
    {
        $this->written[] = rtrim($data, "\r\n");
    }
}
