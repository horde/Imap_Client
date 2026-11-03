<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 * @license http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

use Horde\Imap\Client\Exception\WireEncodingException;

/** A borrowed stream: replayable when seekable, one-pass with explicit length. */
final class ImapWireStream implements ImapWireEncodable
{
    private const CHUNK_SIZE = 65536;

    private readonly ?int $start;
    private bool $consumed = false;
    private readonly int $size;
    private readonly bool $binary;

    /**
     * Capture bytes from the current position through EOF. The caller retains
     * ownership and must not modify or close the stream until sending finishes.
     *
     * With $length, send exactly that many bytes from the current position,
     * without scanning, seeking or restoring it. Binary mode is mandatory in
     * this case and the literal can be consumed only once. Extra source bytes
     * remain unread; a short source fails and the connection must be closed.
     *
     * @param resource $stream A readable PHP stream; seekable without $length.
     * @param ?int $length Explicit length for opt-in forward-only consumption.
     */
    public function __construct(private readonly mixed $stream, private readonly bool $forceBinary = false, ?int $length = null)
    {
        if ($length !== null) {
            if ($length < 0 || !$forceBinary) {
                throw new WireEncodingException('An explicit APPEND length must be non-negative and requires binary mode.');
            }
            if (!is_resource($stream) || get_resource_type($stream) !== 'stream'
                || !preg_match('/[r+]/', stream_get_meta_data($stream)['mode'])) {
                throw new WireEncodingException('APPEND requires a readable stream.');
            }
            $this->start = null;
            $this->size = $length;
            // No scan is possible on a one-pass source. All bytes, including
            // NUL, are permitted because the caller explicitly chose literal8.
            $this->binary = false;
            return;
        }
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
        if ($this->start === null) {
            if ($this->consumed) {
                throw new WireEncodingException('A forward-only APPEND stream cannot be replayed.');
            }
            $this->consumed = true;
            if (!is_resource($this->stream)) {
                throw new WireEncodingException('APPEND stream was closed before sending.');
            }
            $remaining = $this->size;
            while ($remaining > 0) {
                $chunk = $this->read(min(self::CHUNK_SIZE, $remaining));
                $remaining -= strlen($chunk);
                yield $chunk;
            }
            return;
        }

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

    /** Safe to retry only if replayable or no one-pass bytes were requested. */
    public function canReplay(): bool
    {
        return $this->start !== null || !$this->consumed;
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
