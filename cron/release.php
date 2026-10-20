<?php
/**
 *
 * Topic Claim extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\topicclaim\cron;

use phpbb\config\config;
use phpbbmodders\topicclaim\claims;

/**
 * Releases claims older than the board's limit, and claims on locked topics
 * that were locked in a way the listener does not see, once an hour
 */
class release extends \phpbb\cron\task\base
{
	/** Seconds between runs */
	const INTERVAL = 3600;

	/** @var config */
	protected $config;

	/** @var claims */
	protected $claims;

	/**
	 * @param config $config
	 * @param claims $claims
	 */
	public function __construct(config $config, claims $claims)
	{
		$this->config = $config;
		$this->claims = $claims;
	}

	/**
	 * Only when one of the release settings is on
	 *
	 * @return bool
	 */
	public function is_runnable()
	{
		return (int) $this->config['topicclaim_expire_days'] > 0 || !empty($this->config['topicclaim_release_locked']);
	}

	/**
	 * @return bool
	 */
	public function should_run()
	{
		return (int) $this->config['topicclaim_cron_last_run'] < time() - self::INTERVAL;
	}

	/**
	 * @return void
	 */
	public function run()
	{
		$this->config->set('topicclaim_cron_last_run', time(), false);

		if (!empty($this->config['topicclaim_release_locked']))
		{
			$this->claims->release_automatically($this->claims->find_locked(), 'LOG_TOPICCLAIM_RELEASE_LOCKED');
		}

		$days = (int) $this->config['topicclaim_expire_days'];
		if ($days > 0)
		{
			$this->claims->release_automatically($this->claims->find_expired($days), 'LOG_TOPICCLAIM_RELEASE_EXPIRED');
		}
	}
}
