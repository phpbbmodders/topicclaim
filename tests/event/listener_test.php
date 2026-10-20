<?php
/**
 *
 * Topic Claim extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\topicclaim\tests\event;

use phpbb\config\config;
use phpbb\event\data;
use phpbbmodders\topicclaim\claims;
use phpbbmodders\topicclaim\event\listener;

/**
 * Tests \phpbbmodders\topicclaim\event\listener
 */
class listener_test extends \phpbb_test_case
{
	/** @var config */
	protected $config;

	/** @var \PHPUnit\Framework\MockObject\MockObject|\phpbb\template\template */
	protected $template;

	/** @var \PHPUnit\Framework\MockObject\MockObject|claims */
	protected $claims;

	protected function setUp(): void
	{
		parent::setUp();

		$this->config = new config(['topicclaim_icon' => 'fa-eye', 'topicclaim_release_locked' => 0]);
		$this->template = $this->getMockBuilder('\phpbb\template\template')->getMock();
		$this->claims = $this->getMockBuilder(claims::class)->disableOriginalConstructor()->getMock();
	}

	/**
	 * @return listener
	 */
	protected function get_listener()
	{
		$helper = $this->getMockBuilder('\phpbb\controller\helper')->disableOriginalConstructor()->getMock();
		$language = $this->getMockBuilder('\phpbb\language\language')->disableOriginalConstructor()->getMock();
		$language->method('lang')->willReturnArgument(0);
		$request = $this->getMockBuilder('\phpbb\request\request_interface')->getMock();
		$user = $this->getMockBuilder('\phpbb\user')->disableOriginalConstructor()->getMock();

		return new listener($this->config, $helper, $language, $request, $this->template, $user, $this->claims);
	}

	public function test_construct()
	{
		$this->assertInstanceOf('\Symfony\Component\EventDispatcher\EventSubscriberInterface', $this->get_listener());
	}

	public function test_subscribed_events()
	{
		$events = array_keys(listener::getSubscribedEvents());

		foreach (['core.permissions', 'core.viewtopic_add_quickmod_option_before', 'core.modify_quickmod_options', 'core.delete_topics_before_query', 'core.delete_user_after', 'core.mcp_lock_unlock_after'] as $event)
		{
			$this->assertContains($event, $events);
		}
	}

	public function test_add_permissions()
	{
		$event = new data(['permissions' => []]);
		$this->get_listener()->add_permissions($event);

		$this->assertSame(['lang' => 'ACL_M_TOPICCLAIM', 'cat' => 'misc'], $event['permissions']['m_topicclaim']);
		$this->assertSame(['lang' => 'ACL_A_TOPICCLAIM', 'cat' => 'misc'], $event['permissions']['a_topicclaim']);
	}

	/**
	 * @return array
	 */
	public static function icon_data()
	{
		return [
			'chosen icon'			=> ['fa-flag', 'fa-flag'],
			'hyphenated icon'		=> ['fa-hand-paper-o', 'fa-hand-paper-o'],
			'not a class'			=> ['flag', 'fa-eye'],
			'markup refused'		=> ['fa-eye" onclick="x', 'fa-eye'],
			'empty'					=> ['', 'fa-eye'],
		];
	}

	/**
	 * @dataProvider icon_data
	 *
	 * @param string $setting
	 * @param string $expected
	 */
	public function test_assign_icon($setting, $expected)
	{
		$this->config['topicclaim_icon'] = $setting;
		$this->template->expects($this->once())->method('assign_var')->with('TOPICCLAIM_ICON', $expected);

		$this->get_listener()->assign_icon();
	}

	/**
	 * @return array
	 */
	public static function quickmod_data()
	{
		return [
			'not a moderator here'	=> [false, null, null],
			'unclaimed'				=> [true, null, 'TOPICCLAIM_QUICKMOD_CLAIM'],
			'claimed'				=> [true, ['user_id' => 3], 'TOPICCLAIM_QUICKMOD_MANAGE'],
		];
	}

	/**
	 * @dataProvider quickmod_data
	 *
	 * @param bool $can_view
	 * @param array|null $claim
	 * @param string|null $expected Language key of the entry, or null for none
	 */
	public function test_add_quickmod_option($can_view, $claim, $expected)
	{
		$this->claims->method('can_view')->with(5)->willReturn($can_view);
		$this->claims->method('get')->with(9)->willReturn($claim);

		$event = new data(['forum_id' => 5, 'topic_id' => 9, 'quickmod_array' => ['lock' => ['LOCK_TOPIC', true]]]);
		$this->get_listener()->add_quickmod_option($event);

		if ($expected === null)
		{
			$this->assertArrayNotHasKey(listener::QUICKMOD_ACTION, $event['quickmod_array']);
		}
		else
		{
			$this->assertSame([$expected, true], $event['quickmod_array'][listener::QUICKMOD_ACTION]);
		}
		$this->assertArrayHasKey('lock', $event['quickmod_array']);
	}

	public function test_other_quickmod_actions_are_left_alone()
	{
		$event = new data(['action' => 'lock', 'is_valid_action' => false]);
		$this->get_listener()->handle_quickmod_option($event);

		$this->assertFalse($event['is_valid_action']);
	}

	public function test_mcp_lock_releases_only_when_set()
	{
		$this->claims->expects($this->once())->method('release_automatically')->with([4, 7], 'LOG_TOPICCLAIM_RELEASE_LOCKED');
		$listener = $this->get_listener();

		// Off: nothing released
		$listener->release_on_mcp_lock(new data(['action' => 'lock', 'ids' => [1], 'data' => []]));

		$this->config['topicclaim_release_locked'] = 1;
		// Unlocking and post locks release nothing
		$listener->release_on_mcp_lock(new data(['action' => 'unlock', 'ids' => [1], 'data' => []]));
		$listener->release_on_mcp_lock(new data(['action' => 'lock_post', 'ids' => [1], 'data' => []]));
		$listener->release_on_mcp_lock(new data(['action' => 'lock', 'ids' => [4, 7], 'data' => []]));
	}

	public function test_deleted_topics_and_users_lose_their_claims()
	{
		$this->claims->expects($this->once())->method('delete_topics')->with([3, 8]);
		$this->claims->expects($this->once())->method('delete_users')->with([12]);
		$listener = $this->get_listener();

		$listener->delete_topic_claims(new data(['topic_ids' => [3, 8], 'table_ary' => []]));
		$listener->delete_user_claims(new data(['user_ids' => [12]]));
	}
}
