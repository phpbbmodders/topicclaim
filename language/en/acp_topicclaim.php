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
	'ACP_TOPICCLAIM_SETTINGS_EXPLAIN'	=> 'Moderators claim topics from the topic’s Quick-mod tools, and see every claim under <strong>MCP → Claimed topics</strong>. Who can claim, and who can take over other moderators’ claims, is set under <strong>Permissions</strong> (<em>Misc</em> tab of the moderator and administrator permissions).',

	'TOPICCLAIM_RELEASE_SETTINGS'		=> 'Releasing claims',
	'TOPICCLAIM_RELEASE_LOCKED'			=> 'Release claims on locked topics',
	'TOPICCLAIM_RELEASE_LOCKED_EXPLAIN'	=> 'A claim is released when its topic is locked, and locked topics cannot be claimed.',
	'TOPICCLAIM_EXPIRE_DAYS'			=> 'Release claims after',
	'TOPICCLAIM_EXPIRE_DAYS_EXPLAIN'	=> 'Claims older than this are released. Taking over or transferring a claim starts it again. 0 keeps claims until they are released.',
	'TOPICCLAIM_EXPIRE_DAYS_INVALID'	=> 'Release claims after must be between 0 and %d days.',
	'TOPICCLAIM_RELEASE_CRON'			=> 'Checked about once an hour, when the board runs its scheduled tasks.',

	'TOPICCLAIM_DISPLAY_SETTINGS'		=> 'Display',
	'TOPICCLAIM_ICON'					=> 'Claim icon',
	'TOPICCLAIM_ICON_EXPLAIN'			=> 'The Font Awesome icon marking claimed topics, for example <samp>fa-eye</samp> or <samp>fa-flag</samp>.',
	'TOPICCLAIM_ICON_PREVIEW'			=> 'Preview',
	'TOPICCLAIM_ICON_INVALID'			=> 'The claim icon must be a Font Awesome class such as fa-eye.',
]);
