<?php
/**
 *
 * Topic Claim extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\topicclaim\controller;

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\controller\helper;
use phpbb\exception\http_exception;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;
use phpbbmodders\topicclaim\claims;

/**
 * The claim page of a topic: claim it, change the note, take it over,
 * transfer it or release it
 *
 * Reached from the topic's Quick-mod tools and from the MCP list of claims.
 */
class manage
{
	/** Form key name */
	const FORM_KEY = 'phpbbmodders_topicclaim';

	/** Seconds the result message shows before going back */
	const REDIRECT_DELAY = 3;

	/**
	 * Buttons that ask for confirmation first, with the title key confirm_box() uses.
	 * It adds _CONFIRM for the question: TOPICCLAIM_RELEASE_CONFIRM, TOPICCLAIM_TAKEOVER_CONFIRM.
	 */
	const CONFIRM_ACTIONS = [
		'release'	=> 'TOPICCLAIM_RELEASE',
		'takeover'	=> 'TOPICCLAIM_TAKEOVER',
	];

	/** @var auth */
	protected $auth;

	/** @var config */
	protected $config;

	/** @var driver_interface */
	protected $db;

	/** @var helper */
	protected $helper;

	/** @var language */
	protected $language;

	/** @var request_interface */
	protected $request;

	/** @var template */
	protected $template;

	/** @var user */
	protected $user;

