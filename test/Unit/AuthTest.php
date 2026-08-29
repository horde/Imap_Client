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

namespace Horde\Imap\Client\Test\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Horde\Imap\Client\Test\Stub\DigestMD5;
use Horde\Imap\Client\Test\Stub\Scram;

/**
 * Tests for the Imap Client ACL Auth features.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class AuthTest extends TestCase
{
    #[DataProvider('digestMd5Provider')]
    public function testDigestMd5($c)
    {
        $ob = new DigestMD5(
            $c['user'],
            $c['pass'],
            $c['challenge'],
            $c['hostname'],
            $c['service'],
            $c['cnonce']
        );

        $this->assertEquals(
            $c['expected'],
            $ob->response
        );
    }

    public static function digestMd5Provider()
    {
        return [
            [
                // IMAP example from RFC 2831 [4]
                [
                    'user' => 'chris',
                    'pass' => 'secret',
                    'challenge' => base64_decode('cmVhbG09ImVsd29vZC5pbm5vc29mdC5jb20iLG5vbmNlPSJPQTZNRzl0RVFHbTJoaCIscW9wPSJhdXRoIixhbGdvcml0aG09bWQ1LXNlc3MsY2hhcnNldD11dGYtOA=='),
                    'hostname' => 'elwood.innosoft.com',
                    'service' => 'imap',
                    'cnonce' => 'OA6MHXh6VqTrRk',
                    'expected' => 'd388dad90d4bbd760a152321f2143af7',
                ],
            ],
        ];
    }

    #[DataProvider('scramProvider')]
    public function testScram($c)
    {
        $ob = new Scram(
            $c['user'],
            $c['pass'],
            $c['hash']
        );
        $ob->setNonce($c['nonce']);

        $this->assertEquals(
            $c['c1'],
            $ob->getClientFirstMessage()
        );

        $this->assertTrue($ob->parseServerFirstMessage($c['s1']));

        $this->assertEquals(
            $c['c2'],
            $ob->getClientFinalMessage()
        );

        $this->assertTrue($ob->parseServerFinalMessage($c['s2']));
    }

    public static function scramProvider()
    {
        return [
            [
                // Example from RFC 5802 [5]
                [
                    'user' => 'user',
                    'pass' => 'pencil',
                    'hash' => 'SHA1',
                    'nonce' => 'fyko+d2lbbFgONRv9qkxdawL',
                    'c1' => 'n,,n=user,r=fyko+d2lbbFgONRv9qkxdawL',
                    's1' => 'r=fyko+d2lbbFgONRv9qkxdawL3rfcNHYJY1ZVvWVs7j,s=QSXCR+Q6sek8bf92,i=4096',
                    'c2' => 'c=biws,r=fyko+d2lbbFgONRv9qkxdawL3rfcNHYJY1ZVvWVs7j,p=v0X8v3Bz2T0CJGbJQyF0X+HI4Ts=',
                    's2' => 'v=rmF9pqV8S7suAoZWja4dJRkFsKQ=',
                ],
            ],
        ];
    }

}
