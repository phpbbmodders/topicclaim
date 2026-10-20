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
	 * Refuse to enable below the minimum phpBB and PHP versions
	 *
	 * @return bool|string True if enableable, otherwise the reason
	 */
	public function is_enableable()
	{
		if (!$this->check_phpbb_version() || !$this->check_php_version())
		{
			$language = $this->container->get('language');
			$language->add_lang('install_topicclaim', 'phpbbmodders/topicclaim');

			return $language->lang('TOPICCLAIM_NOT_ENABLEABLE');
		}

		return true;
	}

	/**
	 * Require phpBB 3.3.19
	 *
	 * @return bool
	 */
	public function check_phpbb_version()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.3.19', '>=');
	}

	/**
	 * Require PHP 7.4
	 *
	 * @return bool
	 */
	public function check_php_version()
	{
		return PHP_VERSION_ID >= 70400;
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
