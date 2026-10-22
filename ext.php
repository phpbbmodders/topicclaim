<?php
/**
 *
 * Topic Claim extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\topicclaim;

/**
 * Topic Claim extension base
 */
class ext extends \phpbb\extension\base
{
	/** Notification type this extension adds */
	const NOTIFICATION_TYPE = 'phpbbmodders.topicclaim.notification.type.claim';

	/**
	 * Check whether the extension can be enabled.
	 * The current phpBB and PHP version should meet or
	 * exceed the minimum version required by this extension:
	 *
	 * @return bool|array
	 * @access public
	 */
	public function is_enableable()
	{
		$enableable = $this->check_phpbb_version() && $this->check_php_version();

		if (!$enableable)
		{
			$language = $this->container->get('language');
			$language->add_lang('install_topicclaim', 'phpbbmodders/topicclaim');

			return $language->lang('TOPICCLAIM_NOT_ENABLEABLE');
		}

		return $enableable;
	}

	/**
	 * Require phpBB 3.3.19 due to several security fixes in the phpBB software.
	 *
	 * @return bool
	 */
	public function check_phpbb_version()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.3.19', '>=');
	}

	/**
	 * Requires PHP 8.3.36 due to security fixes.
	 *
	 * @return bool
	 */
	public function check_php_version()
	{
		return PHP_VERSION_ID >= 80336;
	}

	/**
	 * Turn the notification type on when the extension is enabled
	 *
	 * @param mixed $old_state
	 * @return mixed
	 */
	public function enable_step($old_state)
	{
		if ($old_state === false)
		{
			$this->container->get('notification_manager')->enable_notifications(self::NOTIFICATION_TYPE);
			return 'notifications';
		}

		return parent::enable_step($old_state);
	}

	/**
	 * Turn the notification type off when the extension is disabled
	 *
	 * @param mixed $old_state
	 * @return mixed
	 */
	public function disable_step($old_state)
	{
		if ($old_state === false)
		{
			$this->container->get('notification_manager')->disable_notifications(self::NOTIFICATION_TYPE);
			return 'notifications';
		}

		return parent::disable_step($old_state);
	}

	/**
	 * Remove the extension's notifications when its data is deleted
	 *
	 * @param mixed $old_state
	 * @return mixed
	 */
	public function purge_step($old_state)
	{
		if ($old_state === false)
		{
			try
			{
				$this->container->get('notification_manager')->purge_notifications(self::NOTIFICATION_TYPE);
			}
			catch (\phpbb\notification\exception $e)
			{
				// The type was never registered, so there is nothing to remove
			}
			return 'notifications';
		}

		return parent::purge_step($old_state);
	}
}
