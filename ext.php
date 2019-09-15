<?php
/**
 *
 * phpBB lockers. An extension for the phpBB Forum Software package.
 *
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace jeb\locker;

/**
 * phpBB lockers Extension base
 *
 */
class ext extends \phpbb\extension\base
{
	/**
	 * Enable notifications for the extension
	 *
	 * @param mixed $old_state State returned by previous call of this method
	 *
	 * @return mixed Returns false after last step, otherwise temporary state
	 */
	public function enable_step($old_state)
	{
        return parent::enable_step($old_state);
	}

	/**
	 * Disable notifications for the extension
	 *
	 * @param mixed $old_state State returned by previous call of this method
	 *
	 * @return mixed Returns false after last step, otherwise temporary state
	 */
	public function disable_step($old_state)
	{
        return parent::disable_step($old_state);
	}

	/**
	 * Purge notifications for the extension
	 *
	 * @param mixed $old_state State returned by previous call of this method
	 *
	 * @return mixed Returns false after last step, otherwise temporary state
	 */
	public function purge_step($old_state)
	{
        return parent::purge_step($old_state);
	}
}
