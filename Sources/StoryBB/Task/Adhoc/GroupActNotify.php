<?php

/**
 * This taks handles notifying someone that their request to join a group has been decided.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Task\Adhoc;

use StoryBB\Helper\Mail;

/**
 * This taks handles notifying someone that their request to join a group has been decided.
 */
class GroupActNotify extends \StoryBB\Task\Adhoc
{
	/**
	 * This executes the task - loads up the information, puts the email in the queue and inserts alerts as needed.
	 * @return bool Always returns true
	 */
	public function execute()
	{
		global $sourcedir, $smcFunc, $language, $modSettings;

		// Get the details of all the members concerned...
		$request = $smcFunc['db']->query('', '
			SELECT lgr.id_request, lgr.id_member, lgr.id_group, mem.email_address,
				mem.lngfile, mem.member_name,  mg.group_name
			FROM {db_prefix}log_group_requests AS lgr
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
				INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
			WHERE lgr.id_request IN ({array_int:request_list})
			ORDER BY mem.lngfile',
			[
				'request_list' => $this->_details['request_list'],
			]
		);
		$affected_users = [];
		$members = [];
		$alert_rows = [];
		while ($row = $smcFunc['db']->fetch_assoc($request))
		{
			$members[] = $row['id_member'];
			$row['lngfile'] = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];

			// If we are approving, add them!
			if ($this->_details['status'] == 'approve')
			{
				// Hack in blank permissions so that allowedTo() will fail.
				require_once($sourcedir . '/Security.php');
				$user_info['permissions'] = [];

				// For the moddlog
				$user_info['id'] = $this->_details['member_id'];
				$user_info['ip'] = $this->_details['member_ip'];

				require_once($sourcedir . '/Subs-Membergroups.php');
				addMembersToGroup($row['id_member'], $row['id_group'], 'auto', true);
			}

			// Build the required information array
			$affected_users[] = [
				'rid' => $row['id_request'],
				'member_id' => $row['id_member'],
				'member_name' => $row['member_name'],
				'group_id' => $row['id_group'],
				'group_name' => $row['group_name'],
				'email' => $row['email_address'],
				'language' => $row['lngfile'],
			];
		}
		$smcFunc['db']->free_result($request);

		// Ensure everyone who is online gets their changes right away.
		updateSettings(['settings_updated' => time()]);

		if (!empty($affected_users))
		{
			require_once($sourcedir . '/Subs-Notify.php');
			$prefs = getNotifyPrefs($members, ['groupr_approved', 'groupr_rejected'], true);

			// They are being approved?
			if ($this->_details['status'] == 'approve')
			{
				$pref_name = 'approved';
				$email_template_name = 'mc_group_approve';
				$email_message_id_prefix = 'grpapp';
			}
			// Otherwise, they are getting rejected (With or without a reason).
			else
			{
				$pref_name = 'rejected';
				$email_template_name = empty($custom_reason) ? 'mc_group_reject' : 'mc_group_reject_reason';
				$email_message_id_prefix = 'grprej';
			}

			// Same as for approving, kind of.
			foreach ($affected_users as $user)
			{
				$pref = !empty($prefs[$user['member_id']]['groupr_' . $pref_name]) ? $prefs[$user['member_id']]['groupr_' . $pref_name] : 0;
				$custom_reason = isset($this->_details['reason']) && isset($this->_details['reason'][$user['rid']]) ? $this->_details['reason'][$user['rid']] : '';

				if ($pref & 0x01)
				{
					$alert_rows[] = [
						'alert_time' => time(),
						'id_member' => $user['member_id'],
						'content_type' => 'groupr',
						'content_id' => 0,
						'content_action' => $pref_name,
						'is_read' => 0,
						'extra' => json_encode(['group_name' => $user['group_name'], 'reason' => !empty($custom_reason) ? '<br><br>' . $custom_reason : '']),
					];
					updateMemberData($user['member_id'], ['alerts' => '+']);
				}

				if ($pref & 0x02)
				{
					// Emails are a bit complicated. We have to do language stuff.
					require_once($sourcedir . '/Subs-Post.php');
					require_once($sourcedir . '/ScheduledTasks.php');
					loadEssentialThemeData();

					$replacements = [
						'USERNAME' => $user['member_name'],
						'GROUPNAME' => $user['group_name'],
					];

					if (!empty($custom_reason))
						$replacements['REASON'] = $custom_reason;

					$emaildata = loadEmailTemplate($email_template_name, $replacements, $user['language']);

					Mail::send($user['email'], $emaildata['subject'], $emaildata['body'], null, $email_message_id_prefix . $user['rid'], $emaildata['is_html'], 2);
				}
			}

			// Insert the alerts if any
			if (!empty($alert_rows))
				$smcFunc['db']->insert('',
					'{db_prefix}user_alerts',
					[
						'alert_time' => 'int', 'id_member' => 'int', 'content_type' => 'string',
						'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string',
					],
					$alert_rows,
					[]
				);
		}

		return true;
	}
}
