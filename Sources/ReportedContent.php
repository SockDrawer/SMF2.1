<?php

/**
 * Handles reported members and posts, as well as moderation comments.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\Helper\Parser;
use StoryBB\StringLibrary;

/**
 * Sets and call a function based on the given subaction. Acts as a dispatcher function.
 * It requires the moderate_forum permission.
 *
 * @uses ModerationCenter template.
 * @uses ModerationCenter language file.
 *
 */
function ReportedContent()
{
	global $txt, $context, $user_info;
	global $sourcedir;

	// First order of business - what are these reports about?
	// area=reported{type}
	$context['report_type'] = substr($_GET['area'], 8);

	loadLanguage('ModerationCenter');

	// We need this little rough gem.
	require_once($sourcedir . '/Subs-ReportedContent.php');

	// Set up the comforting bits...
	$context['page_title'] = $txt['mc_reported_' . $context['report_type']];

	// Put the open and closed options into tabs, because we can...
	$context[$context['moderation_menu_name']]['tab_data'] = [
		'title' => $txt['mc_reported_' . $context['report_type']],
		'help' => '',
		'description' => $txt['mc_reported_' . $context['report_type'] . '_desc'],
	];

	// This comes under the umbrella of moderating posts.
	if ($context['report_type'] == 'members' || $user_info['mod_cache']['bq'] == '0=1')
		isAllowedTo('moderate_forum');

	$subActions = [
		'show' => 'ShowReports',
		'closed' => 'ShowClosedReports',
		'handle' => 'HandleReport', // Deals with closing/opening reports.
		'details' => 'ReportDetails', // Shows a single report and its comments.
		'handlecomment' => 'HandleComment', // CRUD actions for moderator comments.
		'editcomment' => 'EditComment',
	];

	// Go ahead and add your own sub-actions.
	routing_integration_hook('integrate_reported_' . $context['report_type'], [&$subActions]);

	// By default we call the open sub-action.
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$context['sub_action'] = StringLibrary::htmltrim(StringLibrary::escape($_REQUEST['sa']), ENT_QUOTES);

	else
		$context['sub_action'] = 'show';

	// Hi Ho Silver Away!
	call_helper($subActions[$context['sub_action']]);
}

/**
 * Shows all currently open reported posts.
 * Handles closing multiple reports
 *
 */
function ShowReports()
{
	global $context, $txt, $scripturl;

	// Showing closed or open ones? regardless, turn this to an integer for better handling.
	$context['view_closed'] = 0;

	// Call the right template.
	StoryBB\Template::add_helper(['create_button' => 'create_button']);
	if ($context['report_type'] == 'posts')
	{
		$context['sub_template'] = 'modcenter_reportedposts';
	}
	else
	{
		$context['sub_template'] = 'modcenter_reportedmembers';
	}

	$context['start'] = (int) isset($_GET['start']) ? $_GET['start'] : 0;

	// Before anything, we need to know just how many reports do we have.
	$context['total_reports'] = countReports($context['view_closed']);

	// Just how many items are we showing per page?
	$context['reports_how_many'] = 10;

	// So, that means we can have pagination, yes?
	$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=reported' . $context['report_type'] . ';sa=show', $context['start'], $context['total_reports'], $context['reports_how_many']);

	// Get the reports at once!
	$context['reports'] = getReports($context['view_closed']);

	// Are we closing multiple reports?
	if (isset($_POST['close']) && isset($_POST['close_selected']))
	{
		checkSession('post');
		validateToken('mod-report-close-all');

		// All the ones to update...
		$toClose = [];
		foreach ($_POST['close'] as $rid)
			$toClose[] = (int) $rid;

		if (!empty($toClose))
			updateReport('closed', 1, $toClose);

		// Set the confirmation message.
		session_flash('success', $txt['report_action_close_all']);

		// Force a page refresh.
		redirectexit($scripturl . '?action=moderate;area=reported' . $context['report_type']);
	}

	createToken('mod-report-close-all');
	createToken('mod-report-ignore', 'get');
	createToken('mod-report-closed', 'get');
}

/**
 * Shows all currently closed reported posts.
 *
 */
