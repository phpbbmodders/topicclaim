<?php
/**
 *
 * Topic Claim extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\topicclaim\acp;

/**
 * ACP module information
 */
class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\phpbbmodders\topicclaim\acp\main_module',
			'title'		=> 'ACP_TOPICCLAIM',
			'modes'		=> [
				'settings'	=> [
					'title'	=> 'ACP_TOPICCLAIM_SETTINGS',
					'auth'	=> 'ext_phpbbmodders/topicclaim && acl_a_board',
					'cat'	=> ['ACP_TOPICCLAIM'],
				],
			],
		];
	}
}
