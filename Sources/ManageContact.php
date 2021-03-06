<?php

/**
 * This file manages responses to contact-us messages.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

use StoryBB\StringLibrary;
use StoryBB\Model\Alert;

/**
 * The main dispatcher.
 */
function ManageContact()
{
	isAllowedTo('admin_forum');

	$actions = [
		'listcontact' => 'ListContact',
		'viewcontact' => 'ViewContact',
		'replycontact' => 'ReplyContact',
	];

	$sa = isset($_GET['sa'], $actions[$_GET['sa']]) ? $_GET['sa'] : 'listcontact';
	$actions[$sa]();
}

/**
 * Lists the messages received via the contact form.
 */
function ListContact()
{
	global $context, $txt, $sourcedir, $smcFunc, $modSettings, $scripturl;
	require_once($sourcedir . '/Subs-List.php');

	if (isset($_GET['delete']))
	{
		checkSession('get');
		$message = (int) $_GET['delete'];
		if (!empty($message))
		{
			// Delete any alerts sent out.
			$alerts = Alert::find_alerts(['content_type' => 'contactform', 'content_id' => $message]);
			foreach ($alerts as $member => $alert_ids)
			{
				Alert::delete($alert_ids, $member);
			}

			// Delete the messages.
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}contact_form_response
				WHERE id_message = {int:message}',
				[
					'message' => $_GET['delete'],
				]
			);
			$smcFunc['db']->query('', '
				DELETE FROM {db_prefix}contact_form
				WHERE id_message = {int:message}',
				[
					'message' => $_GET['delete'],
				]
			);
		}
		redirectexit('action=admin;area=contactform');
	}

	$listOptions = [
		'id' => 'contact_form',
		'title' => $txt['contact_us'],
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $scripturl . '?action=admin;area=contactform',
		'no_items_label' => $txt['contact_form_no_messages'],
		'get_count' => [
			'function' => function() use ($smcFunc)
			{
				$request = $smcFunc['db']->query('', '
					SELECT COUNT(cf.id_message)
					FROM {db_prefix}contact_form AS cf'
				);
				list($count) = $smcFunc['db']->fetch_row($request);
				$smcFunc['db']->free_result($request);

				return $count;
			},
		],
		'get_items' => [
			'function' => function($start, $items_per_page, $sort)
			{
				global $smcFunc;
				$rows = [];
				$request = $smcFunc['db']->query('', '
					SELECT cf.id_message, mem.id_member, COALESCE(mem.real_name, cf.contact_name) AS member_name,
						COALESCE(mem.email_address, cf.contact_email) AS member_email, cf.subject, cf.time_received, cf.status
					FROM {db_prefix}contact_form AS cf
						LEFT JOIN {db_prefix}members AS mem ON (cf.id_member = mem.id_member)
					ORDER BY CASE WHEN cf.status = 0 THEN 0 ELSE 1 END, cf.time_received
					LIMIT {int:start}, {int:limit}',
					[
						'start' => $start,
						'limit' => $items_per_page,
					]
				);
				while ($row = $smcFunc['db']->fetch_assoc($request))
				{
					$rows[$row['id_message']] = $row;
				}
				$smcFunc['db']->free_result($request);

				return $rows;
			},
		],
		'columns' => [
			'sender' => [
				'header' => [
					'value' => $txt['contact_form_sender'],
				],
				'data' => [
					'function' => function ($rowData) use ($scripturl)
					{
						if (!empty($rowData['id_member']))
						{
							return '<a href="' . $scripturl . '?action=profile;u=' . $rowData['id_member'] . '" target="_blank" rel="noopener">' . $rowData['member_name'] . '</a>';
						}
						else
							return $rowData['member_name'];
					}
				],
			],
			'sender_email' => [
				'header' => [
					'value' => $txt['contact_form_email'],
				],
				'data' => [
					'db' => 'member_email',
				],
			],
			'subject' => [
				'header' => [
					'value' => $txt['contact_form_subject'],
				],
				'data' => [
					'function' => function ($rowData) use ($scripturl)
					{
						return '<a href="' . $scripturl . '?action=admin;area=contactform;sa=viewcontact;msg=' . $rowData['id_message'] . '">' . $rowData['subject'] . '</a>';
					}
				],
			],
			'sent_on' => [
				'header' => [
					'value' => $txt['contact_form_sent_on'],
				],
				'data' => [
					'db' => 'time_received',
					'timeformat' => true,
				],
			],
			'status' => [
				'header' => [
					'value' => $txt['contact_form_status'],
				],
				'data' => [
					'function' => function($rowData) use ($txt)
					{
						switch ($rowData['status'])
						{
							case 0:
								return $txt['contact_form_status_unanswered'];
							default:
								return $txt['contact_form_status_answered'];
						}
					}
				],
			],
			'actions' => [
				'header' => [
					'value' => '',
				],
				'data' => [
					'function' => function($rowData) use ($txt, $scripturl, $context)
					{
						return '<a href="' . $scripturl . '?action=admin;area=contactform;delete=' . $rowData['id_message'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" class="you_sure button">' . $txt['delete'] . '</a>';
					},
					'class' => 'centertext',
				],
			],
		],
	];

	createList($listOptions);

	$context['page_title'] = $txt['contact_us'];
	$context['sub_template'] = 'generic_list_page';
	$context['default_list'] = 'contact_form';
}

/**
 * Shows an individual message from the contact form.
 */
