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
 * ACP settings page: when claims are released on their own, and the icon
 * that marks claimed topics
 */
class main_module
{
	/** Icon used when the setting has never been saved */
	const DEFAULT_ICON = 'fa-eye';

	/** Longest icon class accepted; the form field has the same limit */
	const MAX_ICON_LENGTH = 64;

	/** Longest claim age accepted, in days */
	const MAX_EXPIRE_DAYS = 3650;

	public $u_action;
	public $tpl_name;
	public $page_title;

	/**
	 * @param string $id	Module ID
	 * @param string $mode	Module mode
	 */
	public function main($id, $mode)
	{
		global $config, $language, $request, $template, $user, $phpbb_log;

		$language->add_lang('acp_topicclaim', 'phpbbmodders/topicclaim');
		$this->tpl_name = 'acp_topicclaim_settings';
		$this->page_title = 'ACP_TOPICCLAIM_SETTINGS';

		add_form_key('phpbbmodders_topicclaim_acp');

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key('phpbbmodders_topicclaim_acp'))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$icon = trim($request->variable('topicclaim_icon', self::DEFAULT_ICON));
			if (strlen($icon) > self::MAX_ICON_LENGTH || !preg_match('/\Afa-[a-z0-9]+(?:-[a-z0-9]+)*\z/', $icon))
			{
				trigger_error($language->lang('TOPICCLAIM_ICON_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$expire_days = $request->variable('topicclaim_expire_days', 0);
			if ($expire_days < 0 || $expire_days > self::MAX_EXPIRE_DAYS)
			{
				trigger_error($language->lang('TOPICCLAIM_EXPIRE_DAYS_INVALID', self::MAX_EXPIRE_DAYS) . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$config->set('topicclaim_release_locked', (int) $request->variable('topicclaim_release_locked', false));
			$config->set('topicclaim_expire_days', $expire_days);
			$config->set('topicclaim_icon', $icon);

			$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_TOPICCLAIM_SETTINGS');
			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'U_ACTION'						=> $this->u_action,
			'S_TOPICCLAIM_RELEASE_LOCKED'	=> !empty($config['topicclaim_release_locked']),
			'TOPICCLAIM_EXPIRE_DAYS'		=> (int) $config['topicclaim_expire_days'],
			'TOPICCLAIM_EXPIRE_DAYS_MAX'	=> self::MAX_EXPIRE_DAYS,
			'TOPICCLAIM_ICON'				=> !empty($config['topicclaim_icon']) ? $config['topicclaim_icon'] : self::DEFAULT_ICON,
			'TOPICCLAIM_ICON_MAX_LENGTH'	=> self::MAX_ICON_LENGTH,
		]);
	}
}
