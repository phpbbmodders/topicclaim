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
	'ACL_M_TOPICCLAIM'	=> 'Can claim topics and see claims',
	'ACL_A_TOPICCLAIM'	=> 'Can take over, transfer and release other moderators’ topic claims<br /><em>Only in forums where they can also claim topics.</em>',
]);
