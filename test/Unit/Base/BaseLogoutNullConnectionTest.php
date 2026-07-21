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

use Horde\Imap\Client\Test\Stub\Base;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression test for logout() null-connection guard.
 *
 * Historically Horde_Imap_Client_Base::logout() read
 * `$this->_connection->connected` when `_isAuthenticated` was true.
 * If an authenticated client was torn down before _connection was
 * materialized (or after it had been cleared and _isAuthenticated
 * still flagged true), PHP 8+ raised
 * "Attempt to read property 'connected' on null" from shutdown().
 * PHPUnit's `failOnWarning="true"` in phpunit.xml.dist turns the
 * warning into a test failure — the assertion is implicit.
 */
#[CoversNothing]
class BaseLogoutNullConnectionTest extends TestCase
{
    /**
     * When _isAuthenticated is true but _connection is null,
     * logout() must not warn on the ->connected read; it should
     * zero the state and return cleanly.
     */
    public function testLogoutWithAuthenticatedFlagAndNullConnectionDoesNotWarn(): void
    {
        $ob = new Base([
            'username' => 'user',
            'password' => 'pass',
        ]);

        // Force the exact state the CI stack trace observed:
        // _isAuthenticated = true, _connection = null. Reflection
        // is needed because both properties are protected and no
        // setter exists (nor should one — this state is only
        // reachable through internal lifecycle races).
        $reflection = new ReflectionClass($ob);

        $authProperty = $reflection->getProperty('_isAuthenticated');
        $authProperty->setValue($ob, true);

        $connProperty = $reflection->getProperty('_connection');
        $connProperty->setValue($ob, null);

        // logout() is where the warning fired. If it warns here, the
        // test fails via phpunit.xml.dist's failOnWarning="true".
        $ob->logout();

        // State should be zeroed regardless of the pre-call condition.
        $this->assertFalse($authProperty->getValue($ob));
        $this->assertNull($connProperty->getValue($ob));
    }
}
