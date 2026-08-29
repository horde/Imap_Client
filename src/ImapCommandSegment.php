<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

/**
 * One chunk of a command line as it goes out on the wire.
 * Either plain text to write as-is or a literal's raw bytes to send after its `{n}`
 * announcement and its `+` continuation (unless non-synchronizing).
 *
 * {@see ImapCommand::segments()} is the only producer of these.
 * A connection iterates them in order and never needs to know why a given
 * value ended up as one or the other.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapCommandSegment
{
    private function __construct(
        public readonly bool $isLiteral,
        public readonly string $text,
        public readonly bool $isBinary,
        public readonly string $bytes,
    ) {}

    public static function text(string $text): self
    {
        return new self(false, $text, false, '');
    }

    public static function literal(bool $binary, string $bytes): self
    {
        return new self(true, '', $binary, $bytes);
    }

    public function length(): int
    {
        return strlen($this->bytes);
    }
}
