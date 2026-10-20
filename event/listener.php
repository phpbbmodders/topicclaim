<?php
/**
 *
 * Topic Claim extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\topicclaim\event;

use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\event\data;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;
use phpbbmodders\topicclaim\claims;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Topic Claim event listener
 *
 * Adds the permissions, the Quick-mod tools entry, the claim marks on topic
 * lists and topic pages, and removes or releases claims when their topic is
 * deleted or locked or their moderator is deleted.
 */
class listener implements EventSubscriberInterface
{
	/** Quick-mod tools action */
	const QUICKMOD_ACTION = 'topicclaim';

	/** Icon used when the setting is empty */
	const DEFAULT_ICON = 'fa-eye';

	/** @var config */
	protected $config;

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

	/**
	 * @param config $config
	 * @param helper $helper
	 * @param language $language
	 * @param request_interface $request
	 * @param template $template
	 * @param user $user
	 * @param claims $claims
	 */
	public function __construct(config $config, helper $helper, language $language, request_interface $request, template $template, user $user, claims $claims)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->claims = $claims;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'								=> 'load_language',
			'core.permissions'								=> 'add_permissions',
			'core.page_header'								=> 'assign_icon',
			'core.viewtopic_add_quickmod_option_before'		=> 'add_quickmod_option',
			'core.modify_quickmod_options'					=> 'handle_quickmod_option',
			'core.viewtopic_assign_template_vars_before'	=> 'assign_viewtopic_claim',
			'core.viewforum_modify_topics_data'				=> 'load_viewforum_claims',
			'core.viewforum_modify_topicrow'				=> 'add_topicrow_claim',
			'core.search_modify_rowset'						=> 'load_search_claims',
			'core.search_modify_tpl_ary'					=> 'add_search_claim',
			'core.mcp_view_forum_modify_topicrow'			=> 'add_mcp_topicrow_claim',
			'core.mcp_lock_unlock_after'					=> 'release_on_mcp_lock',
			'core.posting_modify_submit_post_after'			=> 'release_on_posting_lock',
			'core.delete_topics_before_query'				=> 'delete_topic_claims',
			'core.delete_forum_content_before_query'		=> 'delete_topic_claims',
			'core.delete_user_after'						=> 'delete_user_claims',
		];
	}

	/**
	 * Load the language file on every page: it holds the Quick-mod tools
	 * entry, log entries and notification texts
	 *
	 * @param data $event
	 * @return void
	 */
	public function load_language(data $event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name'	=> 'phpbbmodders/topicclaim',
			'lang_set'	=> 'topicclaim',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Add the permissions to ACP > Permissions
	 *
	 * @param data $event
	 * @return void
	 */
	public function add_permissions(data $event)
	{
		$this->language->add_lang('permissions_topicclaim', 'phpbbmodders/topicclaim');

		$permissions = $event['permissions'];
		$permissions['m_topicclaim'] = ['lang' => 'ACL_M_TOPICCLAIM', 'cat' => 'misc'];
		$permissions['a_topicclaim'] = ['lang' => 'ACL_A_TOPICCLAIM', 'cat' => 'misc'];
		$event['permissions'] = $permissions;
	}

	/**
	 * Give the templates the icon chosen in the ACP
	 *
	 * @return void
	 */
	public function assign_icon()
	{
		$this->template->assign_var('TOPICCLAIM_ICON', $this->get_icon());
	}

	/**
	 * Add "Claim topic" or "Topic claim" to the topic's Quick-mod tools
	 *
	 * @param data $event
	 * @return void
	 */
	public function add_quickmod_option(data $event)
	{
		if (!$this->claims->can_view($event['forum_id']))
		{
			return;
		}

		$claimed = $this->claims->get($event['topic_id']) !== null;

		$quickmod_array = $event['quickmod_array'];
		$quickmod_array[self::QUICKMOD_ACTION] = [$claimed ? 'TOPICCLAIM_QUICKMOD_MANAGE' : 'TOPICCLAIM_QUICKMOD_CLAIM', true];
		$event['quickmod_array'] = $quickmod_array;
	}

	/**
	 * Send the Quick-mod tools entry on to the topic's claim page
	 *
	 * @param data $event
	 * @return void
	 */
	public function handle_quickmod_option(data $event)
	{
		if ($event['action'] !== self::QUICKMOD_ACTION)
		{
			return;
		}

		$event['is_valid_action'] = true;

		redirect($this->helper->route('phpbbmodders_topicclaim_manage', ['topic_id' => $this->request->variable('t', 0)], false));
	}

	/**
	 * Show the claim below the topic title
	 *
	 * @param data $event
	 * @return void
	 */
	public function assign_viewtopic_claim(data $event)
	{
		if (!$this->claims->can_view($event['forum_id']))
		{
			return;
		}

		$claim = $this->claims->get($event['topic_id']);
		if ($claim === null)
		{
			return;
		}

		$this->template->assign_vars($this->get_claim_vars($claim, (int) $event['topic_id']));
	}

	/**
	 * Read the claims of a forum page's topics in one query
	 *
	 * @param data $event
	 * @return void
	 */
	public function load_viewforum_claims(data $event)
	{
		if ($event['topic_list'] && $this->claims->can_view($event['forum_id']))
		{
			$this->claims->get_for_topics($event['topic_list']);
		}
	}

	/**
	 * Mark a claimed topic in a forum's topic list
	 *
	 * @param data $event
	 * @return void
	 */
	public function add_topicrow_claim(data $event)
	{
		$event['topic_row'] = $this->add_row_claim($event['topic_row'], $event['row']);
	}

	/**
	 * Read the claims of a topic search's results in one query
	 *
	 * @param data $event
	 * @return void
	 */
	public function load_search_claims(data $event)
	{
		if ($event['show_results'] === 'topics' && $event['rowset'])
		{
			$this->claims->get_for_topics(array_column($event['rowset'], 'topic_id'));
		}
	}

	/**
	 * Mark a claimed topic in topic search results
	 *
	 * @param data $event
	 * @return void
	 */
	public function add_search_claim(data $event)
	{
		if ($event['show_results'] === 'topics')
		{
			$event['tpl_ary'] = $this->add_row_claim($event['tpl_ary'], $event['row']);
		}
	}

	/**
	 * Mark a claimed topic in the MCP's forum view
	 *
	 * @param data $event
	 * @return void
	 */
	public function add_mcp_topicrow_claim(data $event)
	{
		if (!empty($event['row']['forum_id']) && $this->claims->can_view($event['row']['forum_id']))
		{
			// One query for the whole page
			$this->claims->get_for_forum($event['row']['forum_id']);
		}

		$event['topic_row'] = $this->add_row_claim($event['topic_row'], $event['row']);
	}

	/**
	 * Release the claims on topics locked from the MCP, when the board says so
	 *
	 * @param data $event
	 * @return void
	 */
	public function release_on_mcp_lock(data $event)
	{
		if ($event['action'] === 'lock' && $this->config['topicclaim_release_locked'])
		{
			$this->claims->release_automatically($event['ids'], 'LOG_TOPICCLAIM_RELEASE_LOCKED');
		}
	}

	/**
	 * Release the claim on a topic locked from the posting form, when the
	 * board says so
	 *
	 * @param data $event
	 * @return void
	 */
	public function release_on_posting_lock(data $event)
	{
		$topic_id = (int) $event['topic_id'];

		if ($topic_id && $this->config['topicclaim_release_locked'] && $this->claims->get($topic_id) !== null)
		{
			$this->claims->release_automatically($this->claims->find_locked([$topic_id]), 'LOG_TOPICCLAIM_RELEASE_LOCKED');
		}
	}

	/**
	 * Remove the claims on deleted topics, including the topics of a deleted
	 * forum
	 *
	 * @param data $event
	 * @return void
	 */
	public function delete_topic_claims(data $event)
	{
		$this->claims->delete_topics($event['topic_ids']);
	}

	/**
	 * Remove the claims of deleted users
	 *
	 * @param data $event
	 * @return void
	 */
	public function delete_user_claims(data $event)
	{
		$this->claims->delete_users($event['user_ids']);
	}

	/**
	 * Add a topic's claim to its template row, for viewers who may see it
	 *
	 * @param array $topic_row Template row
	 * @param array $row Topic data, with topic_id and forum_id
	 * @return array
	 */
	protected function add_row_claim(array $topic_row, array $row)
	{
		if (empty($row['topic_id']) || empty($row['forum_id']) || !$this->claims->can_view($row['forum_id']))
		{
			return $topic_row;
		}

		$claim = $this->claims->get($row['topic_id']);
		if ($claim === null)
		{
			return $topic_row;
		}

		return array_merge($topic_row, $this->get_claim_vars($claim, (int) $row['topic_id']));
	}

	/**
	 * Template variables for a claim mark
	 *
	 * @param array $claim
	 * @param int $topic_id
	 * @return array
	 */
	protected function get_claim_vars(array $claim, $topic_id)
	{
		// Usernames are stored escaped; notes are plain text
		$time = $this->user->format_date($claim['claim_time']);
		$note = utf8_htmlspecialchars($claim['claim_note']);
		$title = $this->language->lang('TOPICCLAIM_CLAIMED_BY', $claim['username'], $time);
		if ($note !== '')
		{
			$title .= $this->language->lang('COLON') . ' ' . $note;
		}

		return [
			'S_TOPICCLAIM'			=> true,
			'S_TOPICCLAIM_OWN'		=> $this->claims->is_own($claim),
			'TOPICCLAIM_USER'		=> get_username_string('no_profile', $claim['user_id'], $claim['username'], $claim['user_colour']),
			'TOPICCLAIM_USER_FULL'	=> get_username_string('full', $claim['user_id'], $claim['username'], $claim['user_colour']),
			'TOPICCLAIM_TITLE'		=> $title,
			'TOPICCLAIM_TIME'		=> $time,
			'TOPICCLAIM_NOTE'		=> $note,
			'U_TOPICCLAIM'			=> $this->helper->route('phpbbmodders_topicclaim_manage', ['topic_id' => $topic_id]),
		];
	}

	/**
	 * The icon chosen in the ACP, or the default when it is not a valid
	 * Font Awesome class
	 *
	 * @return string
	 */
	protected function get_icon()
	{
		$icon = (string) $this->config['topicclaim_icon'];

		return preg_match('/\Afa-[a-z0-9]+(?:-[a-z0-9]+)*\z/', $icon) ? $icon : self::DEFAULT_ICON;
	}
}
