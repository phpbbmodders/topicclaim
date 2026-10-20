<?php
/**
 *
 * Topic Claim extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

/**
 * DO NOT CHANGE
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	// Quick-mod tools
	'TOPICCLAIM_QUICKMOD_CLAIM'		=> 'Claim topic',
	'TOPICCLAIM_QUICKMOD_MANAGE'	=> 'Topic claim',

	// Claim marks
	'TOPICCLAIM_CLAIMED_BY'			=> 'Claimed by %1$s on %2$s',
	'TOPICCLAIM_CLAIMED_BY_SHORT'	=> 'Claimed by %s',
	'TOPICCLAIM_CLAIMED_BY_YOU'		=> 'Claimed by you',
	'TOPICCLAIM_MANAGE'				=> 'Manage claim',

	// Claim page
	'TOPICCLAIM_PAGE_TITLE'			=> 'Topic claim',
	'TOPICCLAIM_PAGE_EXPLAIN'		=> 'Claim a topic to tell the other moderators you are looking after it. Only moderators see claims.',
	'TOPICCLAIM_LOGIN'				=> 'You need to be logged in to claim topics.',
	'TOPICCLAIM_NOT_CLAIMED_YET'	=> 'Nobody has claimed this topic.',
	'TOPICCLAIM_HELD_BY'			=> 'Claimed by',
	'TOPICCLAIM_SINCE'				=> 'Since',
	'TOPICCLAIM_NOTE'				=> 'Note',
	'TOPICCLAIM_NOTE_EXPLAIN'		=> 'Optional. Other moderators see it with the claim. Up to %d characters.',
	'TOPICCLAIM_NO_NOTE'			=> 'No note',
	'TOPICCLAIM_CLAIM'				=> 'Claim topic',
	'TOPICCLAIM_SAVE_NOTE'			=> 'Save note',
	'TOPICCLAIM_TAKEOVER'			=> 'Take over claim',
	'TOPICCLAIM_TAKEOVER_EXPLAIN'	=> 'Makes the claim yours, with the note above. %s is told.',
	'TOPICCLAIM_TRANSFER'			=> 'Transfer claim',
	'TOPICCLAIM_TRANSFER_TO'		=> 'Transfer to',
	'TOPICCLAIM_TRANSFER_EXPLAIN'	=> 'Moderators who can claim topics in this forum. They are told.',
	'TOPICCLAIM_RELEASE'			=> 'Release claim',
	'TOPICCLAIM_RELEASE_EXPLAIN'	=> 'Removes the claim, so another moderator can claim the topic.',
	'TOPICCLAIM_VIEW_ONLY'			=> 'Only the moderator who holds the claim, or an administrator, can change it.',
	'TOPICCLAIM_LOCKED_NOTICE'		=> 'This topic is locked. The board releases claims on locked topics, so it cannot be claimed.',
	'TOPICCLAIM_LOCKED_RELEASE'		=> 'The board releases claims on locked topics. Locking this topic releases its claim.',
	'TOPICCLAIM_EXPIRE_NOTICE'		=> [
		1	=> 'The board releases claims after %d day.',
		2	=> 'The board releases claims after %d days.',
	],
	'TOPICCLAIM_RETURN_LIST'		=> '%sReturn to the claimed topics%s',

	// Results
	'TOPICCLAIM_CLAIMED'			=> 'You claimed the topic.',
	'TOPICCLAIM_NOTE_SAVED'			=> 'The note was saved.',
	'TOPICCLAIM_TAKEN_OVER'			=> 'The claim is now yours.',
	'TOPICCLAIM_TRANSFERRED'		=> 'The claim was transferred.',
	'TOPICCLAIM_RELEASED'			=> 'The claim was released.',
	'TOPICCLAIM_ALREADY_CLAIMED'	=> 'Another moderator claimed this topic first.',
	'TOPICCLAIM_NOT_CLAIMED'		=> 'This topic is no longer claimed.',
	'TOPICCLAIM_NOT_ALLOWED'		=> 'You cannot change this claim.',
	'TOPICCLAIM_TRANSFER_INVALID'	=> 'Choose a moderator who can claim topics in this forum.',
	'TOPICCLAIM_LOCKED_NO_CLAIM'	=> 'This topic is locked, and the board releases claims on locked topics.',

	// MCP list
	'TOPICCLAIM_LIST_EXPLAIN'		=> 'Topics claimed in the forums where you can claim topics. Open a claim to transfer or release it.',
	'TOPICCLAIM_SHOW_ALL'			=> 'All claims',
	'TOPICCLAIM_SHOW_MINE'			=> 'My claims',
	'TOPICCLAIM_FILTER'				=> 'Show claims',
	'TOPICCLAIM_NO_CLAIMS'			=> 'There are no claimed topics.',
	'TOPICCLAIM_TOTAL'				=> [
		0	=> 'No claimed topics',
		1	=> '%d claimed topic',
		2	=> '%d claimed topics',
	],
	'TOPICCLAIM_TOPIC_LOCKED'		=> 'Locked',

	// Notifications
	'TOPICCLAIM_NOTIFICATION_OPTION'	=> 'Someone else changes a topic claim of yours',
	'TOPICCLAIM_NOTIFICATION_TRANSFER'	=> '<strong>Topic claim</strong> transferred to you by %1$s:',
	'TOPICCLAIM_NOTIFICATION_TAKEOVER'	=> '<strong>Topic claim</strong> taken over by %1$s:',
	'TOPICCLAIM_NOTIFICATION_RELEASE'	=> '<strong>Topic claim</strong> released by %1$s:',
	'TOPICCLAIM_NOTIFICATION_REASSIGN'	=> '<strong>Topic claim</strong> given to another moderator by %1$s:',
	'TOPICCLAIM_EMAIL_TRANSFER'			=> 'transferred to you the claim on',
	'TOPICCLAIM_EMAIL_TAKEOVER'			=> 'took over your claim on',
	'TOPICCLAIM_EMAIL_RELEASE'			=> 'released your claim on',
	'TOPICCLAIM_EMAIL_REASSIGN'			=> 'gave another moderator your claim on',

	// Logs
	'LOG_TOPICCLAIM_CLAIM'			=> '<strong>Claimed topic</strong><br />» %s',
	'LOG_TOPICCLAIM_TAKEOVER'		=> '<strong>Took over the claim of %2$s on topic</strong><br />» %1$s',
	'LOG_TOPICCLAIM_TRANSFER'		=> '<strong>Transferred the claim on topic from %2$s to %3$s</strong><br />» %1$s',
	'LOG_TOPICCLAIM_RELEASE'		=> '<strong>Released the claim of %2$s on topic</strong><br />» %1$s',
	'LOG_TOPICCLAIM_RELEASE_LOCKED'	=> '<strong>Released the claim of %2$s on locked topic</strong><br />» %1$s',
	'LOG_TOPICCLAIM_RELEASE_EXPIRED'	=> '<strong>Released the claim of %2$s on topic after the board’s time limit</strong><br />» %1$s',
	'LOG_TOPICCLAIM_SETTINGS'		=> '<strong>Altered Topic Claim settings</strong>',
]);
