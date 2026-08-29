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
use Horde\Imap\Client\ImapWireList;
use Horde\Imap\Client\ImapWireString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapCommand::class)]
class ImapCommandTest extends TestCase
{
    /**
     * Flatten a command's segments into one string, the way a
     * connection would if it never had to stop for a literal.
     */
    private function render(ImapCommand $command): string
    {
        $rendered = '';

        foreach ($command->segments() as $segment) {
            $rendered .= $segment->isLiteral ? $segment->bytes : $segment->text;
        }

        return $rendered;
    }

    public function testSimpleCommandWithNoArguments(): void
    {
        $command = new ImapCommand('A1', 'NOOP');

        self::assertSame('A1 NOOP', $this->render($command));
        self::assertFalse($command->needsContinuation());
    }

    public function testCommandWithBareStringArguments(): void
    {
        $command = new ImapCommand('A2', 'SELECT', ['INBOX']);

        self::assertSame('A2 SELECT INBOX', $this->render($command));
    }

    public function testCommandWithWireValueArgument(): void
    {
        $command = new ImapCommand('A3', 'LOGIN', [
            new ImapWireString('admin'),
            new ImapWireString('sw0rdfish'),
        ]);

        self::assertSame('A3 LOGIN admin sw0rdfish', $this->render($command));
        self::assertFalse($command->needsContinuation());
    }

    public function testCommandWithParenthesizedListArgument(): void
    {
        $command = new ImapCommand('A4', 'STORE', [
            '1:5',
            '+FLAGS',
            new ImapWireList(['\\Seen', '\\Deleted']),
        ]);

        self::assertSame('A4 STORE 1:5 +FLAGS (\\Seen \\Deleted)', $this->render($command));
    }

    public function testCommandNeedsContinuationForLiteralArgument(): void
    {
        $command = new ImapCommand('A5', 'LOGIN', [
            new ImapWireString('admin'),
            new ImapWireString("multi\r\nline"),
        ]);

        self::assertTrue($command->needsContinuation());
    }

    public function testLiteralArgumentProducesLiteralSegment(): void
    {
        $command = new ImapCommand('A6', 'LOGIN', [
            new ImapWireString('admin'),
            new ImapWireString("multi\r\nline"),
        ]);

        $segments = $command->segments();
        $literalSegments = array_values(array_filter($segments, fn($s) => $s->isLiteral));

        self::assertCount(1, $literalSegments);
        self::assertSame("multi\r\nline", $literalSegments[0]->bytes);
        self::assertSame(11, $literalSegments[0]->length());
        self::assertFalse($literalSegments[0]->isBinary);
    }

    public function testNestedListWithLiteralMemberNeedsContinuation(): void
    {
        $command = new ImapCommand('A7', 'APPEND', [
            'INBOX',
            new ImapWireList(['\\Seen', new ImapWireString("bin\x00ary")]),
        ]);

        self::assertTrue($command->needsContinuation());

        $segments = $command->segments();
        $rendered = '';
        $sawLiteral = false;

        foreach ($segments as $segment) {
            if ($segment->isLiteral) {
                $sawLiteral = true;
                self::assertTrue($segment->isBinary);
                $rendered .= $segment->bytes;
                continue;
            }

            $rendered .= $segment->text;
        }

        self::assertTrue($sawLiteral);
        self::assertSame("A7 APPEND INBOX (\\Seen bin\x00ary)", $rendered);
    }
}