function ViewContact()
{
	global $context, $txt, $smcFunc;

	$msg = isset($_GET['msg']) ? (int) $_GET['msg'] : 0;

	$request = $smcFunc['db']->query('', '
		SELECT cf.id_message, mem.id_member, COALESCE(mem.real_name, cf.contact_name) AS member_name,
			COALESCE(mem.email_address, cf.contact_email) AS member_email, cf.subject, cf.message, cf.time_received, cf.status
		FROM {db_prefix}contact_form AS cf
			LEFT JOIN {db_prefix}members AS mem ON (cf.id_member = mem.id_member)
		WHERE cf.id_message = {int:msg}',
		[
			'msg' => $msg,
		]
	);
	if ($smcFunc['db']->num_rows($request) == 0)
	{
		$smcFunc['db']->free_result($request);
		fatal_lang_error('contact_form_message_not_found', false);
	}
	$context['contact'] = $smcFunc['db']->fetch_assoc($request);
	$context['contact']['message'] = str_replace("\n", "<br>\n", $context['contact']['message']);
	$smcFunc['db']->free_result($request);

	$context['contact']['time_received_timeformat'] = timeformat($context['contact']['time_received']);

	// See if there's any previous messages we should show.
	$context['contact']['previous'] = [];

	$request = $smcFunc['db']->query('', '
		SELECT mem.id_member, mem.real_name, cfr.response, cfr.time_sent
		FROM {db_prefix}contact_form_response AS cfr
			LEFT JOIN {db_prefix}members AS mem ON (cfr.id_member = mem.id_member)
		WHERE cfr.id_message = {int:msg}
		ORDER BY cfr.time_sent',
		[
			'msg' => $msg,
		]
	);
	while ($row = $smcFunc['db']->fetch_assoc($request))
	{
		$row['time_sent_format'] = timeformat($row['time_sent']);
		$context['contact']['previous'][] = $row;
	}
	$smcFunc['db']->free_result($request);

	// And set up the template.
	$context['page_title'] = $txt['contact_us'];
	$context['sub_template'] = 'admin_contact_form';

	createToken('admin-contact');
}

/**
 * Reply to an actual message.
 */
function ReplyContact()
{
	global $context, $txt, $smcFunc, $sourcedir, $language;

	isAllowedTo('admin_forum');
	checkSession();
	validateToken('admin-contact');

	$msg = isset($_POST['msg']) ? (int) $_POST['msg'] : 0;

	$message = !empty($_POST['reply']) ? StringLibrary::htmltrim(StringLibrary::escape($_POST['reply'], ENT_QUOTES)) : '';

	$request = $smcFunc['db']->query('', '
		SELECT cf.id_message, mem.id_member, COALESCE(mem.real_name, cf.contact_name) AS member_name,
			COALESCE(mem.email_address, cf.contact_email) AS member_email, cf.subject, cf.message, cf.time_received, cf.status
		FROM {db_prefix}contact_form AS cf
			LEFT JOIN {db_prefix}members AS mem ON (cf.id_member = mem.id_member)
		WHERE cf.id_message = {int:msg}',
		[
			'msg' => $msg,
		]
	);
	if ($smcFunc['db']->num_rows($request) == 0)
	{
		$smcFunc['db']->free_result($request);
		fatal_lang_error('contact_form_message_not_found', false);
	}
	$context['contact'] = $smcFunc['db']->fetch_assoc($request);
	$smcFunc['db']->free_result($request);

	// Nothing entered?
	if (empty($message))
	{
		session_flash('warning', $txt['contact_form_no_reply']);
		redirectexit('action=admin;area=contactform;sa=viewcontact;msg=' . $msg);
	}

	// Insert it into the database.
	$smcFunc['db']->insert('insert',
		'{db_prefix}contact_form_response',
		['id_message' => 'int', 'id_member' => 'int', 'response' => 'string', 'time_sent' => 'int'],
		[$msg, $context['user']['id'], $message, time()],
		['id_response']
	);

	// Update the message to be sent.
	$smcFunc['db']->query('', '
		UPDATE {db_prefix}contact_form
		SET status = 1
		WHERE status = 0
			AND id_message = {int:msg}',
		[
			'msg' => $msg,
		]
	);

	// Send the original recipient an email.
	require_once($sourcedir . '/Subs-Post.php');
	$replacements = [
		'NAME' => $context['contact']['member_name'],
		'MSGSUBJECT' => $context['contact']['subject'],
		'MSGRESPONSE' => $message,
	];

	$emaildata = loadEmailTemplate('contact_form_response', $replacements, $language);
	StoryBB\Helper\Mail::send($context['contact']['member_email'], $emaildata['subject'], $emaildata['body'], null, 'contactform' . $context['contact']['id_message'], $emaildata['is_html']);

	// Clear any pending alerts.
	$alerted = StoryBB\Model\Alert::find_alerts([
		'content_type' => 'contactform',
		'content_id' => $msg,
		'content_action' => 'received',
		'is_read' => 0
	]);
	if (!empty($alerted))
	{
		foreach ($alerted as $memID => $alerts)
		{
			StoryBB\Model\Alert::change_read($memID, $alerts, 1);
		}
	}

	// Return to the admin.
	session_flash('success', $txt['contact_form_reply_sent']);
	redirectexit('action=admin;area=contactform;sa=viewcontact;msg=' . $msg);
}
