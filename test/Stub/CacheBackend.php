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

namespace Horde\Imap\Client\Test\Stub;

use Horde_Imap_Client_Cache_Backend;

/**
 * Horde_Imap_Client_Base cache logic testing needs a minimal in-memory Horde_Imap_Client_Cache backend(metadata invalidation, message
 * deletion) without a real storage driver.
 *
 * Message data and per-mailbox metadata are held in plain arrays. Tests can seed and inspect them directly.
 *
 * @author     Horde LLC
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class CacheBackend extends Horde_Imap_Client_Cache_Backend
{
    /**
     * Cached message data, keyed by mailbox then UID.
     *
     * @var array
     */
    public $data = [];

    /**
     * Cached mailbox metadata, keyed by mailbox then entry name.
     *
     * @var array
     */
    public $metadata = [];

    public function get($mailbox, $uids, $fields, $uidvalid)
    {
        $out = [];

        foreach ($uids as $uid) {
            if (isset($this->data[$mailbox][$uid])) {
                $out[$uid] = $this->data[$mailbox][$uid];
            }
        }

        return $out;
    }

    public function getCachedUids($mailbox, $uidvalid)
    {
        return isset($this->data[$mailbox])
            ? array_keys($this->data[$mailbox])
            : [];
    }

    public function set($mailbox, $data, $uidvalid)
    {
        foreach ($data as $uid => $fields) {
            $this->data[$mailbox][$uid] = isset($this->data[$mailbox][$uid])
                ? array_merge($this->data[$mailbox][$uid], $fields)
                : $fields;
        }
    }

    public function getMetaData($mailbox, $uidvalid, $entries)
    {
        $md = $this->metadata[$mailbox] ?? [];
        $md['uidvalid'] = $uidvalid;

        return empty($entries)
            ? $md
            : array_intersect_key($md, array_flip(array_merge($entries, ['uidvalid'])));
    }

    public function setMetaData($mailbox, $data)
    {
        unset($data['uidvalid']);
        $this->metadata[$mailbox] = array_merge(
            $this->metadata[$mailbox] ?? [],
            $data
        );
    }

    public function deleteMsgs($mailbox, $uids)
    {
        foreach ($uids as $uid) {
            unset($this->data[$mailbox][$uid]);
        }
    }

    public function deleteMailbox($mailbox)
    {
        unset($this->data[$mailbox], $this->metadata[$mailbox]);
    }

    public function clear($lifetime)
    {
        $this->data = [];
        $this->metadata = [];
    }
}
