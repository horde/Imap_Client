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
 * SQL schema for the Db IMAP/POP cache driver.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @ignore
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Imap_Client
 */
class HordeImapClientBaseTables extends Horde_Db_Migration_Base
{
    public function up()
    {
        if (in_array('horde_imap_client_data', $this->tables())) {
            return;
        }

        $t = $this->createTable('horde_imap_client_data', [
            'autoincrementKey' => 'messageid',
        ]);
        $t->column('hostspec', 'string', [
            'limit' => 255,
            'null' => false,
        ]);
        $t->column('mailbox', 'string', [
            'limit' => 255,
            'null' => false,
        ]);
        $t->column('modified', 'bigint');
        $t->column('port', 'integer', [
            'null' => false,
        ]);
        $t->column('username', 'string', [
            'limit' => 255,
            'null' => false,
        ]);
        $t->end();

        $this->addIndex(
            'horde_imap_client_data',
            ['hostspec', 'mailbox', 'port', 'username']
        );

        $t = $this->createTable('horde_imap_client_message', [
            'autoincrementKey' => false,
        ]);
        $t->column('data', 'binary');
        $t->column('msguid', 'string', [
            'null' => false,
        ]);
        $t->column('messageid', 'bigint', [
            'null' => false,
        ]);
        $t->end();

        $this->addIndex(
            'horde_imap_client_message',
            ['msguid', 'messageid']
        );

        $t = $this->createTable('horde_imap_client_metadata', [
            'autoincrementKey' => false,
        ]);
        $t->column('data', 'binary');
        $t->column('field', 'string', [
            'null' => false,
        ]);
        $t->column('messageid', 'bigint', [
            'null' => false,
        ]);
        $t->end();

        $this->addIndex(
            'horde_imap_client_metadata',
            ['messageid']
        );
    }

    public function down()
    {
        $this->dropTable('horde_imap_client_data');
        $this->dropTable('horde_imap_client_message');
        $this->dropTable('horde_imap_client_metadata');
    }
}
