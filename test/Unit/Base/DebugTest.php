<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Base;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Horde_Imap_Client_Base_Debug;
use ReflectionProperty;

/**
 * Tests for Horde_Imap_Client_Base_Debug.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class DebugTest extends TestCase
{
    private function createDebug()
    {
        $stream = fopen('php://memory', 'r+');
        $debug = new Horde_Imap_Client_Base_Debug($stream);
        return [$debug, $stream];
    }

    private function readStream($stream): string
    {
        rewind($stream);
        return stream_get_contents($stream);
    }

    public function testClientOutput(): void
    {
        [$debug, $stream] = $this->createDebug();
        $debug->client('hello');

        $output = $this->readStream($stream);
        $this->assertStringContainsString('C: hello', $output);
    }

    public function testServerOutput(): void
    {
        [$debug, $stream] = $this->createDebug();
        $debug->server('response');

        $output = $this->readStream($stream);
        $this->assertStringContainsString('S: response', $output);
    }

    public function testInfoOutput(): void
    {
        [$debug, $stream] = $this->createDebug();
        $debug->info('message');

        $output = $this->readStream($stream);
        $this->assertStringContainsString('>> message', $output);
    }

    public function testRawOutput(): void
    {
        [$debug, $stream] = $this->createDebug();
        $debug->raw('rawdata');

        $output = $this->readStream($stream);
        $this->assertEquals('rawdata', $output);
    }

    public function testFirstWriteOutputsDateHeader(): void
    {
        [$debug, $stream] = $this->createDebug();
        $debug->client('first');

        $output = $this->readStream($stream);
        $this->assertStringContainsString('-----', $output);
        $this->assertStringContainsString('>> ', $output);
        $this->assertStringContainsString('C: first', $output);
    }

    public function testSlowCommandDetection(): void
    {
        [$debug, $stream] = $this->createDebug();
        $debug->client('first');

        $ref = new ReflectionProperty($debug, '_time');
        $ref->setAccessible(true);
        $ref->setValue($debug, microtime(true) - 6);

        $debug->client('second');

        $output = $this->readStream($stream);
        $this->assertStringContainsString('Slow Command:', $output);
    }

    public function testDebugDisabledSuppressesOutput(): void
    {
        [$debug, $stream] = $this->createDebug();
        $debug->debug = false;

        $debug->client('nothing');
        $debug->server('nothing');
        $debug->info('nothing');

        $this->assertEquals('', $this->readStream($stream));
    }

    public function testShutdownClosesStream(): void
    {
        [$debug, $stream] = $this->createDebug();
        $debug->shutdown();

        // After shutdown, writing should produce no output
        $debug->client('after shutdown');
        // Stream is closed, so we can't read it. Just verify no error
        $this->assertFalse(is_resource($stream));
    }
}
