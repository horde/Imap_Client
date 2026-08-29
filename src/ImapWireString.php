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
 * An IMAP string (RFC 3501 §4.3): Either a quoted string or a literal,
 * chosen automatically from the content.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class ImapWireString implements ImapWireEncodable
{
    private readonly ImapStringClassification $classification;

    /**
     * @param string $data           The raw (unescaped) value.
     * @param bool   $isAstring      An astring (RFC 3501 §9) must be
     *                               quoted even when empty, since a bare
     *                               empty atom is ambiguous. A plain
     *                               string does not have this rule.
     * @param bool   $allowWildcards If true, `%` and `*` are treated as
     *                               ordinary characters rather than
     *                               atom-specials. Set this for a
     *                               LIST/LSUB mailbox pattern.
     */
    public function __construct(
        private readonly string $data,
        private readonly bool $isAstring = false,
        bool $allowWildcards = false,
    ) {
        $this->classification = ImapStringClassifier::classify($data, $allowWildcards);
    }

    public function isLiteral(): bool
    {
        return $this->classification->literal;
    }

    public function isBinary(): bool
    {
        return $this->classification->binary;
    }

    public function length(): int
    {
        return strlen($this->data);
    }

    public function escape(): string
    {
        if ($this->isLiteral()) {
            throw new WireEncodingException(
                'This string contains a byte that requires literal output.',
            );
        }

        if ($this->classification->quoted || ($this->isAstring && $this->data === '')) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $this->data) . '"';
        }

        return $this->data;
    }

    public function rawBytes(): string
    {
        return $this->data;
    }
}
