<?php

declare(strict_types=1);

/**
 * Copyright 2008-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

/**
 * Threading algorithm for thread().
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2008-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
enum ThreadAlgorithm: int
{
    case OrderedSubject = 1;
    case References = 2;
    case Refs = 3;
}
