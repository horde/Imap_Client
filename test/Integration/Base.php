<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Integration;

use Horde_Imap_Client_Exception;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Base class for live server testing.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class Base extends TestCase
{
    public static $live;

    public static function tearDownAfterClass(): void
    {
        self::$live = null;
    }

    protected function onNotSuccessfulTest(Throwable $t): never
    {
        if ($t instanceof Horde_Imap_Client_Exception) {
            $t = new Horde_Imap_Client_Exception(
                $t->getMessage() . ' [' . self::$live->url . ']',
                $t->getCode(),
                $t
            );
        }
        parent::onNotSuccessfulTest($t);
    }
}
