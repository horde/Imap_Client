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
 * The IMAP `NIL` atom (RFC 3501 §4.5): the absent value of an nstring.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapWireNil implements ImapWireEncodable
{
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
        return 0;
    }

    public function escape(): string
    {
        return 'NIL';
    }

    public function rawBytes(): string
    {
        return '';
    }
}
