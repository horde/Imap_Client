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
use Horde\Imap\Client\Test\Stub\Base;
use Horde_Imap_Client_Data_Capability;
use Horde_Imap_Client_Data_SearchCharset;

/**
 * Tests for Horde_Imap_Client_Base SplObserver update() behavior.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class BaseObserverTest extends TestCase
{
    private Base $ob;

    public function setUp(): void
    {
        $this->ob = new Base([
            'username' => 'user',
            'password' => 'pass',
        ]);
    }

    public function testUpdateSetsChangedOnCapabilitySubject(): void
    {
        $cap = new Horde_Imap_Client_Data_Capability();
        $this->ob->changed = false;

        $this->ob->update($cap);

        $this->assertTrue($this->ob->changed);
    }

    public function testUpdateSetsChangedOnSearchCharsetSubject(): void
    {
        $sc = new Horde_Imap_Client_Data_SearchCharset();
        $this->ob->changed = false;

        $this->ob->update($sc);

        $this->assertTrue($this->ob->changed);
    }

    public function testUpdateCollectsAlertFromAlertsSubject(): void
    {
        $this->ob->alerts_ob->add('Test Alert');

        $alerts = $this->ob->alerts();
        $this->assertContains('Test Alert', $alerts);
    }

    public function testAlertsAreClearedAfterRetrieval(): void
    {
        $this->ob->alerts_ob->add('Alert');
        $this->ob->alerts();

        $this->assertEmpty($this->ob->alerts());
    }
}
