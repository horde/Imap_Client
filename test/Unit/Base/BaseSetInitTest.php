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

/**
 * Tests for Horde_Imap_Client_Base _setInit() logic.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class BaseSetInitTest extends TestCase
{
    private function create(array $params = []): Base
    {
        return new Base(array_merge([
            'username' => 'user',
            'password' => 'pass',
        ], $params));
    }

    public function testSetsChangedFlag(): void
    {
        $ob = $this->create();
        $ob->changed = false;

        $ob->setInit('testkey', 'testval');

        $this->assertTrue($ob->changed);
    }

    public function testNullKeyResetsAll(): void
    {
        $ob = $this->create();
        $ob->setInit('key1', 'val1');
        $ob->setInit('key2', 'val2');

        $ob->setInit(null);

        $data = $ob->__serialize();
        $this->assertEmpty($data['i']);
    }

    public function testNullValueRemovesKey(): void
    {
        $ob = $this->create();
        $ob->setInit('key', 'val');
        $ob->setInit('key', null);

        $data = $ob->__serialize();
        $this->assertArrayNotHasKey('key', $data['i']);
    }

    public function testCapabilityFilterRemovesIgnored(): void
    {
        $ob = $this->create([
            'capability_ignore' => ['IDLE'],
        ]);

        $cap = new Horde_Imap_Client_Data_Capability();
        $cap->add('IDLE');
        $cap->add('SORT');

        $ob->setInit('capability', $cap);

        $this->assertFalse($ob->capability->query('IDLE'));
        $this->assertTrue($ob->capability->query('SORT'));
    }

    public function testCapabilityFilterWithParam(): void
    {
        $ob = $this->create([
            'capability_ignore' => ['AUTH=PLAIN'],
        ]);

        $cap = new Horde_Imap_Client_Data_Capability();
        $cap->add('AUTH', 'PLAIN');
        $cap->add('AUTH', 'LOGIN');

        $ob->setInit('capability', $cap);

        $this->assertFalse($ob->capability->query('AUTH', 'PLAIN'));
        $this->assertTrue($ob->capability->query('AUTH', 'LOGIN'));
    }

    public function testCapabilityAttachesObserver(): void
    {
        $ob = $this->create();
        $cap = new Horde_Imap_Client_Data_Capability();

        $ob->setInit('capability', $cap);
        $ob->changed = false;

        // Modifying the capability object should trigger the observer
        $cap->add('NEWCAP');

        $this->assertTrue($ob->changed);
    }

    public function testNoChangeIfValueIdentical(): void
    {
        $ob = $this->create();
        $ob->setInit('key', 'val');
        $ob->changed = false;

        $ob->setInit('key', 'val');

        $this->assertFalse($ob->changed);
    }
}
