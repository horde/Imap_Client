<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 * @license http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

use Horde\Imap\Client\Exception\WireEncodingException;

/** A borrowed, seekable stream represented as a replayable IMAP literal. */
final class ImapWireStream implements ImapWireEncodable
{
    private const CHUNK_SIZE = 65536;

    private readonly int $start;
    private readonly int $size;
    private readonly bool $binary;

    /**
     * Capture bytes from the current position through EOF. The caller retains
     * ownership and must not modify or close the stream until sending finishes.
     *
     * @param resource $stream A readable, seekable PHP stream.
     */
    public function __construct(private readonly mixed $stream, private readonly bool $forceBinary = false)
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream'
            || !stream_get_meta_data($stream)['seekable']) {
            throw new WireEncodingException('APPEND requires a readable, seekable stream.');
        }

        $start = ftell($stream);
        if ($start === false) {
            throw new WireEncodingException('Cannot determine APPEND stream position.');
        }

        try {
            if (fseek($stream, 0, SEEK_END) !== 0 || ($end = ftell($stream)) === false || $end < $start) {
                throw new WireEncodingException('Cannot determine APPEND stream length.');
            }
            $this->start = $start;
            $this->size = $end - $start;
            if (fseek($stream, $start) !== 0) {
                throw new WireEncodingException('Cannot seek APPEND stream.');
            }

            $binary = false;
            $remaining = $this->size;
            while ($remaining > 0) {
                $chunk = $this->read(min(self::CHUNK_SIZE, $remaining));
                $remaining -= strlen($chunk);
                if (str_contains($chunk, "\0")) {
                    $binary = true;
                    break;
                }
            }
            $this->binary = $binary;
        } finally {
            fseek($stream, $start);
        }
    }

    public function isLiteral(): bool
    {
        return true;
    }

    public function isBinary(): bool
    {
        return $this->binary || $this->forceBinary;
    }

    public function containsNul(): bool
    {
        return $this->binary;
    }

    public function length(): int
    {
        return $this->size;
    }

    public function escape(): string
    {
        throw new WireEncodingException('A stream requires literal output.');
    }

    public function rawBytes(): string
    {
        throw new WireEncodingException('Use chunks() to read a stream literal without buffering it.');
    }

    /**
     * @return iterable<string> Exact literal bytes in bounded chunks.
     */
    public function chunks(): iterable
    {
        if (!is_resource($this->stream) || ($position = ftell($this->stream)) === false) {
            throw new WireEncodingException('APPEND stream was closed before sending.');
        }

        try {
            if (fseek($this->stream, $this->start) !== 0) {
                throw new WireEncodingException('Cannot rewind APPEND stream for sending.');
            }
            $remaining = $this->size;
            while ($remaining > 0) {
                $chunk = $this->read(min(self::CHUNK_SIZE, $remaining));
                $remaining -= strlen($chunk);
                yield $chunk;
            }
        } finally {
            if (is_resource($this->stream)) {
                fseek($this->stream, $position);
            }
        }
    }

    private function read(int $length): string
    {
        // Convert I/O warnings into a protocol-layer exception. A short or failed
        // source must never leave the connection usable for another command.
        $chunk = @fread($this->stream, $length);
        if ($chunk === false || $chunk === '') {
            throw new WireEncodingException('APPEND stream ended early or could not be read.');
        }
        return $chunk;
    }
}
