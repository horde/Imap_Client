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
 * An IMAP mailbox name (RFC 3501 §9) encoded for the wire through a
 * {@see ImapMailboxNameCodec}.
 *
 * Set `$allowWildcards` for a LIST/LSUB mailbox-pattern argument, where
 * `%` and `*` are wildcards rather than literal text (RFC 3501 §6.3.8).
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapWireMailbox implements ImapWireEncodable
{
    private readonly ImapWireString $inner;

    public function __construct(
        private readonly string $utf8Name,
        ImapMailboxNameCodec $codec,
        bool $allowWildcards = false,
    ) {
        $this->inner = new ImapWireString($codec->encode($utf8Name), isAstring: true, allowWildcards: $allowWildcards);

        if ($this->inner->isBinary()) {
            throw new WireEncodingException(
                "Mailbox name '{$this->utf8Name}' contains a null byte and cannot be sent to an IMAP server.",
            );
        }
    }

    public function isLiteral(): bool
    {
        return $this->inner->isLiteral();
    }

    public function isBinary(): bool
    {
        // Mailbox names are never sent as an RFC 3516 binary literal;
        // the constructor already rejected the one input that would
        // otherwise require it.
        return false;
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
