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

use phpbb\auth\auth;
use phpbb\db\driver\driver_interface;
use phpbb\log\log_interface;
use phpbb\notification\manager;
use phpbb\user;

/**
 * Reads and changes topic claims, checks who may see and change them, and
 * logs and notifies each change
 *
 * Notes are stored as plain text, not HTML-escaped; escape them for output.
 */
class claims
{
	/** Longest note, in characters; the column holds 255 */
	const NOTE_MAX_LENGTH = 255;

	/** Notification reasons */
	const REASON_TRANSFER = 'transfer';
	const REASON_TAKEOVER = 'takeover';
	const REASON_RELEASE = 'release';
	const REASON_REASSIGN = 'reassign';

	/** @var auth */
	protected $auth;

	/** @var driver_interface */
	protected $db;

	/** @var log_interface */
	protected $log;

	/** @var manager */
	protected $notification_manager;

	/** @var user */
	protected $user;

	/** @var string */
	protected $claims_table;

	/** @var array Claims already read, by topic ID (false when unclaimed) */
	protected $cache = [];

	/** @var array Forums whose claims are all in the cache */
	protected $cached_forums = [];

	/**
	 * @param auth $auth
	 * @param driver_interface $db
	 * @param log_interface $log
	 * @param manager $notification_manager
	 * @param user $user
	 * @param string $claims_table
	 */
	public function __construct(auth $auth, driver_interface $db, log_interface $log, manager $notification_manager, user $user, $claims_table)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->log = $log;
		$this->notification_manager = $notification_manager;
		$this->user = $user;
		$this->claims_table = $claims_table;
	}

	/**
	 * Whether the current user may see claims in a forum and claim its topics
	 *
	 * @param int $forum_id
	 * @return bool
	 */
	public function can_view($forum_id)
	{
		return $this->user->data['is_registered'] && (bool) $this->auth->acl_get('m_topicclaim', (int) $forum_id);
	}

	/**
	 * Whether the current user may take over, transfer and release other
	 * moderators' claims
	 *
	 * @return bool
	 */
	public function can_override()
	{
		return (bool) $this->auth->acl_get('a_topicclaim');
	}

	/**
	 * Whether the current user may transfer, release or change a claim
	 *
	 * @param array $claim
	 * @param int $forum_id
	 * @return bool
	 */
	public function can_manage(array $claim, $forum_id)
	{
		return $this->can_view($forum_id) && ($this->is_own($claim) || $this->can_override());
	}

	/**
	 * Whether a claim belongs to the current user
	 *
	 * @param array $claim
	 * @return bool
	 */
	public function is_own(array $claim)
	{
		return (int) $claim['user_id'] === (int) $this->user->data['user_id'];
	}

	/**
	 * The claim on a topic
	 *
	 * @param int $topic_id
	 * @return array|null Claim with the claimant's username and colour, or
	 *                    null when the topic is not claimed
	 */
	public function get($topic_id)
	{
		$claims = $this->get_for_topics([(int) $topic_id]);

		return $claims[(int) $topic_id] ?? null;
	}

	/**
	 * The claims on some topics
	 *
	 * @param int[] $topic_ids
	 * @return array Claims by topic ID; unclaimed topics are left out
	 */
	public function get_for_topics(array $topic_ids)
	{
		$topic_ids = array_unique(array_map('intval', $topic_ids));
		$missing = array_values(array_diff($topic_ids, array_keys($this->cache)));

		if ($missing)
		{
			foreach ($missing as $topic_id)
			{
				$this->cache[$topic_id] = false;
			}

			$this->load('c.' . $this->db->sql_in_set('topic_id', $missing));
		}

		$claims = [];
		foreach ($topic_ids as $topic_id)
		{
			if (!empty($this->cache[$topic_id]))
			{
				$claims[$topic_id] = $this->cache[$topic_id];
			}
		}

		return $claims;
	}

	/**
	 * The claims on a forum's topics
	 *
	 * @param int $forum_id
	 * @return array Claims by topic ID
	 */
	public function get_for_forum($forum_id)
	{
		$forum_id = (int) $forum_id;

		if (!isset($this->cached_forums[$forum_id]))
		{
			$this->cached_forums[$forum_id] = $this->load('t.forum_id = ' . $forum_id, true);
		}

		return $this->cached_forums[$forum_id];
	}

	/**
	 * Read claims into the cache
	 *
	 * @param string $where SQL condition on c (claims) and t (topics)
	 * @param bool $join_topics Whether the condition needs the topics table
	 * @return array The claims read, by topic ID
	 */
	protected function load($where, $join_topics = false)
	{
		$sql_ary = [
			'SELECT'	=> 'c.topic_id, c.user_id, c.claim_time, c.claim_note, u.username, u.user_colour',
			'FROM'		=> [$this->claims_table => 'c'],
			'LEFT_JOIN'	=> [
				[
					'FROM'	=> [USERS_TABLE => 'u'],
					'ON'	=> 'u.user_id = c.user_id',
				],
			],
			'WHERE'		=> $where,
		];

		if ($join_topics)
		{
			$sql_ary['FROM'][TOPICS_TABLE] = 't';
			$sql_ary['WHERE'] = 't.topic_id = c.topic_id AND ' . $where;
		}

		$result = $this->db->sql_query($this->db->sql_build_query('SELECT', $sql_ary));

		$claims = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$row = $this->cast($row);
			$claims[$row['topic_id']] = $this->cache[$row['topic_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $claims;
	}

	/**
	 * Claims for the MCP list, newest first
	 *
	 * @param int[] $forum_ids Forums to include
	 * @param int $user_id Only this moderator's claims, or 0 for everyone's
	 * @param int $start
	 * @param int $limit
	 * @return array [rows, total]; each row is a claim with its topic and
	 *               forum
	 */
	public function get_list(array $forum_ids, $user_id, $start, $limit)
	{
		if (!$forum_ids)
		{
			return [[], 0];
		}

		$where = 't.topic_id = c.topic_id
			AND f.forum_id = t.forum_id
			AND ' . $this->db->sql_in_set('t.forum_id', array_map('intval', $forum_ids));
		if ($user_id)
		{
			$where .= ' AND c.user_id = ' . (int) $user_id;
		}

		$sql = 'SELECT COUNT(c.topic_id) AS total
			FROM ' . $this->claims_table . ' c, ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . ' f
			WHERE ' . $where;
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		$sql_ary = [
			'SELECT'	=> 'c.topic_id, c.user_id, c.claim_time, c.claim_note, u.username, u.user_colour,
				t.forum_id, t.topic_title, t.topic_status, f.forum_name',
			'FROM'		=> [
				$this->claims_table	=> 'c',
				TOPICS_TABLE		=> 't',
				FORUMS_TABLE		=> 'f',
			],
			'LEFT_JOIN'	=> [
				[
					'FROM'	=> [USERS_TABLE => 'u'],
					'ON'	=> 'u.user_id = c.user_id',
				],
			],
			'WHERE'		=> $where,
			'ORDER_BY'	=> 'c.claim_time DESC, c.topic_id DESC',
		];
		$result = $this->db->sql_query_limit($this->db->sql_build_query('SELECT', $sql_ary), (int) $limit, (int) $start);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $this->cast($row);
		}
		$this->db->sql_freeresult($result);

		return [$rows, $total];
	}

	/**
	 * The users who could hold a claim on a forum's topics: active users with
	 * the claim permission there, globally or in that forum
	 *
	 * @param int $forum_id
	 * @return array Usernames by user ID, sorted by name
	 */
	public function get_moderators($forum_id)
	{
		$forum_id = (int) $forum_id;
		$user_ids = [];

		foreach ([$this->auth->acl_get_list(false, 'm_topicclaim', $forum_id), $this->auth->acl_get_list(false, 'm_topicclaim', false)] as $list)
		{
			foreach ($list as $users_by_option)
			{
				if (!empty($users_by_option['m_topicclaim']))
				{
					$user_ids = array_merge($user_ids, $users_by_option['m_topicclaim']);
				}
			}
		}

		$user_ids = array_unique(array_map('intval', $user_ids));
		if (!$user_ids)
		{
			return [];
		}

		$sql = 'SELECT user_id, username
			FROM ' . USERS_TABLE . '
			WHERE ' . $this->db->sql_in_set('user_id', $user_ids) . '
				AND ' . $this->db->sql_in_set('user_type', [USER_NORMAL, USER_FOUNDER]) . '
			ORDER BY username_clean';
		$result = $this->db->sql_query($sql);

		$moderators = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$moderators[(int) $row['user_id']] = $row['username'];
		}
		$this->db->sql_freeresult($result);

		return $moderators;
	}

	/**
	 * Claim an unclaimed topic for the current user
	 *
	 * @param array $topic topic_id, forum_id, topic_title
	 * @param string $note
	 * @return bool False when someone claimed it first
	 */
	public function claim(array $topic, $note)
	{
		$topic_id = (int) $topic['topic_id'];

		// The primary key refuses a second claim, so a race leaves the first
		$this->db->sql_return_on_error(true);
		$inserted = $this->db->sql_query('INSERT INTO ' . $this->claims_table . ' ' . $this->db->sql_build_array('INSERT', [
			'topic_id'		=> $topic_id,
			'user_id'		=> (int) $this->user->data['user_id'],
			'claim_time'	=> time(),
			'claim_note'	=> $this->clean_note($note),
		]));
		$this->db->sql_return_on_error(false);

		unset($this->cache[$topic_id]);
		$this->cached_forums = [];
		if ($inserted === false)
		{
			return false;
		}

		$this->add_log($topic, 'LOG_TOPICCLAIM_CLAIM');

		return true;
	}

	/**
	 * Change a claim's note
	 *
	 * @param array $topic
	 * @param string $note
	 * @return void
	 */
	public function set_note(array $topic, $note)
	{
		$this->update($topic, ['claim_note' => $this->clean_note($note)]);
	}

	/**
	 * Give another moderator's claim to the current user
	 *
	 * @param array $topic
	 * @param array $claim The claim being taken over
	 * @param string $note
	 * @return void
	 */
	public function take_over(array $topic, array $claim, $note)
	{
		$this->update($topic, [
			'user_id'		=> (int) $this->user->data['user_id'],
			'claim_time'	=> time(),
			'claim_note'	=> $this->clean_note($note),
		]);

		$this->add_log($topic, 'LOG_TOPICCLAIM_TAKEOVER', [$claim['username']]);
		$this->notify($topic, (int) $claim['user_id'], self::REASON_TAKEOVER);
	}

	/**
	 * Give a claim to another moderator
	 *
	 * @param array $topic
	 * @param array $claim The claim being transferred
	 * @param int $user_id
	 * @param string $username
	 * @return void
	 */
	public function transfer(array $topic, array $claim, $user_id, $username)
	{
		$this->update($topic, [
			'user_id'		=> (int) $user_id,
			'claim_time'	=> time(),
		]);

		$this->add_log($topic, 'LOG_TOPICCLAIM_TRANSFER', [$claim['username'], $username]);
		$this->notify($topic, (int) $user_id, self::REASON_TRANSFER);

		if (!$this->is_own($claim))
		{
			$this->notify($topic, (int) $claim['user_id'], self::REASON_REASSIGN);
		}
	}

	/**
	 * Release a claim
	 *
	 * @param array $topic
	 * @param array $claim The claim being released
	 * @return void
	 */
	public function release(array $topic, array $claim)
	{
		$this->delete_topics([(int) $topic['topic_id']]);

		$this->add_log($topic, 'LOG_TOPICCLAIM_RELEASE', [$claim['username']]);

		if (!$this->is_own($claim))
		{
			$this->notify($topic, (int) $claim['user_id'], self::REASON_RELEASE);
		}
	}

	/**
	 * Release claims on their own, logging each one
	 *
	 * @param int[] $topic_ids
	 * @param string $log_operation LOG_TOPICCLAIM_RELEASE_LOCKED or
	 *                              LOG_TOPICCLAIM_RELEASE_EXPIRED
	 * @return int How many claims were released
	 */
	public function release_automatically(array $topic_ids, $log_operation)
	{
		$topic_ids = array_unique(array_map('intval', $topic_ids));
		if (!$topic_ids)
		{
			return 0;
		}

		$sql = 'SELECT c.topic_id, u.username, t.forum_id, t.topic_title
			FROM ' . $this->claims_table . ' c
			LEFT JOIN ' . USERS_TABLE . ' u
				ON u.user_id = c.user_id
			LEFT JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_id = c.topic_id
			WHERE ' . $this->db->sql_in_set('c.topic_id', $topic_ids);
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		if (!$rows)
		{
			return 0;
		}

		$this->delete_topics(array_column($rows, 'topic_id'));

		foreach ($rows as $row)
		{
			$this->add_log([
				'topic_id'		=> (int) $row['topic_id'],
				'forum_id'		=> (int) $row['forum_id'],
				'topic_title'	=> (string) $row['topic_title'],
			], $log_operation, [(string) $row['username']]);
		}

		return count($rows);
	}

	/**
	 * Topics whose claims are older than a number of days
	 *
	 * @param int $days
	 * @return int[]
	 */
	public function find_expired($days)
	{
		$sql = 'SELECT topic_id
			FROM ' . $this->claims_table . '
			WHERE claim_time < ' . (time() - (int) $days * 86400);

		return $this->fetch_topic_ids($sql);
	}

	/**
	 * Claimed topics that are locked
	 *
	 * @param int[] $topic_ids Only check these topics, or [] for all claims
	 * @return int[]
	 */
	public function find_locked(array $topic_ids = [])
	{
		$sql = 'SELECT c.topic_id
			FROM ' . $this->claims_table . ' c, ' . TOPICS_TABLE . ' t
			WHERE t.topic_id = c.topic_id
				AND t.topic_status = ' . ITEM_LOCKED .
				($topic_ids ? ' AND ' . $this->db->sql_in_set('c.topic_id', array_map('intval', $topic_ids)) : '');

		return $this->fetch_topic_ids($sql);
	}

	/**
	 * Remove the claims on topics, without logging (the topics are gone)
	 *
	 * @param int[] $topic_ids
	 * @return void
	 */
	public function delete_topics(array $topic_ids)
	{
		$topic_ids = array_map('intval', $topic_ids);
		if (!$topic_ids)
		{
			return;
		}

		$this->db->sql_query('DELETE FROM ' . $this->claims_table . '
			WHERE ' . $this->db->sql_in_set('topic_id', $topic_ids));

		foreach ($topic_ids as $topic_id)
		{
			unset($this->cache[$topic_id]);
		}
		$this->cached_forums = [];
	}

	/**
	 * Remove the claims held by users (the users are gone)
	 *
	 * @param int[] $user_ids
	 * @return void
	 */
	public function delete_users(array $user_ids)
	{
		$user_ids = array_map('intval', $user_ids);
		if (!$user_ids)
		{
			return;
		}

		$this->db->sql_query('DELETE FROM ' . $this->claims_table . '
			WHERE ' . $this->db->sql_in_set('user_id', $user_ids));

		$this->cache = $this->cached_forums = [];
	}

	/**
	 * Trim a note and cut it to the column's length
	 *
	 * @param string $note Plain text
	 * @return string
	 */
	public function clean_note($note)
	{
		$note = trim(preg_replace('/\s+/u', ' ', (string) $note));

		return utf8_substr($note, 0, self::NOTE_MAX_LENGTH);
	}

	/**
	 * Change a claim's columns
	 *
	 * @param array $topic
	 * @param array $data
	 * @return void
	 */
	protected function update(array $topic, array $data)
	{
		$topic_id = (int) $topic['topic_id'];

		$this->db->sql_query('UPDATE ' . $this->claims_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $data) . '
			WHERE topic_id = ' . $topic_id);

		unset($this->cache[$topic_id]);
		$this->cached_forums = [];
	}

	/**
	 * Add an entry to the moderator log
	 *
	 * @param array $topic
	 * @param string $operation
	 * @param array $data Log parameters after the topic title
	 * @return void
	 */
	protected function add_log(array $topic, $operation, array $data = [])
	{
		$this->log->add('mod', (int) $this->user->data['user_id'], (string) $this->user->ip, $operation, false, array_merge([
			'forum_id'	=> (int) $topic['forum_id'],
			'topic_id'	=> (int) $topic['topic_id'],
			$topic['topic_title'],
		], $data));
	}

	/**
	 * Tell a moderator about a change someone else made to a claim
	 *
	 * @param array $topic
	 * @param int $user_id Who to tell
	 * @param string $reason One of the REASON_ constants
	 * @return void
	 */
	protected function notify(array $topic, $user_id, $reason)
	{
		if (!$user_id || $user_id === (int) $this->user->data['user_id'])
		{
			return;
		}

		$this->notification_manager->add_notifications(ext::NOTIFICATION_TYPE, [
			'topic_id'		=> (int) $topic['topic_id'],
			'forum_id'		=> (int) $topic['forum_id'],
			'topic_title'	=> (string) $topic['topic_title'],
			'recipient_id'	=> $user_id,
			'actor_id'		=> (int) $this->user->data['user_id'],
			'reason'		=> $reason,
			'time'			=> time(),
		]);
	}

	/**
	 * @param string $sql Query selecting topic_id
	 * @return int[]
	 */
	protected function fetch_topic_ids($sql)
	{
		$result = $this->db->sql_query($sql);

		$topic_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topic_ids[] = (int) $row['topic_id'];
		}
		$this->db->sql_freeresult($result);

		return $topic_ids;
	}

	/**
	 * Cast a claim row's numbers
	 *
	 * @param array $row
	 * @return array
	 */
	protected function cast(array $row)
	{
		foreach (['topic_id', 'user_id', 'claim_time', 'forum_id', 'topic_status'] as $key)
		{
			if (isset($row[$key]))
			{
				$row[$key] = (int) $row[$key];
			}
		}
		$row['username'] = (string) $row['username'];
		$row['user_colour'] = (string) $row['user_colour'];

		return $row;
	}
}