function ShowClosedReports()
{
	global $context, $scripturl;

	// Showing closed ones.
	$context['view_closed'] = 1;

	// Call the right template.
	if ($context['report_type'] == 'posts')
	{
		StoryBB\Template::add_helper(['create_button' => 'create_button']);
		$context['sub_template'] = 'modcenter_reportedposts';
	}
	else
	{
		$context['sub_template'] = 'reported_' . $context['report_type'];
	}
	$context['start'] = (int) isset($_GET['start']) ? $_GET['start'] : 0;

	// Before anything, we need to know just how many reports do we have.
	$context['total_reports'] = countReports($context['view_closed']);

	// Just how many items are we showing per page?
	$context['reports_how_many'] = 10;

	// So, that means we can have pagination, yes?
	$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=reported' . $context['report_type'] . ';sa=closed', $context['start'], $context['total_reports'], $context['reports_how_many']);

	// Get the reports at once!
	$context['reports'] = getReports($context['view_closed']);

	createToken('mod-report-ignore', 'get');
	createToken('mod-report-closed', 'get');
}

/**
 * Shows detailed information about a report. such as report comments and moderator comments.
 * Shows a list of moderation actions for the specific report.
 *
 */
function ReportDetails()
{
	global $context, $sourcedir, $scripturl, $txt;

	// Have to at least give us something to work with.
	if (empty($_REQUEST['rid']))
		fatal_lang_error('mc_reportedp_none_found');

	// Integers only please
	$report_id = (int) $_REQUEST['rid'];

	// Get the report details.
	$report = getReportDetails($report_id);

	if (!$report)
		fatal_lang_error('mc_no_modreport_found');

	// Build the report data - basic details first, then extra stuff based on the type
	$context['report'] = [
		'id' => $report['id_report'],
		'report_href' => $scripturl . '?action=moderate;area=reported' . $context['report_type'] . ';rid=' . $report['id_report'],
		'comments' => [],
		'mod_comments' => [],
		'time_started' => timeformat($report['time_started']),
		'last_updated' => timeformat($report['time_updated']),
		'num_reports' => $report['num_reports'],
		'closed' => $report['closed'],
		'ignore' => $report['ignore_all']
	];

	// Different reports have different "extra" data attached to them
	if ($context['report_type'] == 'members')
	{
		$extraDetails = [
			'user' => [
				'id' => $report['id_user'],
				'name' => $report['user_name'],
				'link' => $report['id_user'] ? '<a href="' . $scripturl . '?action=profile;u=' . $report['id_user'] . '">' . $report['user_name'] . '</a>' : $report['user_name'],
				'href' => $scripturl . '?action=profile;u=' . $report['id_user'],
			],
		];
	}
	else
	{
		$extraDetails = [
			'topic_id' => $report['id_topic'],
			'board_id' => $report['id_board'],
			'message_id' => $report['id_msg'],
			'message_href' => $scripturl . '?msg=' . $report['id_msg'],
			'message_link' => '<a href="' . $scripturl . '?msg=' . $report['id_msg'] . '">' . $report['subject'] . '</a>',
			'author' => [
				'id' => $report['id_author'],
				'name' => $report['author_name'],
				'link' => $report['id_author'] ? '<a href="' . $scripturl . '?action=profile;u=' . $report['id_author'] . '">' . $report['author_name'] . '</a>' : $report['author_name'],
				'href' => $scripturl . '?action=profile;u=' . $report['id_author'],
			],
			'subject' => $report['subject'],
			'body' => Parser::parse_bbc($report['body']),
		];
	}

	$context['report'] = array_merge($context['report'], $extraDetails);

	$reportComments = getReportComments($report_id);

	if (!empty($reportComments))
		$context['report'] = array_merge($context['report'], $reportComments);

	// What have the other moderators done to this message?
	require_once($sourcedir . '/Modlog.php');
	require_once($sourcedir . '/Subs-List.php');
	loadLanguage('Modlog');

	// Parameters are slightly different depending on what we're doing here...
	if ($context['report_type'] == 'members')
	{
		// Find their ID in the serialized action string...
		$user_id_length = strlen((string) $context['report']['user']['id']);
		$member = 's:6:"member";s:' . $user_id_length . ':"' . $context['report']['user']['id'] . '";}';

		$params = [
			'lm.extra LIKE {raw:member}
				AND lm.action LIKE {raw:report}',
			['member' => '\'%' . $member . '\'', 'report' => '\'%_user_report\''],
			1,
			true,
		];
	}
	else
	{
		$params = [
			'lm.id_topic = {int:id_topic}
				AND lm.id_board != {int:not_a_reported_post}',
			['id_topic' => $context['report']['topic_id'], 'not_a_reported_post' => 0],
			1,
		];
	}

	// This is all the information from the moderation log.
	$listOptions = [
		'id' => 'moderation_actions_list',
		'title' => $txt['mc_modreport_modactions'],
		'items_per_page' => 15,
		'no_items_label' => $txt['modlog_no_entries_found'],
		'base_href' => $scripturl . '?action=moderate;area=reported' . $context['report_type'] . ';sa=details;rid=' . $context['report']['id'],
		'default_sort_col' => 'time',
		'get_items' => [
			'function' => 'list_getModLogEntries',
			'params' => $params,
		],
		'get_count' => [
			'function' => 'list_getModLogEntryCount',
			'params' => $params,
		],
		// This assumes we are viewing by user.
		'columns' => [
			'action' => [
				'header' => [
					'value' => $txt['modlog_action'],
				],
				'data' => [
					'db' => 'action_text',
					'class' => 'smalltext',
				],
				'sort' => [
					'default' => 'lm.action',
					'reverse' => 'lm.action DESC',
				],
			],
			'time' => [
				'header' => [
					'value' => $txt['modlog_date'],
				],
				'data' => [
					'db' => 'time',
					'class' => 'smalltext',
				],
				'sort' => [
					'default' => 'lm.log_time',
					'reverse' => 'lm.log_time DESC',
				],
			],
			'moderator' => [
				'header' => [
					'value' => $txt['modlog_member'],
				],
				'data' => [
					'db' => 'moderator_link',
					'class' => 'smalltext',
				],
				'sort' => [
					'default' => 'mem.real_name',
					'reverse' => 'mem.real_name DESC',
				],
			],
			'position' => [
				'header' => [
					'value' => $txt['modlog_position'],
				],
				'data' => [
					'db' => 'position',
					'class' => 'smalltext',
				],
				'sort' => [
					'default' => 'mg.group_name',
					'reverse' => 'mg.group_name DESC',
				],
			],
			'ip' => [
				'header' => [
					'value' => $txt['modlog_ip'],
				],
				'data' => [
					'db' => 'ip',
					'class' => 'smalltext',
				],
				'sort' => [
					'default' => 'lm.ip',
					'reverse' => 'lm.ip DESC',
				],
			],
		],
	];

	// Create the watched user list.
	createList($listOptions);

	// Make sure to get the correct tab selected.
	if ($context['report']['closed'])
		$context[$context['moderation_menu_name']]['current_subsection'] = 'closed';

	// Finally we are done :P
	StoryBB\Template::add_helper(['create_button' => 'create_button']);
	if ($context['report_type'] == 'members')
	{
		$context['page_title'] = sprintf($txt['mc_viewmemberreport'], $context['report']['user']['name']);
		$context['sub_template'] = 'modcenter_reportedmember_details';
	}
	else
	{
		$context['page_title'] = sprintf($txt['mc_viewmodreport'], $context['report']['subject'], $context['report']['author']['name']);
		$context['sub_template'] = 'modcenter_reportedpost_details';
	}

	createToken('mod-reportC-add');
	createToken('mod-reportC-delete', 'get');

	// We can "un-ignore" and close a report from here so add their respective tokens.
	createToken('mod-report-ignore', 'get');
	createToken('mod-report-closed', 'get');
}

