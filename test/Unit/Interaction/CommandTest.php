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

namespace Horde\Imap\Client\Test\Unit\Interaction;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Horde_Imap_Client_Interaction_Command;
use Horde_Imap_Client_Interaction_Command_Continuation;
use Horde_Imap_Client_Data_Format_List;
use Horde_Imap_Client_Data_Format_String_Nonascii;

/**
 * Tests for the Interaction Command object
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class CommandTest extends TestCase
{
    #[DataProvider('continuationCheckProvider')]
    public function testContinuationCheck($command, $result)
    {
        $this->assertEquals(
            $result,
            $command->continuation
        );
    }

    public static function continuationCheckProvider()
    {
        $out = [];

        $cmd = new Horde_Imap_Client_Interaction_Command('FOO', '1');
        $cmd->add([
            'FOO',
            'BAR',
        ]);

        $out[] = [$cmd, false];

        $cmd = clone $cmd;
        $cmd->add(
            new Horde_Imap_Client_Interaction_Command_Continuation(function () {})
        );

        $out[] = [$cmd, true];

        $cmd = new Horde_Imap_Client_Interaction_Command('FOO', '1');
        $cmd->add([
            'FOO',
            [
                'BAR',
            ],
            new Horde_Imap_Client_Data_Format_List([
                'BAR',
            ]),
        ]);

        $out[] = [$cmd, false];

        $cmd = new Horde_Imap_Client_Interaction_Command('FOO', '1');
        $cmd->add([
            'FOO',
            [
                'BAR',
                [
                    'BAZ',
                    [
                        new Horde_Imap_Client_Data_Format_String_Nonascii('Envoyé'),
                    ],
                ],
            ],
        ]);

        $out[] = [$cmd, true];

        return $out;
    }
}
