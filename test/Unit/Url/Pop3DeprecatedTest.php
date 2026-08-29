<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Url;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tests for the deprecated Horde_Imap_Client_Url POP3 URL parsing.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class Pop3DeprecatedTest extends TestBase
{
    protected $classname = 'Horde_Imap_Client_Url';
    protected $protocol = 'pop';

    public static function urlProvider()
    {
        return [
            [
                'pop://test.example.com/',
                null,
                [
                    'hostspec' => 'test.example.com',
                    'port' => 110,
                    'relative' => false,
                    'protocol' => 'pop',
                ],
            ],
            [
                'pop://test.example.com:110/',
                'pop://test.example.com/',
                [
                    'hostspec' => 'test.example.com',
                    'port' => 110,
                    'relative' => false,
                    'protocol' => 'pop',
                ],
            ],
            [
                'pop://testuser@test.example.com/',
                null,
                [
                    'hostspec' => 'test.example.com',
                    'port' => 110,
                    'relative' => false,
                    'username' => 'testuser',
                    'protocol' => 'pop',
                ],
            ],
            // This is the default port for IMAP, not POP3
            [
                'pop://testuser@test.example.com:143/',
                null,
                [
                    'hostspec' => 'test.example.com',
                    'port' => 143,
                    'relative' => false,
                    'username' => 'testuser',
                    'protocol' => 'pop',
                ],
            ],
            [
                'pop://testuser;AUTH=*@test.example.com:110/',
                'pop://testuser@test.example.com/',
                [
                    'hostspec' => 'test.example.com',
                    'port' => 110,
                    'username' => 'testuser',
                    'relative' => false,
                    'protocol' => 'pop',
                ],
            ],
            [
                'pop://testuser;AUTH=PLAIN@test.example.com/',
                null,
                [
                    'hostspec' => 'test.example.com',
                    'port' => 110,
                    'username' => 'testuser',
                    'relative' => false,
                    'auth' => 'PLAIN',
                    'protocol' => 'pop',
                ],
            ],
            // Ignore everything after the port.
            [
                'pop://test.example.com:110/INBOX.Quarant%26AOQ-ne;UIDVALIDITY=1240054819/;UID=39193/;SECTION=HEADER',
                'pop://test.example.com/',
                [
                    'hostspec' => 'test.example.com',
                    'port' => 110,
                    'relative' => false,
                    'section' => '',
                    'uid' => '',
                    'uidvalidity' => '',
                    'mailbox' => '',
                ],
            ],
        ];
    }

    public static function serializeProvider()
    {
        return [
            ['pop://test.example.com/'],
        ];
    }
}
