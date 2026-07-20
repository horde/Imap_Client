<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author     Torben Dannhauer <torben@dannhauer.de>
 * @category   Horde
 * @copyright  2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 */

namespace Horde\Imap\Client\Test\Unit\Base;

use Horde\Imap\Client\Test\Stub\Base;
use Horde_Imap_Client_Data_Namespace;
use Horde_Imap_Client_Mailbox;
use Horde_Imap_Client_Namespace_List;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Horde_Imap_Client_Base#getNamespaces().
 *
 * @author     Torben Dannhauer <torben@dannhauer.de>
 * @copyright  2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class GetNamespacesTest extends TestCase
{
    /**
     * Nested-array additional namespaces must not trigger
     * "Array to string conversion" (horde/imp#94).
     */
    public function testAdditionalNestedArraysDoNotWarn(): void
    {
        $ob = new class ([
            'username' => 'user',
            'password' => 'pass',
        ]) extends Base {
            protected function _getNamespaces()
            {
                $ns = new Horde_Imap_Client_Data_Namespace();
                $ns->delimiter = '/';
                $ns->name = '';
                $ns->type = Horde_Imap_Client_Data_Namespace::NS_PERSONAL;

                return new Horde_Imap_Client_Namespace_List([$ns]);
            }
        };

        $result = $ob->getNamespaces(
            [
                '#shared/',
                '#broken' => ['delimiter' => '/'],
                ['nested'],
            ],
            ['ob_return' => true]
        );

        $this->assertInstanceOf(Horde_Imap_Client_Namespace_List::class, $result);
        $this->assertCount(1, $result);
    }

    /**
     * Already-advertised namespaces in $additional must be skipped
     * (array_map must use strval, not strlen, on the namespace list).
     */
    public function testSkipsAlreadyDetectedAdditionalNamespaces(): void
    {
        $listed = [];

        $ob = new class ([
            'username' => 'user',
            'password' => 'pass',
        ], $listed) extends Base {
            /** @var array */
            private $listedRef;

            public function __construct(array $params, array &$listed)
            {
                parent::__construct($params);
                $this->listedRef = &$listed;
            }

            protected function _getNamespaces()
            {
                $ns = new Horde_Imap_Client_Data_Namespace();
                $ns->delimiter = '/';
                $ns->name = '#shared/';
                $ns->type = Horde_Imap_Client_Data_Namespace::NS_SHARED;

                return new Horde_Imap_Client_Namespace_List([$ns]);
            }

            protected function _listMailboxes($pattern, $mode, $options)
            {
                $this->listedRef[] = $pattern;

                return [];
            }
        };

        $ob->getNamespaces(['#shared/', '#public/'], ['ob_return' => true]);

        $this->assertCount(1, $listed);
        $this->assertSame(
            ['#public/'],
            array_map('strval', $listed[0])
        );
    }

    public function testStringableAdditionalNamespacesAreAccepted(): void
    {
        $listed = [];

        $ob = new class ([
            'username' => 'user',
            'password' => 'pass',
        ], $listed) extends Base {
            /** @var array */
            private $listedRef;

            public function __construct(array $params, array &$listed)
            {
                parent::__construct($params);
                $this->listedRef = &$listed;
            }

            protected function _getNamespaces()
            {
                $ns = new Horde_Imap_Client_Data_Namespace();
                $ns->delimiter = '/';
                $ns->name = '';
                $ns->type = Horde_Imap_Client_Data_Namespace::NS_PERSONAL;

                return new Horde_Imap_Client_Namespace_List([$ns]);
            }

            protected function _listMailboxes($pattern, $mode, $options)
            {
                $this->listedRef[] = $pattern;

                return [];
            }
        };

        $ob->getNamespaces(
            [Horde_Imap_Client_Mailbox::get('#shared/')],
            ['ob_return' => true]
        );

        $this->assertCount(1, $listed);
        $this->assertSame(
            ['#shared/'],
            array_map('strval', $listed[0])
        );
    }
}
