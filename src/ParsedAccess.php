<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2011-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

use Generator;

/**
 * Layer 3: OO representation on top of the stream layer.
 *
 * Return types use object until Envelope, Headers, and BodyStructure value
 * objects are implemented. Concrete classes narrow via covariant return types.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @copyright 2011-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface ParsedAccess
{
    /**
     * @return object Envelope value object
     */
    public function getEnvelope(): object;

    /**
     * @return object Headers value object
     */
    public function getHeaders(string $label): object;

    /**
     * Yields headers one at a time from the stream.
     *
     * @return Generator
     */
    public function getHeadersIterator(string $label): Generator;

    /**
     * @return object BodyStructure value object
     */
    public function getStructure(): object;
}
