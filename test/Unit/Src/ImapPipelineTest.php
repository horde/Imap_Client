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

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\ImapCommand;
use Horde\Imap\Client\ImapPipeline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapPipeline::class)]
class ImapPipelineTest extends TestCase
{
    public function testEnqueuedCommandIsPending(): void
    {
        $pipeline = new ImapPipeline();
        $pipeline->enqueue(new ImapCommand('A1', 'NOOP'));

        self::assertTrue($pipeline->isPending('A1'));
        self::assertSame(1, $pipeline->count());
        self::assertFalse($pipeline->isEmpty());
    }

    public function testCompleteRemovesAndReturnsTheCommand(): void
    {
        $pipeline = new ImapPipeline();
        $command = new ImapCommand('A1', 'NOOP');
        $pipeline->enqueue($command);

        $completed = $pipeline->complete('A1');

        self::assertSame($command, $completed);
        self::assertFalse($pipeline->isPending('A1'));
        self::assertTrue($pipeline->isEmpty());
    }

    public function testCompletingAnUnknownTagReturnsNull(): void
    {
        $pipeline = new ImapPipeline();

        self::assertNull($pipeline->complete('A9'));
    }

    public function testTracksMultipleOutstandingCommands(): void
    {
        $pipeline = new ImapPipeline();
        $pipeline->enqueue(new ImapCommand('A1', 'NOOP'));
        $pipeline->enqueue(new ImapCommand('A2', 'NOOP'));

        self::assertSame(2, $pipeline->count());

        $pipeline->complete('A1');

        self::assertSame(1, $pipeline->count());
        self::assertTrue($pipeline->isPending('A2'));
    }
}
