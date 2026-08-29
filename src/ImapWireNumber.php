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

use Horde\Imap\Client\Exception\WireEncodingException;

/**
 * An IMAP number (RFC 3501 §4.2): An unsigned integer used for message
 * sequence numbers, UIDs, sizes and similar values.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapWireNumber implements ImapWireEncodable
{
    public function __construct(
        private readonly int $value,
    ) {
        if ($value < 0) {
            throw new WireEncodingException(
                "IMAP number cannot be negative: {$value}.",
            );
        }
    }

    public function isLiteral(): bool
    {
        return false;
    }

    public function isBinary(): bool
    {
        return false;
    }

    public function length(): int
    {
        return strlen((string) $this->value);
    }

    public function escape(): string
    {
        return (string) $this->value;
    }

    public function rawBytes(): string
    {
        return (string) $this->value;
    }
}
