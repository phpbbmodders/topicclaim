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
 * MCP list of claimed topics in the forums the moderator may claim in, with
 * links to each claim's page to transfer or release it
 */
class main_module
{
	/** Claims per page */
	const PER_PAGE = 25;

	public $u_action;
	public $tpl_name;
	public $page_title;

	/**
	 * @param string $id	Module ID
	 * @param string $mode	Module mode
	 */
	public function main($id, $mode)
	{
		global $auth, $config, $language, $phpbb_container, $phpbb_root_path, $phpEx, $request, $template, $user;

		/** @var \phpbbmodders\topicclaim\claims $claims */
		$claims = $phpbb_container->get('phpbbmodders.topicclaim.claims');
		/** @var \phpbb\controller\helper $helper */
		$helper = $phpbb_container->get('controller.helper');
		/** @var \phpbb\pagination $pagination */
		$pagination = $phpbb_container->get('pagination');

		$language->add_lang('topicclaim', 'phpbbmodders/topicclaim');
		$this->tpl_name = '@phpbbmodders_topicclaim/mcp_topicclaim';
		$this->page_title = 'MCP_TOPICCLAIM_CLAIMS';

		$show = $request->variable('show', 'all') === 'mine' ? 'mine' : 'all';
		$start = max(0, $request->variable('start', 0));

		// Only forums the moderator may both claim in and read
		$forum_ids = array_values(array_intersect(
			array_keys($auth->acl_getf('m_topicclaim', true)),
			array_keys($auth->acl_getf('f_read', true))
		));

		$user_id = ($show === 'mine') ? (int) $user->data['user_id'] : 0;
		list($rows, $total) = $claims->get_list($forum_ids, $user_id, $start, self::PER_PAGE);

		// A page past the end (claims released since) goes to the last page
		$valid_start = $pagination->validate_start($start, self::PER_PAGE, $total);
		if ($valid_start !== $start)
		{
			$start = $valid_start;
			list($rows, $total) = $claims->get_list($forum_ids, $user_id, $start, self::PER_PAGE);
		}

		foreach ($rows as $row)
		{
			$u_manage = $helper->route('phpbbmodders_topicclaim_manage', ['topic_id' => $row['topic_id']]);

			$template->assign_block_vars('topicclaim', [
				'TOPIC_TITLE'	=> censor_text($row['topic_title']),
				'FORUM_NAME'	=> $row['forum_name'],
				'USER'			=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
				'TIME'			=> $user->format_date($row['claim_time']),
				'NOTE'			=> utf8_htmlspecialchars($row['claim_note']),
				'U_TOPIC'		=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", 't=' . $row['topic_id']),
				'U_FORUM'		=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $row['forum_id']),
				'U_MANAGE'		=> $u_manage . (strpos($u_manage, '?') === false ? '?' : '&amp;') . 'return=mcp',
				'S_CAN_MANAGE'	=> $claims->can_manage($row, $row['forum_id']),
				'S_LOCKED'		=> $row['topic_status'] === ITEM_LOCKED,
			]);
		}

		$base_url = $this->u_action . '&amp;show=' . $show;
		$pagination->generate_template_pagination($base_url, 'pagination', 'start', $total, self::PER_PAGE, $start);

		$template->assign_vars([
			'TOPICCLAIM_TOTAL'		=> $language->lang('TOPICCLAIM_TOTAL', $total),
			'S_TOPICCLAIM_MINE'		=> $show === 'mine',
			'U_TOPICCLAIM_ALL'		=> $this->u_action . '&amp;show=all',
			'U_TOPICCLAIM_MINE'		=> $this->u_action . '&amp;show=mine',
			'S_TOPICCLAIM_EXPIRE'	=> (int) $config['topicclaim_expire_days'] > 0,
			'TOPICCLAIM_EXPIRE'		=> $language->lang('TOPICCLAIM_EXPIRE_NOTICE', (int) $config['topicclaim_expire_days']),
		]);
	}
}
