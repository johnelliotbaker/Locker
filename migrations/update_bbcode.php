<?php
/**
 *
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace jeb\locker\migrations;

use jeb\locker\core\bbcodes_installer;
use phpbb\db\migration\container_aware_migration;

class update_bbcode extends container_aware_migration
{
	static public function depends_on()
	{
        return array('\jeb\locker\migrations\add_bbcode');
    }

    public function update_data()
    {
        return array(
            array('custom', array(array($this, 'update_bbcodes'))),
        );
    }
    public function update_bbcodes()
    {
        $install = new bbcodes_installer($this->db, $this->container->get('request'), $this->container->get('user'), $this->phpbb_root_path, $this->php_ext);
        $install->install_bbcodes([
            'locker' => [
                'display_on_posting'	=> false,
                'bbcode_match'		    => '[locker]{SIMPLETEXT}[/locker]',
                'bbcode_tpl'		    => '
                <span data-hash="{SIMPLETEXT}" id="lock_tag_{SIMPLETEXT}" class="locker pointer noselect hide">
                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                </span>
                <span data-hash="{SIMPLETEXT}" id="locker_{SIMPLETEXT}" class="locker pointer noselect">
                    <i class="fa fa-key" aria-hidden="true"></i>
                </span>', ],
            ]
        );

    }
}