/**
 * Creates/Deletes moderator comments.
 *
 */
function HandleComment()
{
	global $scripturl, $user_info, $context, $txt;

	// The report ID is a must.
	if (empty($_REQUEST['rid']))
		fatal_lang_error('mc_reportedp_none_found');

	// Integers only please.
	$report_id = (int) $_REQUEST['rid'];

	// If they are adding a comment then... add a comment.
	if (isset($_POST['add_comment']) && !empty($_POST['mod_comment']))
	{
		checkSession();
		validateToken('mod-reportC-add');

		$new_comment = trim(StringLibrary::escape($_POST['mod_comment']));

		saveModComment($report_id, [$report_id, $new_comment, time()]);

		// Everything went better than expected!
		session_flash('success', $txt['report_action_message_saved']);
	}

	// Deleting a comment?
	if (isset($_REQUEST['delete']) && isset($_REQUEST['mid']))
	{
		checkSession('get');
		validateToken('mod-reportC-delete', 'get');

		if (empty($_REQUEST['mid']))
			fatal_lang_error('mc_reportedp_comment_none_found');

		$comment_id = (int) $_REQUEST['mid'];

		// We need to verify some data, so lets load the comment details once more!
		$comment = getCommentModDetails($comment_id);

		// Perhaps somebody else already deleted this fine gem...
		if (empty($comment))
			fatal_lang_error('report_action_message_delete_issue');

		// Can you actually do this?
		$comment_owner = $user_info['id'] == $comment['id_member'];

		// Nope! sorry.
		if (!allowedTo('admin_forum') && !$comment_owner)
			fatal_lang_error('report_action_message_delete_cannot');

		// All good!
		deleteModComment($comment_id);

		// Tell them the message was deleted.
		session_flash('success', $txt['report_action_message_deleted']);
	}

	//Redirect to prevent double submission.
	redirectexit($scripturl . '?action=moderate;area=reported' . $context['report_type'] . ';sa=details;rid=' . $report_id);
}

