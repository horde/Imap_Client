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
use Horde_Imap_Client_Base_Alerts;
use SplObserver;
use SplSubject;

/**
 * Tests for Horde_Imap_Client_Base_Alerts.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class AlertsTest extends TestCase
{
    private Horde_Imap_Client_Base_Alerts $alerts;

    public function setUp(): void
    {
        $this->alerts = new Horde_Imap_Client_Base_Alerts();
    }

    public function testGetLastReturnsNullInitially(): void
    {
        $this->assertNull($this->alerts->getLast());
    }

    public function testAddSetsAlertData(): void
    {
        $this->alerts->add('Test alert');

        $last = $this->alerts->getLast();
        $this->assertEquals('Test alert', $last->alert);
        $this->assertFalse(isset($last->type));
    }

    public function testAddWithType(): void
    {
        $this->alerts->add('Alert msg', 'WARNING');

        $last = $this->alerts->getLast();
        $this->assertEquals('Alert msg', $last->alert);
        $this->assertEquals('WARNING', $last->type);
    }

    public function testAddOverwritesPrevious(): void
    {
        $this->alerts->add('First');
        $this->alerts->add('Second');

        $this->assertEquals('Second', $this->alerts->getLast()->alert);
    }

    public function testAttachAndNotify(): void
    {
        $observer = $this->createMock(SplObserver::class);
        $observer->expects($this->once())
            ->method('update')
            ->with($this->alerts);

        $this->alerts->attach($observer);
        $this->alerts->add('Test');
    }

    public function testDetach(): void
    {
        $observer = $this->createMock(SplObserver::class);
        $observer->expects($this->never())
            ->method('update');

        $this->alerts->attach($observer);
        $this->alerts->detach($observer);
        $this->alerts->add('Test');
    }

    public function testAttachDeduplicates(): void
    {
        $observer = $this->createMock(SplObserver::class);
        $observer->expects($this->once())
            ->method('update');

        $this->alerts->attach($observer);
        $this->alerts->attach($observer);
        $this->alerts->add('Test');
    }

    public function testMultipleObservers(): void
    {
        $obs1 = $this->createMock(SplObserver::class);
        $obs1->expects($this->once())->method('update');

        $obs2 = $this->createMock(SplObserver::class);
        $obs2->expects($this->once())->method('update');

        $this->alerts->attach($obs1);
        $this->alerts->attach($obs2);
        $this->alerts->add('Test');
    }
}
