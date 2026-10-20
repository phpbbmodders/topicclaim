<?php
/**
 *
 * Topic Claim extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\topicclaim\notification;

use phpbbmodders\topicclaim\claims;

/**
 * Tells a moderator that someone else changed their claim: transferred a
 * claim to them, took over or released their claim, or gave it to another
 * moderator
 */
class claim extends \phpbb\notification\type\base
{
	/** Language keys of the titles, by reason */
	const TITLES = [
		claims::REASON_TRANSFER	=> 'TOPICCLAIM_NOTIFICATION_TRANSFER',
		claims::REASON_TAKEOVER	=> 'TOPICCLAIM_NOTIFICATION_TAKEOVER',
		claims::REASON_RELEASE	=> 'TOPICCLAIM_NOTIFICATION_RELEASE',
		claims::REASON_REASSIGN	=> 'TOPICCLAIM_NOTIFICATION_REASSIGN',
	];

	/** Language keys of the email wording, by reason */
	const EMAIL_CHANGES = [
		claims::REASON_TRANSFER	=> 'TOPICCLAIM_EMAIL_TRANSFER',
		claims::REASON_TAKEOVER	=> 'TOPICCLAIM_EMAIL_TAKEOVER',
		claims::REASON_RELEASE	=> 'TOPICCLAIM_EMAIL_RELEASE',
		claims::REASON_REASSIGN	=> 'TOPICCLAIM_EMAIL_REASSIGN',
	];

	protected $notify_icon = 'notify_phpbb';
	protected $permission = 'm_topicclaim';

	public static $notification_option = [
		'lang'	=> 'TOPICCLAIM_NOTIFICATION_OPTION',
		'group'	=> 'NOTIFICATION_GROUP_MODERATION',
	];

	/** @var \phpbb\user_loader */
	protected $user_loader;

	/**
	 * Set the user loader (injected by the service definition, so the
	 * constructor stays phpBB's, which differs between 3.3 and 4.0)
	 *
	 * @param \phpbb\user_loader $user_loader
	 * @return void
	 */
	public function set_user_loader(\phpbb\user_loader $user_loader)
	{
		$this->user_loader = $user_loader;
	}

	public function get_type()
	{
		return 'phpbbmodders.topicclaim.notification.type.claim';
	}

	/**
	 * Offered in the UCP to users who may claim topics somewhere
	 *
	 * @return bool
	 */
	public function is_available()
	{
		return (bool) $this->auth->acl_getf_global($this->permission);
	}

	/**
	 * One item per change: the topic, who made it, who it is for, and when
	 *
	 * @param array $type_data
	 * @return int
	 */
	public static function get_item_id($type_data)
	{
		return (int) (crc32($type_data['topic_id'] . '|' . $type_data['actor_id'] . '|' . $type_data['recipient_id'] . '|' . $type_data['reason'] . '|' . $type_data['time']) & 0x7fffffff);
	}

	public static function get_item_parent_id($type_data)
	{
		return (int) $type_data['topic_id'];
	}

	/**
	 * The one moderator concerned, while they can still claim in the forum
	 *
	 * @param array $type_data
	 * @param array $options
	 * @return array
	 */
	public function find_users_for_notification($type_data, $options = [])
	{
		$recipient_id = (int) $type_data['recipient_id'];
		$users = $this->get_permitted_users([$recipient_id], (int) $type_data['forum_id']);
		if (!$users)
		{
			return [];
		}

		return $this->check_user_notification_options(array_keys($users), $options);
	}

	/**
	 * The recipients who have the permission in the forum, globally or there
	 *
	 * @param int[] $users
	 * @param int $forum_id
	 * @return array User IDs as keys
	 */
	protected function get_permitted_users(array $users, $forum_id)
	{
		$authorised = [];

		foreach ([$this->auth->acl_get_list($users, $this->permission, $forum_id), $this->auth->acl_get_list($users, $this->permission, false)] as $list)
		{
			foreach ($list as $users_by_option)
			{
				foreach ($users_by_option[$this->permission] ?? [] as $user_id)
				{
					$authorised[(int) $user_id] = true;
				}
			}
		}

		return $authorised;
	}

	public function users_to_query()
	{
		return [$this->get_data('actor_id')];
	}

	public function get_avatar()
	{
		return $this->user_loader->get_avatar($this->get_data('actor_id'), false, true);
	}

	public function get_title()
	{
		$key = self::TITLES[$this->get_data('reason')] ?? self::TITLES[claims::REASON_RELEASE];

		return $this->language->lang(
			$key,
			$this->user_loader->get_username($this->get_data('actor_id'), 'no_profile'),
			censor_text($this->get_data('topic_title'))
		);
	}

	public function get_url()
	{
		return append_sid($this->phpbb_root_path . 'viewtopic.' . $this->php_ext, 't=' . (int) $this->get_data('topic_id'));
	}

	/**
	 * Plain "&" in the URL: phpBB 4.0 redirects to it without decoding
	 *
	 * @return string
	 */
	public function get_redirect_url()
	{
		return append_sid($this->phpbb_root_path . 'viewtopic.' . $this->php_ext, 't=' . (int) $this->get_data('topic_id'), false);
	}

	public function get_email_template()
	{
		return '@phpbbmodders_topicclaim/topicclaim_notify';
	}

	public function get_email_template_variables()
	{
		$change = self::EMAIL_CHANGES[$this->get_data('reason')] ?? self::EMAIL_CHANGES[claims::REASON_RELEASE];

		return [
			'CLAIM_CHANGE'	=> $this->language->lang($change),
			'AUTHOR_NAME'	=> htmlspecialchars_decode($this->user_loader->get_username($this->get_data('actor_id'), 'username'), ENT_COMPAT),
			'TOPIC_TITLE'	=> htmlspecialchars_decode(censor_text($this->get_data('topic_title')), ENT_COMPAT),
			'U_TOPIC'		=> generate_board_url() . '/viewtopic.' . $this->php_ext . '?t=' . (int) $this->get_data('topic_id'),
		];
	}

	public function create_insert_array($type_data, $pre_create_data = [])
	{
		foreach (['topic_id', 'forum_id', 'topic_title', 'recipient_id', 'actor_id', 'reason', 'time'] as $key)
		{
			$this->set_data($key, $type_data[$key]);
		}

		parent::create_insert_array($type_data, $pre_create_data);
	}
}
