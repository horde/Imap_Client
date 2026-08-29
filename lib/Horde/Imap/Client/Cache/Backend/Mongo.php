<?php

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Imap_Client
 */

/**
 * A MongoDB database implementation for caching IMAP/POP data.
 *
 * Requires the Horde_Mongo class.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Imap_Client
 */
class Horde_Imap_Client_Cache_Backend_Mongo extends Horde_Imap_Client_Cache_Backend implements Horde_Mongo_Collection_Index
{
    /** Mongo collection names. */
    public const BASE = 'horde_imap_client_cache_data';
    public const MD = 'horde_imap_client_cache_metadata';
    public const MSG = 'horde_imap_client_cache_message';

    /** Mongo field names: BASE collection. */
    public const BASE_HOSTSPEC = 'hostspec';
    public const BASE_MAILBOX = 'mailbox';
    public const BASE_MODIFIED = 'modified';
    public const BASE_PORT = 'port';
    public const BASE_UID = 'data';
    public const BASE_USERNAME = 'username';

    /** Mongo field names: MD collection. */
    public const MD_DATA = 'data';
    public const MD_FIELD = 'field';
    public const MD_UID = 'uid';

    /** Mongo field names: MSG collection. */
    public const MSG_DATA = 'data';
    public const MSG_MSGUID = 'msguid';
    public const MSG_UID = 'uid';

    /**
     * The MongoDB object for the cache data.
     *
     * @var MongoDB
     */
    protected $_db;

    /**
     * The list of indices.
     *
     * @var array
     */
    protected $_indices = [
        self::BASE => [
            'base_index_1' => [
                self::BASE_HOSTSPEC => 1,
                self::BASE_MAILBOX => 1,
                self::BASE_PORT => 1,
                self::BASE_USERNAME => 1,
            ],
        ],
        self::MSG => [
            'msg_index_1' => [
                self::MSG_MSGUID => 1,
                self::MSG_UID => 1,
            ],
        ],
    ];

    /**
     * Constructor.
     *
     * @param array $params  Configuration parameters:
     * <pre>
     *   - REQUIRED parameters:
     *     - mongo_db: (Horde_Mongo_Client) A MongoDB client object.
     * </pre>
     */
    public function __construct(array $params = [])
    {
        if (!isset($params['mongo_db'])) {
            throw new InvalidArgumentException('Missing mongo_db parameter.');
        }

        parent::__construct($params);
    }

    /**
     */
    protected function _initOb()
    {
        $this->_db = $this->_params['mongo_db']->selectDB(null);
    }

    /**
     */
    public function get($mailbox, $uids, $fields, $uidvalid)
    {
        $this->getMetaData($mailbox, $uidvalid, ['uidvalid']);

        if (!($uid = $this->_getUid($mailbox))) {
            return [];
        }

        $out = [];
        $query = [
            self::MSG_MSGUID => ['$in' => array_map('strval', $uids)],
            self::MSG_UID => $uid,
        ];

        try {
            $cursor = $this->_db->selectCollection(self::MSG)->find(
                $query,
                [self::MSG_DATA => true, self::MSG_MSGUID => true]
            );
            foreach ($cursor as $val) {
                try {
                    $out[$val[self::MSG_MSGUID]] = $this->_value($val[self::MSG_DATA]);
                } catch (Exception $e) {
                }
            }
        } catch (MongoException $e) {
        }

        return $out;
    }

    /**
     */
    public function getCachedUids($mailbox, $uidvalid)
    {
        $this->getMetaData($mailbox, $uidvalid, ['uidvalid']);

        if (!($uid = $this->_getUid($mailbox))) {
            return [];
        }

        $out = [];
        $query = [
            self::MSG_UID => $uid,
        ];

        try {
            $cursor = $this->_db->selectCollection(self::MSG)->find(
                $query,
                [self::MSG_MSGUID => true]
            );
            foreach ($cursor as $val) {
                $out[] = $val[self::MSG_MSGUID];
            }
        } catch (MongoException $e) {
        }

        return $out;
    }

    /**
     */
    public function set($mailbox, $data, $uidvalid)
    {
        if ($uid = $this->_getUid($mailbox)) {
            $res = $this->get($mailbox, array_keys($data), [], $uidvalid);
        } else {
            $res = [];
            $uid = $this->_createUid($mailbox);
        }

        $coll = $this->_db->selectCollection(self::MSG);

        foreach ($data as $key => $val) {
            try {
                if (isset($res[$key])) {
                    $coll->update([
                        self::MSG_MSGUID => strval($key),
                        self::MSG_UID => $uid,
                    ], [
                        self::MSG_DATA => $this->_value(array_merge($res[$key], $val)),
                        self::MSG_MSGUID => strval($key),
                        self::MSG_UID => $uid,
                    ]);
                } else {
                    $doc = [
                        self::MSG_DATA => $this->_value($val),
                        self::MSG_MSGUID => strval($key),
                        self::MSG_UID => $uid,
                    ];
                    $coll->insert($doc);
                }
            } catch (MongoException $e) {
            }
        }

        /* Update modified time. */
        try {
            $this->_db->selectCollection(self::BASE)->update(
                [self::BASE_UID => $uid],
                [self::BASE_MODIFIED => time()]
            );
        } catch (MongoException $e) {
        }

        /* Update uidvalidity. */
        $this->setMetaData($mailbox, ['uidvalid' => $uidvalid]);
    }

