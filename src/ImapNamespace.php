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
 * One namespace entry from a NAMESPACE response (RFC 2342 §5).
 *
 * `$name` is the namespace prefix in UTF-8 (for example `""` for the
 * personal namespace, or `"Other Users/"` for the other-users namespace).
 * `$delimiter` is the hierarchy separator the server uses under this
 * prefix and is `null` for a flat namespace with no hierarchy (RFC 2342
 * allows a `NIL` delimiter). `$translation` carries the RFC 5255 §3.4
 * TRANSLATION extension value when the server sends one, otherwise an
 * empty string.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final readonly class ImapNamespace
{
    public function __construct(
        public string $name,
        public NamespaceType $type,
        public ?string $delimiter = null,
        public bool $hidden = false,
        public string $translation = '',
    ) {}

    /**
     * The namespace base: the name with any single trailing delimiter
     * removed (UTF-8). Mirrors the legacy `base` property.
     */
    public function base(): string
    {
        if ($this->delimiter === null || $this->delimiter === '' || $this->name === '') {
            return $this->name;
        }

        return str_ends_with($this->name, $this->delimiter)
            ? substr($this->name, 0, -strlen($this->delimiter))
            : $this->name;
    }

    /**
     * Strip this namespace's prefix from a mailbox name, if present.
     * Returns the name unchanged when it does not start with the prefix.
     */
    public function stripNamespace(string $mailbox): string
    {
        return ($this->name !== '' && str_starts_with($mailbox, $this->name))
            ? substr($mailbox, strlen($this->name))
            : $mailbox;
    }
}
