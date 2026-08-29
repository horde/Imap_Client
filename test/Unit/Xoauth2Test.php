<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2013-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Horde_Imap_Client_Password_Xoauth2;

/**
 * Tests for the mailbox object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2013-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class Xoauth2Test extends TestCase
{
    public function testTokenGeneration()
    {
        // Example from https://developers.google.com/gmail/xoauth2_protocol
        $xoauth2 = new Horde_Imap_Client_Password_Xoauth2(
            'someuser@example.com',
            'vF9dft4qmTc2Nvb3RlckBhdHRhdmlzdGEuY29tCg=='
        );

        $this->assertEquals(
            'dXNlcj1zb21ldXNlckBleGFtcGxlLmNvbQFhdXRoPUJlYXJlciB2RjlkZnQ0cW1UYzJOdmIzUmxja0JoZEhSaGRtbHpkR0V1WTI5dENnPT0BAQ==',
            $xoauth2->getPassword()
        );
    }

}
