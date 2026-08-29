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
 * An IMAP atom (RFC 3501 §4.1): A bare, unquoted token such as a command
 * name or a flag.
 *
 * Flags are the reason `escape()` does not reject atom-special
 * characters outright: A system flag (`\Seen`, `\Deleted`, ...) or a
 * flag-extension (`"\" atom`, RFC 3501 §9) legitimately starts with a
 * backslash, even though a backslash is technically an atom-special.
 * Call `validate()` explicitly wherever a value must be a strict,
 * plain atom (for example, a user-supplied keyword).
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapWireAtom implements ImapWireEncodable
{
    private const INVALID_ATOM_CHARACTERS = ['(', ')', '{', ' ', '%', '*', '"', '\\', ']'];

    public function __construct(
        private readonly string $data,
    ) {}

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
        return strlen($this->data);
    }

    public function escape(): string
    {
        // An empty atom is ambiguous on the wire so send it quoted.
        return $this->data === '' ? '""' : $this->data;
    }

    public function rawBytes(): string
    {
        return $this->data;
    }

    /**
     * Confirm this value contains nothing but strict, plain-atom
     * characters (no atom-specials at all, not even a leading
     * backslash). Not called automatically by {@see escape()}, since
     * flags are also represented as `ImapWireAtom` and are not strict atoms.
     *
     * @throws WireEncodingException If an atom-special character is
     *                                present.
     */
    public function validate(): void
    {
        if ($this->data !== $this->withoutInvalidAtomCharacters()) {
            throw new WireEncodingException(
                "Illegal character in IMAP atom: '{$this->data}'.",
            );
        }
    }

    private function withoutInvalidAtomCharacters(): string
    {
        $printable = preg_replace('/[^\x20-\x7e]/', '', $this->data);

        return str_replace(self::INVALID_ATOM_CHARACTERS, '', $printable);
    }
}