    /**
     */
    public function getMetaData($mailbox, $uidvalid, $entries)
    {
        if (!($uid = $this->_getUid($mailbox))) {
            return [];
        }

        $out = [];
        $query = [
            self::MD_UID => $uid,
        ];

        if (!empty($entries)) {
            $entries[] = 'uidvalid';
            $query[self::MD_FIELD] = [
                '$in' => array_unique($entries),
            ];
        }

        try {
            $cursor = $this->_db->selectCollection(self::MD)->find(
                $query,
                [self::MD_DATA => true, self::MD_FIELD => true]
            );
            foreach ($cursor as $val) {
                try {
                    $out[$val[self::MD_FIELD]] = $this->_value($val[self::MD_DATA]);
                } catch (Exception $e) {
                }
            }

            if (is_null($uidvalid)
                || !isset($out['uidvalid'])
                || ($out['uidvalid'] == $uidvalid)) {
                return $out;
            }

            $this->deleteMailbox($mailbox);
        } catch (MongoException $e) {
        }

        return [];
    }

    /**
     */
    public function setMetaData($mailbox, $data)
    {
        if (!($uid = $this->_getUid($mailbox))) {
            $uid = $this->_createUid($mailbox);
        }

        $coll = $this->_db->selectCollection(self::MD);

        foreach ($data as $key => $val) {
            try {
                $coll->update(
                    [
                        self::MD_FIELD => $key,
                        self::MD_UID => $uid,
                    ],
                    [
                        self::MD_DATA => $this->_value($val),
                        self::MD_FIELD => $key,
                        self::MD_UID => $uid,
                    ],
                    ['upsert' => true]
                );
            } catch (MongoException $e) {
            }
        }
    }

    /**
     */
    public function deleteMsgs($mailbox, $uids)
    {
        if (!empty($uids) && ($uid = $this->_getUid($mailbox))) {
            try {
                $this->_db->selectCollection(self::MSG)->remove([
                    self::MSG_MSGUID => [
                        '$in' => array_map('strval', $uids),
                    ],
                    self::MSG_UID => $uid,
                ]);
            } catch (MongoException $e) {
            }
        }
    }

    /**
     */
    public function deleteMailbox($mailbox)
    {
        if (!($uid = $this->_getUid($mailbox))) {
            return;
        }

        foreach ([self::BASE, self::MD, self::MSG] as $val) {
            try {
                $this->_db->selectCollection($val)
                    ->remove(['uid' => $uid]);
            } catch (MongoException $e) {
            }
        }
    }

    /**
     */
    public function clear($lifetime)
    {
        if (is_null($lifetime)) {
            foreach ([self::BASE, self::MD, self::MSG] as $val) {
                $this->_db->selectCollection($val)->drop();
            }
            return;
        }

        $query = [
            self::BASE_MODIFIED => ['$lt' => (time() - $lifetime)],
        ];
        $uids = [];

        try {
            $cursor = $this->_db->selectCollection(self::BASE)->find($query);
            foreach ($cursor as $val) {
                $uids[] = strval($val['_id']);
            }
        } catch (MongoException $e) {
        }

        if (empty($uids)) {
            return;
        }

        foreach ([self::BASE, self::MD, self::MSG] as $val) {
            try {
                $this->_db->selectCollection($val)
                    ->remove(['uid' => ['$in' => $uids]]);
            } catch (MongoException $e) {
            }
        }
    }

    /**
     * Return the UID for a mailbox/user/server combo.
     *
     * @param string $mailbox  Mailbox name.
     *
     * @return string  UID from base table.
     */
    protected function _getUid($mailbox)
    {
        $query = [
            self::BASE_HOSTSPEC => $this->_params['hostspec'],
            self::BASE_MAILBOX => $mailbox,
            self::BASE_PORT => $this->_params['port'],
            self::BASE_USERNAME => $this->_params['username'],
        ];

        try {
            if ($result = $this->_db->selectCollection(self::BASE)->findOne($query)) {
                return strval($result['_id']);
            }
        } catch (MongoException $e) {
        }

        return null;
    }

    /**
     * Create and return the UID for a mailbox/user/server combo.
     *
     * @param string $mailbox  Mailbox name.
     *
     * @return string  UID from base table.
     */
    protected function _createUid($mailbox)
    {
        $doc = [
            self::BASE_HOSTSPEC => $this->_params['hostspec'],
            self::BASE_MAILBOX => $mailbox,
            self::BASE_PORT => $this->_params['port'],
            self::BASE_USERNAME => $this->_params['username'],
        ];
        $this->_db->selectCollection(self::BASE)->insert($doc);

        return $this->_getUid($mailbox);
    }

    /**
     * Convert data from/to storage format.
     *
     * @param mixed|MongoBinData $data  The data object.
     *
     * @return mixed|MongoBinData  The converted data.
     */
    protected function _value($data)
    {
        static $compress;

        if (!isset($compress)) {
            $compress = new Horde_Compress_Fast();
        }

        return ($data instanceof MongoBinData)
            ? @unserialize($compress->decompress($data->bin))
            : new MongoBinData(
                $compress->compress(serialize($data)),
                MongoBinData::BYTE_ARRAY
            );
    }

    /* Horde_Mongo_Collection_Index methods. */

    /**
     */
    public function checkMongoIndices()
    {
        foreach ($this->_indices as $key => $val) {
            if (!$this->_params['mongo_db']->checkIndices($key, $val)) {
                return false;
            }
        }

        return true;
    }

    /**
     */
    public function createMongoIndices()
    {
        foreach ($this->_indices as $key => $val) {
            $this->_params['mongo_db']->createIndices($key, $val);
        }
    }

}