/**
 * Shows a textarea for editing a moderator comment.
 * Handles the edited comment and stores it on the DB.
 *
 */
function EditComment()
{
	global $context, $txt, $scripturl, $user_info;

	checkSession(isset($_REQUEST['save']) ? 'post' : 'get');

	// The report ID is a must.
	if (empty($_REQUEST['rid']))
		fatal_lang_error('mc_reportedp_none_found');

	if (empty($_REQUEST['mid']))
		fatal_lang_error('mc_reportedp_comment_none_found');

	// Integers only please.
	$context['report_id'] = (int) $_REQUEST['rid'];
	$context['comment_id'] = (int) $_REQUEST['mid'];

	$context['comment'] = getCommentModDetails($context['comment_id']);

	if (empty($context['comment']))
		fatal_lang_error('mc_reportedp_comment_none_found');

	// Set up the comforting bits...
	$context['page_title'] = $txt['mc_reported_posts'];
	$context['sub_template'] = 'modcenter_reports_comment_edit';

	if (isset($_REQUEST['save']) && isset($_POST['edit_comment']) && !empty($_POST['mod_comment']))
	{
		validateToken('mod-reportC-edit');

		// Make sure there is some data to edit on the DB.
		if (empty($context['comment']))
			fatal_lang_error('report_action_message_edit_issue');

		// Still there, good, now lets see if you can actually edit it...
		$comment_owner = $user_info['id'] == $context['comment']['id_member'];

		// So, you aren't neither an admin or the comment owner huh? that's too bad.
		if (!allowedTo('admin_forum') && !$comment_owner)
			fatal_lang_error('report_action_message_edit_cannot');

		// All good!
		$edited_comment = trim(StringLibrary::escape($_POST['mod_comment']));

		editModComment($context['comment_id'], $edited_comment);

		session_flash('success', $txt['report_action_message_edited']);

		redirectexit($scripturl . '?action=moderate;area=reported' . $context['report_type'] . ';sa=details;rid=' . $context['report_id']);
	}

	createToken('mod-reportC-edit');
}

/**
 * Performs closing/ignoring actions for a given report.
 *
 */
function HandleReport()
{
	global $scripturl, $context, $txt;

	checkSession('get');

	// We need to do something!
	if (empty($_GET['rid']) && (!isset($_GET['ignore']) || !isset($_GET['closed'])))
		fatal_lang_error('mc_reportedp_none_found');

	// What are we gonna do?
	$action = isset($_GET['ignore']) ? 'ignore' : 'closed';

	validateToken('mod-report-' . $action, 'get');

	// Are we ignore or "un-ignore"? "un-ignore" that's a funny word!
	$value = (int) $_GET[$action];

	// Figuring out.
	$message = $action == 'ignore' ? ($value ? 'ignore' : 'unignore') : ($value ? 'close' : 'open');

	// Integers only please.
	$report_id = (int) $_REQUEST['rid'];

	// Update the DB entry
	updateReport($action, $value, $report_id);

	// So, time to show a confirmation message, lets do some trickery!
	session_flash('success', $txt['report_action_' . $message]);

	// Done!
	redirectexit($scripturl . '?action=moderate;area=reported' . $context['report_type']);
}
