<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Url;

use PHPUnit\Framework\Attributes\CoversNothing;
use Horde_Imap_Client_Mailbox;

/**
 * Tests for IMAP URL parsing.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class ImapTest extends TestBase
{
    protected $classname = 'Horde_Imap_Client_Url_Imap';
    protected $protocol = 'imap';

    public static function urlProvider()
    {
        return [
            [
                'imap://test.example.com/',
                null,
                [
                    'host' => 'test.example.com',
                    'port' => 143,
                    'mailbox' => null,
                ],
            ],
            [
                'imap://test.example.com:143/',
                'imap://test.example.com/',
                [
                    'host' => 'test.example.com',
                    'port' => 143,
                    'mailbox' => null,
                ],
            ],
            [
                'imap://testuser@test.example.com/',
                null,
                [
                    'host' => 'test.example.com',
                    'port' => 143,
                    'username' => 'testuser',
                    'mailbox' => null,
                ],
            ],
            [
                'imap://testuser@test.example.com:14300/',
                null,
                [
                    'host' => 'test.example.com',
                    'port' => 14300,
                    'username' => 'testuser',
                    'mailbox' => null,
                ],
            ],
            [
                'imap://testuser;AUTH=*@test.example.com:143/',
                'imap://testuser@test.example.com/',
                [
                    'auth' => null,
                    'host' => 'test.example.com',
                    'port' => 143,
                    'username' => 'testuser',
                    'mailbox' => null,
                ],
            ],
            [
                'imap://testuser;AUTH=PLAIN@test.example.com:14300/',
                null,
                [
                    'host' => 'test.example.com',
                    'port' => 14300,
                    'username' => 'testuser',
                    'auth' => 'PLAIN',
                    'mailbox' => null,
                ],
            ],
            [
                'imap://test.example.com:14300/Quarant%26AOQ-ne;UIDVALIDITY=1240054819/;UID=39193/;SECTION=HEADER/;PARTIAL=0.1024',
                null,
                [
                    'host' => 'test.example.com',
                    'partial' => '0.1024',
                    'port' => 14300,
                    'section' => 'HEADER',
                    'uid' => 39193,
                    'uidvalidity' => 1240054819,
                    'mailbox' => new Horde_Imap_Client_Mailbox('Quarant&AOQ-ne', true),
                ],
            ],
            [
                'imap://test.example.com:14300/INBOX;UIDVALIDITY=123/;UID=456?FLAGGED%20SINCE%201-Feb-1994%20NOT%20FROM%20%22Smith%22',
                'imap://test.example.com:14300/INBOX;UIDVALIDITY=123?FLAGGED%20SINCE%201-Feb-1994%20NOT%20FROM%20%22Smith%22',
                [
                    'host' => 'test.example.com',
                    'port' => 14300,
                    'uidvalidity' => 123,
                    'mailbox' => new Horde_Imap_Client_Mailbox('INBOX', true),
                    // Ignore extra data after UIDVALIDITY
                    'uid' => '',
                    // Search example from RFC 3501 [6.4.4]
                    'search' => 'FLAGGED SINCE 1-Feb-1994 NOT FROM "Smith"',
                ],
            ],
        ];
    }

    public static function serializeProvider()
    {
        return [
            ['imap://test.example.com/'],
        ];
    }
}
