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

use Horde\Imap\Client\ImapCommandTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapCommandTag::class)]
class ImapCommandTagTest extends TestCase
{
    public function testGeneratesSequentialTags(): void
    {
        $tags = new ImapCommandTag();

        self::assertSame('A1', $tags->next());
        self::assertSame('A2', $tags->next());
        self::assertSame('A3', $tags->next());
    }

    public function testUsesCustomPrefix(): void
    {
        $tags = new ImapCommandTag('X');

        self::assertSame('X1', $tags->next());
        self::assertSame('X2', $tags->next());
    }

    public function testTwoGeneratorsAreIndependent(): void
    {
        $first = new ImapCommandTag();
        $second = new ImapCommandTag();

        $first->next();
        $first->next();

        self::assertSame('A1', $second->next());
    }
}
