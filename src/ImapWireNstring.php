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
 * An IMAP nstring (RFC 3501 §4.5): Either `NIL` or a string.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapWireNstring implements ImapWireEncodable
{
    private readonly ImapWireEncodable $inner;

    public function __construct(?string $data, bool $isAstring = false)
    {
        $this->inner = $data === null ? new ImapWireNil() : new ImapWireString($data, $isAstring);
    }

    public function isLiteral(): bool
    {
        return $this->inner->isLiteral();
    }

    public function isBinary(): bool
    {
        return $this->inner->isBinary();
    }

    public function length(): int
    {
        return $this->inner->length();
    }

    public function escape(): string
    {
        return $this->inner->escape();
    }

    public function rawBytes(): string
    {
        return $this->inner->rawBytes();
    }
}