	/** @var claims */
	protected $claims;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * @param auth $auth
	 * @param config $config
	 * @param driver_interface $db
	 * @param helper $helper
	 * @param language $language
	 * @param request_interface $request
	 * @param template $template
	 * @param user $user
	 * @param claims $claims
	 * @param string $root_path
	 * @param string $php_ext
	 */
	public function __construct(auth $auth, config $config, driver_interface $db, helper $helper, language $language, request_interface $request, template $template, user $user, claims $claims, $root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->helper = $helper;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->claims = $claims;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Show the claim page, or carry out the button pressed on it
	 *
	 * @param int $topic_id
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function handle($topic_id)
	{
		$this->language->add_lang('topicclaim', 'phpbbmodders/topicclaim');

		if (!$this->user->data['is_registered'])
		{
			login_box('', $this->language->lang('TOPICCLAIM_LOGIN'));
		}

		$topic = $this->get_topic((int) $topic_id);
		if (!$topic || !$this->claims->can_view($topic['forum_id']) || !$this->auth->acl_get('f_read', $topic['forum_id']))
		{
			throw new http_exception(403, 'NOT_AUTHORISED');
		}

		$claim = $this->claims->get($topic['topic_id']);
		$u_topic = append_sid("{$this->root_path}viewtopic.{$this->php_ext}", 't=' . $topic['topic_id']);
		$u_page = $this->helper->route('phpbbmodders_topicclaim_manage', ['topic_id' => $topic['topic_id']]);
		$u_return = $this->get_return_url($u_topic);
		$u_action = $u_page . ($u_return !== $u_topic ? (strpos($u_page, '?') === false ? '?' : '&amp;') . 'return=mcp' : '');

		add_form_key(self::FORM_KEY);

		$action = $this->get_action();
		if ($action !== '')
		{
			// "No" on the confirmation page: back to the claim page
			if ($this->request->is_set_post('cancel'))
			{
				redirect(str_replace('&amp;', '&', $u_action));
			}

			$needs_confirm = isset(self::CONFIRM_ACTIONS[$action]);
			$confirmed = $needs_confirm && confirm_box(true);

			// confirm_box() checks its own key; every other submit has the form token
			if (!$confirmed && !check_form_key(self::FORM_KEY))
			{
				trigger_error($this->language->lang('FORM_INVALID') . $this->return_links($u_topic, $u_return), E_USER_WARNING);
			}

			if ($needs_confirm && !$confirmed)
			{
				confirm_box(false, self::CONFIRM_ACTIONS[$action], build_hidden_fields([
					'topicclaim_action'	=> $action,
					// Already HTML-escaped by request_interface
					'topicclaim_note'	=> $this->request->variable('topicclaim_note', '', true),
				]), 'confirm_body.html', $u_action);
			}

			$message = $this->run_action($action, $topic, $claim);
			$this->finish($message, $u_topic, $u_return);
		}

		$this->assign_page($topic, $claim, $u_action, $u_topic, $u_return);

		return $this->helper->render('@phpbbmodders_topicclaim/topicclaim_manage.html', $this->language->lang('TOPICCLAIM_PAGE_TITLE'));
	}

	/**
	 * The button pressed, if any
	 *
	 * @return string claim, note, takeover, transfer, release or ''
	 */
	protected function get_action()
	{
		if (!$this->request->is_set_post('topicclaim_action'))
		{
			return '';
		}

		$action = $this->request->variable('topicclaim_action', '');

		return in_array($action, ['claim', 'note', 'takeover', 'transfer', 'release'], true) ? $action : '';
	}

	/**
	 * Carry out a button, after checking it is allowed for the claim as it is now
	 *
	 * @param string $action
	 * @param array $topic
	 * @param array|null $claim
	 * @return string Language key of the message to show
	 */
	protected function run_action($action, array $topic, $claim)
	{
		$note = $this->get_note();

		if ($action === 'claim')
		{
			if ($claim !== null)
			{
				return 'TOPICCLAIM_ALREADY_CLAIMED';
			}

			// The claim would only be released again
			if ($this->config['topicclaim_release_locked'] && $topic['topic_status'] === ITEM_LOCKED)
			{
				return 'TOPICCLAIM_LOCKED_NO_CLAIM';
			}

			return $this->claims->claim($topic, $note) ? 'TOPICCLAIM_CLAIMED' : 'TOPICCLAIM_ALREADY_CLAIMED';
		}

		// Every other button needs a claim
		if ($claim === null)
		{
			return 'TOPICCLAIM_NOT_CLAIMED';
		}

		// Take over is for another moderator's claim; the rest for the holder or an administrator
		$allowed = ($action === 'takeover') ? (!$this->claims->is_own($claim) && $this->claims->can_override()) : $this->claims->can_manage($claim, $topic['forum_id']);
		if (!$allowed)
		{
			return 'TOPICCLAIM_NOT_ALLOWED';
		}

		switch ($action)
		{
			case 'note':
				$this->claims->set_note($topic, $note);
				$message = 'TOPICCLAIM_NOTE_SAVED';
			break;

			case 'takeover':
				$this->claims->take_over($topic, $claim, $note);
				$message = 'TOPICCLAIM_TAKEN_OVER';
			break;

			case 'transfer':
				$user_id = $this->request->variable('topicclaim_user', 0);
				$moderators = $this->claims->get_moderators($topic['forum_id']);
				if (!isset($moderators[$user_id]) || $user_id === $claim['user_id'])
				{
					$message = 'TOPICCLAIM_TRANSFER_INVALID';
					break;
				}

				$this->claims->transfer($topic, $claim, $user_id, $moderators[$user_id]);
				$message = 'TOPICCLAIM_TRANSFERRED';
			break;

			case 'release':
				$this->claims->release($topic, $claim);
				$message = 'TOPICCLAIM_RELEASED';
			break;

			default:
				// get_action() only returns the buttons above
				$message = 'TOPICCLAIM_NOT_ALLOWED';
			break;
		}

		return $message;
	}

	/**
	 * Show the result of a button and go back to where the user came from
	 *
	 * @param string $message Language key
	 * @param string $u_topic
	 * @param string $u_return
	 * @return void
	 */
	protected function finish($message, $u_topic, $u_return)
	{
		$success = in_array($message, ['TOPICCLAIM_CLAIMED', 'TOPICCLAIM_NOTE_SAVED', 'TOPICCLAIM_TAKEN_OVER', 'TOPICCLAIM_TRANSFERRED', 'TOPICCLAIM_RELEASED'], true);

		if ($success)
		{
			meta_refresh(self::REDIRECT_DELAY, $u_return);
		}

		trigger_error($this->language->lang($message) . $this->return_links($u_topic, $u_return), $success ? E_USER_NOTICE : E_USER_WARNING);
	}

	/**
	 * Fill the claim page
	 *
	 * @param array $topic
	 * @param array|null $claim
	 * @param string $u_action Where the page's form posts to
	 * @param string $u_topic
	 * @param string $u_return
	 * @return void
	 */
	protected function assign_page(array $topic, $claim, $u_action, $u_topic, $u_return)
	{
		$can_manage = $claim !== null && $this->claims->can_manage($claim, $topic['forum_id']);
		$moderators = [];

		if ($can_manage)
		{
			$moderators = $this->claims->get_moderators($topic['forum_id']);
			unset($moderators[$claim['user_id']]);

			foreach ($moderators as $user_id => $username)
			{
				$this->template->assign_block_vars('topicclaim_moderators', [
					'USER_ID'	=> $user_id,
					'USERNAME'	=> $username,
				]);
			}
		}

		$this->template->assign_vars([
			'TOPICCLAIM_TOPIC_TITLE'	=> censor_text($topic['topic_title']),
			'TOPICCLAIM_FORUM_NAME'		=> $topic['forum_name'],
			'U_TOPICCLAIM_TOPIC'		=> $u_topic,
			'U_TOPICCLAIM_FORUM'		=> append_sid("{$this->root_path}viewforum.{$this->php_ext}", 'f=' . $topic['forum_id']),
			'U_TOPICCLAIM_ACTION'		=> $u_action,
			'U_TOPICCLAIM_RETURN'		=> $u_return,
			'S_TOPICCLAIM_RETURN_MCP'	=> $u_return !== $u_topic,

			'S_TOPICCLAIM_CLAIMED'		=> $claim !== null,
			'S_TOPICCLAIM_CAN_MANAGE'	=> $can_manage,
			'S_TOPICCLAIM_CAN_TAKEOVER'	=> $claim !== null && !$this->claims->is_own($claim) && $this->claims->can_override(),
			'S_TOPICCLAIM_CAN_TRANSFER'	=> $can_manage && !empty($moderators),
			'S_TOPICCLAIM_LOCKED'		=> (int) $topic['topic_status'] === ITEM_LOCKED,
			'S_TOPICCLAIM_RELEASE_LOCKED'	=> !empty($this->config['topicclaim_release_locked']),
			'TOPICCLAIM_EXPIRE_DAYS'	=> (int) $this->config['topicclaim_expire_days'],

			'TOPICCLAIM_USER'			=> $claim !== null ? get_username_string('full', $claim['user_id'], $claim['username'], $claim['user_colour']) : '',
			'TOPICCLAIM_TIME'			=> $claim !== null ? $this->user->format_date($claim['claim_time']) : '',
			'TOPICCLAIM_NOTE'			=> $claim !== null ? utf8_htmlspecialchars($claim['claim_note']) : '',
			'TOPICCLAIM_NOTE_MAX'		=> claims::NOTE_MAX_LENGTH,
		]);

		$this->template->assign_block_vars('navlinks', [
			'FORUM_NAME'	=> $topic['forum_name'],
			'U_VIEW_FORUM'	=> append_sid("{$this->root_path}viewforum.{$this->php_ext}", 'f=' . $topic['forum_id']),
		]);
	}

	/**
	 * The note typed on the page, as plain text
	 *
	 * @return string
	 */
	protected function get_note()
	{
		// request_interface escapes HTML; claims stores plain text
		return htmlspecialchars_decode($this->request->variable('topicclaim_note', '', true), ENT_COMPAT);
	}

	/**
	 * Where to go after a button: the MCP list when the page was opened from
	 * it, otherwise the topic
	 *
	 * @param string $u_topic
	 * @return string
	 */
	protected function get_return_url($u_topic)
	{
		if ($this->request->variable('return', '') === 'mcp')
		{
			return append_sid("{$this->root_path}mcp.{$this->php_ext}", 'i=-phpbbmodders-topicclaim-mcp-main_module&amp;mode=claims');
		}

		return $u_topic;
	}

	/**
	 * Links back to the topic and, when the page was opened from it, the MCP list
	 *
	 * @param string $u_topic
	 * @param string $u_return
	 * @return string HTML
	 */
	protected function return_links($u_topic, $u_return)
	{
		$links = '<br /><br />' . $this->language->lang('RETURN_TOPIC', '<a href="' . $u_topic . '">', '</a>');

		if ($u_return !== $u_topic)
		{
			$links .= '<br /><br />' . $this->language->lang('TOPICCLAIM_RETURN_LIST', '<a href="' . $u_return . '">', '</a>');
		}

		return $links;
	}

	/**
	 * A topic with its forum's name
	 *
	 * @param int $topic_id
	 * @return array|null
	 */
	protected function get_topic($topic_id)
	{
		$sql = 'SELECT t.topic_id, t.forum_id, t.topic_title, t.topic_status, f.forum_name
			FROM ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . ' f
			WHERE t.topic_id = ' . (int) $topic_id . '
				AND f.forum_id = t.forum_id
				AND t.topic_moved_id = 0';
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return null;
		}

		$row['topic_id'] = (int) $row['topic_id'];
		$row['forum_id'] = (int) $row['forum_id'];
		$row['topic_status'] = (int) $row['topic_status'];

		return $row;
	}
}
