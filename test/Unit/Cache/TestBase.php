<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2013-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Cache;

use PHPUnit\Framework\TestCase;
use Horde_Imap_Client_Cache;
use Horde_Imap_Client_Socket;

/**
 * Tests for the Horde_Cache cache driver.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2013-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
abstract class TestBase extends TestCase
{
    public const HOSTSPEC = 'foo.example.com';
    public const PORT = 143;
    public const USERNAME = 'baz';

    private $_cache;

    public function setUp(): void
    {
        $baseob = $this->createMock(Horde_Imap_Client_Socket::class);
        $baseob->method('getParam')
            ->willReturnCallback([$this, '_baseobHandler']);
        $baseob->method('__serialize')
            ->willReturn([]);

        $this->_cache = new Horde_Imap_Client_Cache([
            'backend' => $this->_getBackend(),
            'baseob' => $baseob,
        ]);

        /* Setup DB with dummy data. Yes... I realize this sort of relies
         * on set() and setMetaData() to be working, but otherwise we have to
         * track INTERNAL changes to the driver from this EXTERNAL
         * perspective. */
        $this->_cache->set('foo1', [
            '100' => [
                'subject' => 'Test1',
            ],
            '101' => [
                'subject' => 'Test2',
            ],
            '102' => [
                'from' => 'foo2@example.com',
                'subject' => 'Test3',
            ],
            '103' => [
                'subject' => 'Test4',
            ],
        ], 1);
        $this->_cache->set('foo2', [
            '300' => [
                'from' => 'foo3@example.com',
            ],
            '400' => [
                'subject' => 'Test 5',
            ],
        ], 1);

        $this->_cache->setMetaData('foo1', '1', [
            'bar' => 'foo',
        ]);
    }

    abstract protected function _getBackend();

    public function _baseobHandler($param)
    {
        switch ($param) {
            case 'hostspec':
                return self::HOSTSPEC;

            case 'port':
                return self::PORT;

            case 'username':
                return self::USERNAME;
        }
    }

    public function tearDown(): void
    {
        unset($this->_cache);
    }

    public function testGet()
    {
        $res = $this->_cache->get('foo1', [100, 101, 102], [], 1);

        $this->assertEquals(
            3,
            count($res)
        );
        $this->assertEquals(
            1,
            count($res['100'])
        );
        $this->assertEquals(
            1,
            count($res['101'])
        );
        $this->assertEquals(
            2,
            count($res['102'])
        );
        $this->assertEquals(
            'Test3',
            $res['102']['subject']
        );

        $res = $this->_cache->get('foo2', [300, 301], [], 1);

        $this->assertEquals(
            1,
            count($res)
        );
        $this->assertEquals(
            'foo3@example.com',
            $res['300']['from']
        );
        $this->assertFalse(array_key_exists('301', $res));

        $res = $this->_cache->get('foo2', [300], ['to'], 1);
        $this->assertFalse(array_key_exists('to', $res['300']));

        $res = $this->_cache->get('foo3', [400], [], 1);
        $this->assertEquals(
            0,
            count($res)
        );
    }

    public function testGetCachedUids()
    {
        $res = $this->_cache->get('foo1', [], [], 1);
        $this->assertEquals(
            4,
            count($res)
        );

        $res = $this->_cache->get('foo2', [], [], 1);
        $this->assertEquals(
            2,
            count($res)
        );

        $res = $this->_cache->get('foo3', [], [], 1);
        $this->assertEquals(
            0,
            count($res)
        );
    }

    public function testSet()
    {
        /* Insert */
        $data = [
            '100' => [
                'size' => 5,
                'to' => 'foo3@example2.com',
            ],
            '101' => [
                'to' => 'foo3@example2.com',
            ],
        ];
        $this->_cache->set('foo1', $data, 1);

        $res = $this->_cache->get('foo1', [100, 101], [], 1);
        $this->assertEquals(
            3,
            count($res['100'])
        );
        $this->assertEquals(
            2,
            count($res['101'])
        );

        /* Update */
        $data = [
            '102' => [
                'subject' => 'ABC',
            ],
        ];
        $this->_cache->set('foo1', $data, 1);

        $res = $this->_cache->get('foo1', [102], [], 1);
        $this->assertEquals(
            'ABC',
            $res['102']['subject']
        );
    }

    public function testGetMetaData()
    {
        $res = $this->_cache->getMetaData('foo1', '1', []);
        $this->assertEquals(
            2,
            count($res)
        );

        $res = $this->_cache->getMetaData('foo1', '1', ['uidvalid']);
        $this->assertEquals(
            1,
            count($res)
        );
        $this->assertEquals(
            1,
            $res['uidvalid']
        );

        $res = $this->_cache->getMetaData('foo2', '1', []);
        $this->assertEquals(
            1,
            count($res)
        );
        $this->assertFalse(array_key_exists('bar', $res));
    }

    public function testSetMetaData()
    {
        /* Insert */
        $this->_cache->setMetaData('foo1', '1', ['baz' => 'ABC']);

        $res = $this->_cache->getMetaData('foo1', '1', ['baz']);
        $this->assertEquals(
            'ABC',
            $res['baz']
        );

        /* Update */
        $this->_cache->setMetaData('foo1', '1', ['baz' => 'DEF']);

        $res = $this->_cache->getMetaData('foo1', '1', ['baz']);
        $this->assertEquals(
            'DEF',
            $res['baz']
        );
    }

    public function testDeleteMessages()
    {
        $this->_cache->deleteMsgs('foo1', [100, 101]);
        $this->assertEquals(
            2,
            count($this->_cache->get('foo1', [], [], 1))
        );

        /* Total count shouldn't change here. */
        $this->_cache->deleteMsgs('foo1', [100, 101]);
        $this->assertEquals(
            2,
            count($this->_cache->get('foo1', [], [], 1))
        );
    }

    public function testDeleteMailbox()
    {
        $this->_cache->deleteMailbox('foo1');
        $this->assertEquals(
            0,
            count($this->_cache->get('foo1', [], [], 1))
        );
    }

    public function testSerialization()
    {
        $this->assertInstanceOf(
            get_class($this->_cache),
            unserialize(serialize($this->_cache))
        );
    }
}
