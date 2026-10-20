<?php
/**
 *
 * Topic Claim extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\topicclaim\tests;

use phpbbmodders\topicclaim\claims;

/**
 * Tests the permission checks and note cleaning of
 * \phpbbmodders\topicclaim\claims
 */
class claims_test extends \phpbb_test_case
{
	/** @var \PHPUnit\Framework\MockObject\MockObject|\phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\user */
	protected $user;

	protected function setUp(): void
	{
		parent::setUp();

		$this->auth = $this->getMockBuilder('\phpbb\auth\auth')->disableOriginalConstructor()->getMock();
		$this->user = $this->getMockBuilder('\phpbb\user')->disableOriginalConstructor()->getMock();
		$this->user->data = ['user_id' => 2, 'is_registered' => true];
	}

	/**
	 * @param array $permissions Granted options, as 'option' or 'option:forum_id'
	 * @return claims
	 */
	protected function get_claims(array $permissions = [])
	{
		$this->auth->method('acl_get')
			->willReturnCallback(function ($option, $forum_id = 0) use ($permissions) {
				return in_array($option, $permissions, true) || in_array($option . ':' . $forum_id, $permissions, true);
			});

		$db = $this->getMockBuilder('\phpbb\db\driver\driver_interface')->getMock();
		$log = $this->getMockBuilder('\phpbb\log\log_interface')->getMock();
		$notifications = $this->getMockBuilder('\phpbb\notification\manager')->disableOriginalConstructor()->getMock();

		return new claims($this->auth, $db, $log, $notifications, $this->user, 'phpbb_topicclaim_claims');
	}

	public function test_can_view_needs_the_forum_permission()
	{
		$claims = $this->get_claims(['m_topicclaim:5']);

		$this->assertTrue($claims->can_view(5));
		$this->assertFalse($claims->can_view(6));
	}

	public function test_guests_cannot_view()
	{
		$this->user->data = ['user_id' => ANONYMOUS, 'is_registered' => false];
		$claims = $this->get_claims(['m_topicclaim:5']);

		$this->assertFalse($claims->can_view(5));
	}

	public function test_moderators_manage_only_their_own_claims()
	{
		$claims = $this->get_claims(['m_topicclaim:5']);

		$this->assertTrue($claims->can_manage(['user_id' => 2], 5));
		$this->assertFalse($claims->can_manage(['user_id' => 3], 5));
		$this->assertFalse($claims->can_override());
	}

	public function test_override_manages_other_claims_where_the_user_can_claim()
	{
		$claims = $this->get_claims(['m_topicclaim:5', 'a_topicclaim']);

		$this->assertTrue($claims->can_override());
		$this->assertTrue($claims->can_manage(['user_id' => 3], 5));
		// Override alone does not reach forums where the user cannot claim
		$this->assertFalse($claims->can_manage(['user_id' => 3], 6));
	}

	public function test_is_own_compares_as_numbers()
	{
		$claims = $this->get_claims();

		$this->assertTrue($claims->is_own(['user_id' => '2']));
		$this->assertFalse($claims->is_own(['user_id' => 20]));
	}

	/**
	 * @return array
	 */
	public static function clean_note_data()
	{
		return [
			'trimmed'				=> ['  watch this  ', 'watch this'],
			'whitespace joined'		=> ["two\n\nlines\tand  spaces", 'two lines and spaces'],
			'html kept as text'		=> ['<b>not bold</b> & "quoted"', '<b>not bold</b> & "quoted"'],
			'empty'					=> ['   ', ''],
			'cut to the limit'		=> [str_repeat('é', 300), str_repeat('é', claims::NOTE_MAX_LENGTH)],
		];
	}

	/**
	 * @dataProvider clean_note_data
	 *
	 * @param string $note
	 * @param string $expected
	 */
	public function test_clean_note($note, $expected)
	{
		$this->assertSame($expected, $this->get_claims()->clean_note($note));
	}
}
