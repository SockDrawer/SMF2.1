<?php

/**
 * The functions in this file deal with reporting posts or profiles to mods and admins
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\StringLibrary;

/**
 * Report a post or profile to the moderator... ask for a comment.
 * Gathers data from the user to report abuse to the moderator(s).
 * Uses the ReportToModerator template, main sub template.
 * Requires the report_any permission.
 * Uses ReportToModerator2() if post data was sent.
 * Accessed through ?action=reporttm.
 */
function ReportToModerator()
{
	global $txt, $topic, $context, $smcFunc, $scripturl, $sourcedir;

	$context['robot_no_index'] = true;
	$context['comment_body'] = '';

	// No guests!
	is_not_guest();

	// You can't use this if it's off or you are not allowed to do it.
	// If we don't have the ID of something to report, we'll die with a no_access error below
	if (isset($_REQUEST['msg']))
		isAllowedTo('report_any');
	elseif (isset($_REQUEST['u']))
		isAllowedTo('report_user');

	// If they're posting, it should be processed by ReportToModerator2.
	if ((isset($_POST[$context['session_var']]) || isset($_POST['save'])) && empty($context['post_errors']))
		ReportToModerator2();

	// We need a message ID or user ID to check!
	if (empty($_REQUEST['msg']) && empty($_REQUEST['mid']) && empty($_REQUEST['u']))
		fatal_lang_error('no_access', false);

	// For compatibility, accept mid, but we should be using msg. (not the flavor kind!)
	if (!empty($_REQUEST['msg']) || !empty($_REQUEST['mid']))
		$_REQUEST['msg'] = empty($_REQUEST['msg']) ? (int) $_REQUEST['mid'] : (int) $_REQUEST['msg'];
	// msg and mid empty - assume we're reporting a user
	elseif (!empty($_REQUEST['u']))
		$_REQUEST['u'] = (int) $_REQUEST['u'];

	// Set up some form values
	$context['report_type'] = isset($_REQUEST['msg']) ? 'msg' : 'u';
	$context['reported_item'] = isset($_REQUEST['msg']) ? $_REQUEST['msg'] : $_REQUEST['u'];

	if (isset($_REQUEST['msg']))
	{
		// Check the message's ID - don't want anyone reporting a post they can't even see!
		$result = $smcFunc['db']->query('', '
			SELECT m.id_msg
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
			WHERE m.id_msg = {int:id_msg}
				AND m.id_topic = {int:current_topic}
			LIMIT 1',
			[
				'current_topic' => $topic,
				'id_msg' => $_REQUEST['msg'],
			]
		);
		if ($smcFunc['db']->num_rows($result) == 0)
			fatal_lang_error('no_board', false);
		list ($_REQUEST['msg']) = $smcFunc['db']->fetch_row($result);
		$smcFunc['db']->free_result($result);

		// This is here so that the user could, in theory, be redirected back to the topic.
		$context['start'] = $_REQUEST['start'];
		$context['message_id'] = $_REQUEST['msg'];

		// The submit URL is different for users than it is for posts
		$context['submit_url'] = $scripturl . '?action=reporttm;msg=' . $_REQUEST['msg'] . ';topic=' . $topic;
	}
	else
	{
		// Check the user's ID
		$result = $smcFunc['db']->query('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member = {int:current_user}',
			[
				'current_user' => $_REQUEST['u'],
			]
		);

		if ($smcFunc['db']->num_rows($result) == 0)
			fatal_lang_error('no_user', false);
		list($_REQUEST['u'], $display_name) = $smcFunc['db']->fetch_row($result);

		$context['current_user'] = $_REQUEST['u'];
		$context['submit_url'] = $scripturl . '?action=reporttm;u=' . $_REQUEST['u'];
	}

	$context['comment_body'] = !isset($_POST['comment']) ? '' : StringLibrary::escape(trim($_POST['comment'], ENT_QUOTES));

	$context['page_title'] = $context['report_type'] == 'msg' ? $txt['report_to_mod'] : sprintf($txt['report_profile'], $display_name);
	$context['notice'] = $context['report_type'] == 'msg' ? $txt['report_to_mod_func'] : $txt['report_profile_func'];

	// Show the inputs for the comment, etc.
	loadLanguage('Post');
	$context['sub_template'] = 'reporttomod';

	addInlineJavaScript('
	var error_box = $("#error_box");
	$("#report_comment").keyup(function() {
		var post_too_long = $("#error_post_too_long");
		if ($(this).val().length > 254)
		{
			if (post_too_long.length == 0)
			{
				error_box.show();
				if ($.trim(error_box.html()) == \'\')
					error_box.append("<ul id=\'error_list\'></ul>");

				$("#error_list").append("<li id=\'error_post_too_long\' class=\'error\'>" + ' . JavaScriptEscape($txt['post_too_long']) . ' + "</li>");
			}
		}
		else
		{
			post_too_long.remove();
			if ($("#error_list li").length == 0)
				error_box.hide();
		}
	});', true);
}

/**
 * Send the emails.
 * Sends off emails to all the moderators.
 * Sends to administrators and global moderators. (1 and 2)
 * Called by ReportToModerator(), and thus has the same permission and setting requirements as it does.
 * Accessed through ?action=reporttm when posting.
 */
function ReportToModerator2()
{
	global $txt, $sourcedir, $context;

	// Sorry, no guests allowed... Probably just trying to spam us anyway
	is_not_guest();

	// You must have the proper permissions!
	if (isset($_REQUEST['msg']))
		isAllowedTo('report_any');
	else
		isAllowedTo('report_user');

	// Make sure they aren't spamming.
	spamProtection('reporttm');

	require_once($sourcedir . '/Subs-Post.php');

	// Prevent double submission of this form.
	checkSubmitOnce('check');

	// No errors, yet.
	$post_errors = [];

	// Check their session.
	if (checkSession('post', '', false) != '')
		$post_errors[] = 'session_timeout';

	// Make sure we have a comment and it's clean.
	if (!isset($_POST['comment']) || StringLibrary::htmltrim($_POST['comment']) === '')
		$post_errors[] = 'no_comment';

	$poster_comment = strtr(StringLibrary::escape($_POST['comment']), ["\r" => '', "\t" => '']);

	if (StringLibrary::strlen($poster_comment) > 254)
		$post_errors[] = 'post_too_long';

	// Any errors?
	if (!empty($post_errors))
	{
		loadLanguage('Errors');

		$context['post_errors'] = [];
		foreach ($post_errors as $post_error)
			$context['post_errors'][$post_error] = $txt['error_' . $post_error];

		return ReportToModerator();
	}

	if (isset($_POST['msg']))
	{
		// Handle this elsewhere to keep things from getting too long
		reportPost($_POST['msg'], $poster_comment);
	}
	else
	{
		reportUser($_POST['u'], $poster_comment);
	}
}

/**
 * Actually reports a post using information specified from a form
 *
 * @param int $msg The ID of the post being reported
 * @param string $reason The reason specified for reporting the post
 */
function reportPost($msg, $reason)
{
	global $context, $smcFunc, $user_info, $topic, $txt;

	// Get the basic topic information, and make sure they can see it.
	$_POST['msg'] = (int) $msg;

	$request = $smcFunc['db']->query('', '
		SELECT m.id_topic, m.id_board, m.subject, m.body, m.id_member AS id_poster, m.poster_name, mem.real_name
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
		WHERE m.id_msg = {int:id_msg}
			AND m.id_topic = {int:current_topic}
		LIMIT 1',
		[
			'current_topic' => $topic,
			'id_msg' => $_POST['msg'],
		]
	);
	if ($smcFunc['db']->num_rows($request) == 0)
		fatal_lang_error('no_board', false);
	$message = $smcFunc['db']->fetch_assoc($request);
	$smcFunc['db']->free_result($request);

	$request = $smcFunc['db']->query('', '
		SELECT id_report, ignore_all
		FROM {db_prefix}log_reported
		WHERE id_msg = {int:id_msg}
			AND (closed = {int:not_closed} OR ignore_all = {int:ignored})
		ORDER BY ignore_all DESC',
		[
			'id_msg' => $_POST['msg'],
			'not_closed' => 0,
			'ignored' => 1,
		]
	);
	if ($smcFunc['db']->num_rows($request) != 0)
		list ($id_report, $ignore) = $smcFunc['db']->fetch_row($request);

	$smcFunc['db']->free_result($request);

	// If we're just going to ignore these, then who gives a monkeys...
	if (!empty($ignore))
		redirectexit('topic=' . $topic . '.msg' . $_POST['msg'] . '#msg' . $_POST['msg']);

	// Already reported? My god, we could be dealing with a real rogue here...
	if (!empty($id_report))
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}log_reported
			SET num_reports = num_reports + 1, time_updated = {int:current_time}
			WHERE id_report = {int:id_report}',
			[
				'current_time' => time(),
				'id_report' => $id_report,
			]
		);
	// Otherwise, we shall make one!
	else
	{
		if (empty($message['real_name']))
			$message['real_name'] = $message['poster_name'];

		$id_report = $smcFunc['db']->insert('',
			'{db_prefix}log_reported',
			[
				'id_msg' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'id_member' => 'int', 'membername' => 'string',
				'subject' => 'string', 'body' => 'string', 'time_started' => 'int', 'time_updated' => 'int',
				'num_reports' => 'int', 'closed' => 'int',
			],
			[
				$_POST['msg'], $message['id_topic'], $message['id_board'], $message['id_poster'], $message['real_name'],
				$message['subject'], $message['body'], time(), time(), 1, 0,
			],
			['id_report'],
			1
		);
	}

	// Now just add our report...
	if ($id_report)
	{
		$smcFunc['db']->insert('',
			'{db_prefix}log_reported_comments',
			[
				'id_report' => 'int', 'id_member' => 'int', 'membername' => 'string',
				'member_ip' => 'inet', 'comment' => 'string', 'time_sent' => 'int',
			],
			[
				$id_report, $user_info['id'], $user_info['name'],
				$user_info['ip'], $reason, time(),
			],
			['id_comment']
		);

		// And get ready to notify people.
		StoryBB\Task::queue_adhoc('StoryBB\\Task\\Adhoc\\MsgReportNotify', [
			'report_id' => $id_report,
			'msg_id' => $_POST['msg'],
			'topic_id' => $message['id_topic'],
			'board_id' => $message['id_board'],
			'sender_id' => $context['user']['id'],
			'sender_name' => $context['user']['name'],
			'time' => time(),
		]);
	}

	// Keep track of when the mod reports get updated, that way we know when we need to look again.
	updateSettings(['last_mod_report_action' => time()]);

	// Back to the post we reported!
	session_flash('success', $txt['report_sent']);
	redirectexit('topic=' . $topic . '.msg' . $_POST['msg'] . '#msg' . $_POST['msg']);
}

