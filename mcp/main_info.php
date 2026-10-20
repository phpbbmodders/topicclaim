<?php
/**
 *
 * Topic Claim extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\topicclaim\mcp;

/**
 * MCP module information
 */
class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\phpbbmodders\topicclaim\mcp\main_module',
			'title'		=> 'MCP_TOPICCLAIM',
			'modes'		=> [
				'claims'	=> [
					'title'	=> 'MCP_TOPICCLAIM_CLAIMS',
					'auth'	=> 'ext_phpbbmodders/topicclaim && aclf_m_topicclaim',
					'cat'	=> ['MCP_TOPICCLAIM'],
				],
			],
		];
	}
}
