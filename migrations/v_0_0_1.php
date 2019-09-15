<?php

/**
 * Migrations
 * */

namespace jeb\locker\migrations;

class v_0_0_1 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    { return false; }

    static public function depends_on()
    { return []; }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'locker' => [
                    'COLUMNS' => [
                        'id'      => ['UINT', null, 'auto_increment'],
                        'user_id' => ['INT:11', 0],
                        'post_id' => ['INT:11', 0],
                        'hash'    => ['VCHAR:255', ''],
                        'text'    => ['VCHAR:255', ''],
                    ],
                    'PRIMARY_KEY' => 'id',
                ],
                $this->table_prefix . 'locker_usage' => [
                    'COLUMNS' => [
                        'id'        => ['UINT', null, 'auto_increment'],
                        'locker_id' => ['INT:11', 0],
                        'user_id'   => ['INT:11', 0],
                        'n_access'  => ['INT:11', 0],
                        'first_access_time'  => ['INT:11', 0],
                        'latest_access_time' => ['INT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'id',
                ],
            ],
            'add_unique_index'    => [
                $this->table_prefix . 'locker'  => [
                    'postid_hash' => ['post_id', 'hash'],
                ],
                $this->table_prefix . 'locker_usage'  => [
                    'lockerid_userid' => ['locker_id', 'user_id'],
                ],
            ],
            'add_index'    => [
                $this->table_prefix . 'locker' => [
                    'hash' => ['hash'],
                    'post_id' => ['post_id'],
                    'user_id' => ['user_id'],
                ],
                $this->table_prefix . 'locker_usage' => [
                    'locker_id' => ['locker_id'],
                    'user_id' => ['user_id'],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'locker',
                $this->table_prefix . 'locker_usage',
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['lock_b_master', 1]],
        ];
    }

}