/**
 * Actually reports a user's profile using information specified from a form
 *
 * @param int $id_member The ID of the member whose profile is being reported
 * @param string $reason The reason specified by the reporter for this report
 */
function reportUser($id_member, $reason)
{
	global $context, $smcFunc, $user_info, $txt;

	// Get the basic topic information, and make sure they can see it.
	$_POST['u'] = (int) $id_member;

	$request = $smcFunc['db']->query('', '
		SELECT id_member, real_name, member_name
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}',
		[
			'id_member' => $_POST['u']
		]
	);
	if ($smcFunc['db']->num_rows($request) == 0)
		fatal_lang_error('no_user', false);
	$user = $smcFunc['db']->fetch_assoc($request);
	$smcFunc['db']->free_result($request);

	$user_name = un_htmlspecialchars($user['real_name']) . ($user['real_name'] != $user['member_name'] ? ' (' . $user['member_name'] . ')' : '');

	$request = $smcFunc['db']->query('', '
		SELECT id_report, ignore_all
		FROM {db_prefix}log_reported
		WHERE id_member = {int:id_member}
			AND id_msg = {int:not_a_reported_post}
			AND (closed = {int:not_closed} OR ignore_all = {int:ignored})
		ORDER BY ignore_all DESC',
		[
			'id_member' => $_POST['u'],
			'not_a_reported_post' => 0,
			'not_closed' => 0,
			'ignored' => 1,
		]
	);
	if ($smcFunc['db']->num_rows($request) != 0)
		list ($id_report, $ignore) = $smcFunc['db']->fetch_row($request);

	$smcFunc['db']->free_result($request);

	// If we're just going to ignore these, then who gives a monkeys...
	if (!empty($ignore))
		redirectexit('action=profile;u=' . $_POST['u']);

	// Already reported? My god, we could be dealing with a real rogue here...
	if (!empty($id_report))
		$smcFunc['db']->query('', '
			UPDATE {db_prefix}log_reported
			SET num_reports = num_reports + 1, time_updated = {int:current_time}
			WHERE id_report = {int:id_report}',
			[
				'current_time' => time(),
				'id_report' => $id_report,
			]
		);
	// Otherwise, we shall make one!
	else
	{
		$id_report = $smcFunc['db']->insert('',
			'{db_prefix}log_reported',
			[
				'id_msg' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'id_member' => 'int', 'membername' => 'string',
				'subject' => 'string', 'body' => 'string', 'time_started' => 'int', 'time_updated' => 'int',
				'num_reports' => 'int', 'closed' => 'int',
			],
			[
				0, 0, 0, $user['id_member'], $user_name,
				'', '', time(), time(), 1, 0,
			],
			['id_report'],
			1
		);
	}

	// Now just add our report...
	if ($id_report)
	{
		$smcFunc['db']->insert('',
			'{db_prefix}log_reported_comments',
			[
				'id_report' => 'int', 'id_member' => 'int', 'membername' => 'string',
				'member_ip' => 'inet', 'comment' => 'string', 'time_sent' => 'int',
			],
			[
				$id_report, $user_info['id'], $user_info['name'],
				$user_info['ip'], $reason, time(),
			],
			['id_comment']
		);

		// And get ready to notify people.
		StoryBB\Task::queue_adhoc('StoryBB\\Task\\Adhoc\\MemberReportNotify', [
			'report_id' => $id_report,
			'user_id' => $user['id_member'],
			'user_name' => $user_name,
			'sender_id' => $context['user']['id'],
			'sender_name' => $context['user']['name'],
			'comment' => $reason,
			'time' => time(),
		]);
	}

	// Keep track of when the mod reports get updated, that way we know when we need to look again.
	updateSettings(['last_mod_report_action' => time()]);

	// Back to the post we reported!
	session_flash('success', $txt['report_sent']);
	redirectexit('reportsent;action=profile;u=' . $id_member);
}
