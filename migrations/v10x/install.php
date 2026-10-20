<?php
/**
 *
 * Topic Claim extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\topicclaim\migrations\v10x;

/**
 * Adds the claims table, the permissions, the settings, and the ACP and MCP
 * pages
 */
class install extends \phpbb\db\migration\migration
{
	/**
	 * {@inheritdoc}
	 */
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'topicclaim_claims');
	}

	/**
	 * {@inheritdoc}
	 */
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	/**
	 * One claim per topic: who holds it, since when, and an optional note
	 *
	 * {@inheritdoc}
	 */
	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'topicclaim_claims' => [
					'COLUMNS' => [
						'topic_id'		=> ['UINT', 0],
						'user_id'		=> ['UINT', 0],
						'claim_time'	=> ['TIMESTAMP', 0],
						'claim_note'	=> ['VCHAR_UNI', ''],
					],
					'PRIMARY_KEY'	=> 'topic_id',
					'KEYS'			=> [
						'user_id'		=> ['INDEX', 'user_id'],
						'claim_time'	=> ['INDEX', 'claim_time'],
					],
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'topicclaim_claims',
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_data()
	{
		return [
			// Release claims when their topic is locked (0 no, 1 yes)
			['config.add', ['topicclaim_release_locked', 0]],
			// Release claims older than this many days (0 never)
			['config.add', ['topicclaim_expire_days', 0]],
			// Font Awesome icon marking claimed topics
			['config.add', ['topicclaim_icon', 'fa-eye']],
			// When the release cron task last ran
			['config.add', ['topicclaim_cron_last_run', 0, true]],

			// Claim topics and see claims: global and per forum, like phpBB's
			// other moderator permissions
			['permission.add', ['m_topicclaim', true]],
			['permission.add', ['m_topicclaim', false]],
			// Take over, transfer and release other moderators' claims
			['permission.add', ['a_topicclaim', true]],

			['if', [
				['permission.role_exists', ['ROLE_MOD_FULL']],
				['permission.permission_set', ['ROLE_MOD_FULL', 'm_topicclaim']],
			]],
			['if', [
				['permission.role_exists', ['ROLE_MOD_STANDARD']],
				['permission.permission_set', ['ROLE_MOD_STANDARD', 'm_topicclaim']],
			]],
			['if', [
				['permission.role_exists', ['ROLE_ADMIN_FULL']],
				['permission.permission_set', ['ROLE_ADMIN_FULL', 'a_topicclaim']],
			]],
			['if', [
				['permission.role_exists', ['ROLE_ADMIN_STANDARD']],
				['permission.permission_set', ['ROLE_ADMIN_STANDARD', 'a_topicclaim']],
			]],

			['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_TOPICCLAIM']],
			['module.add', ['acp', 'ACP_TOPICCLAIM', [
				'module_basename'	=> '\phpbbmodders\topicclaim\acp\main_module',
				'modes'				=> ['settings'],
			]]],

			['module.add', ['mcp', 0, 'MCP_TOPICCLAIM']],
			['module.add', ['mcp', 'MCP_TOPICCLAIM', [
				'module_basename'	=> '\phpbbmodders\topicclaim\mcp\main_module',
				'modes'				=> ['claims'],
			]]],
		];
	}
}
