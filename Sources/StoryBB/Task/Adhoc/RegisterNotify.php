<?php

/**
 * This task handles notifying users when someone new signs up.
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
 * This task handles notifying users when someone new signs up.
 */
class RegisterNotify extends \StoryBB\Task\Adhoc
{
	/**
	 * This executes the task - loads up the information, puts the email in the queue and inserts alerts as needed.
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		global $smcFunc, $sourcedir, $modSettings, $language, $scripturl;

		// Get everyone who could be notified.
		require_once($sourcedir . '/Subs-Members.php');
		$members = membersAllowedTo('moderate_forum');

		// Having successfully figured this out, now let's get the preferences of everyone.
		require_once($sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs($members, 'member_register', true);

		// So now we find out who wants what.
		$alert_bits = [
			'alert' => 0x01,
			'email' => 0x02,
		];
		$notifies = [];

		foreach ($prefs as $member => $pref_option)
		{
			foreach ($alert_bits as $type => $bitvalue)
				if ($pref_option['member_register'] & $bitvalue)
					$notifies[$type][] = $member;
		}

		// Firstly, anyone who wants alerts.
		if (!empty($notifies['alert']))
		{
			// Alerts are relatively easy.
			$insert_rows = [];
			foreach ($notifies['alert'] as $member)
			{
				$insert_rows[] = [
					'alert_time' => $this->_details['time'],
					'id_member' => $member,
					'id_member_started' => $this->_details['new_member_id'],
					'member_name' => $this->_details['new_member_name'],
					'content_type' => 'member',
					'content_id' => 0,
					'content_action' => 'register_' . $this->_details['notify_type'],
					'is_read' => 0,
					'extra' => '',
				];
			}

			$smcFunc['db']->insert('insert',
				'{db_prefix}user_alerts',
				['alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int',
					'member_name' => 'string', 'content_type' => 'string', 'content_id' => 'int',
					'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'],
				$insert_rows,
				['id_alert']
			);

			// And update the count of alerts for those people.
			updateMemberData($notifies['alert'], ['alerts' => '+']);
		}

		// Secondly, anyone who wants emails.
		if (!empty($notifies['email']))
		{
			// Emails are a bit complicated. We have to do language stuff.
			require_once($sourcedir . '/Subs-Post.php');
			require_once($sourcedir . '/ScheduledTasks.php');
			loadEssentialThemeData();

			// First, get everyone's language and details.
			$emails = [];
			$request = $smcFunc['db']->query('', '
				SELECT id_member, lngfile, email_address
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:members})',
				[
					'members' => $notifies['email'],
				]
			);
			while ($row = $smcFunc['db']->fetch_assoc($request))
			{
				if (empty($row['lngfile']))
					$row['lngfile'] = $language;
				$emails[$row['lngfile']][$row['id_member']] = $row['email_address'];
			}
			$smcFunc['db']->free_result($request);

			// Second, iterate through each language, load the relevant templates and set up sending.
			foreach ($emails as $this_lang => $recipients)
			{
				$replacements = [
					'USERNAME' => $this->_details['new_member_name'],
					'PROFILELINK' => $scripturl . '?action=profile;u=' . $this->_details['new_member_id']
				];
				$emailtype = 'admin_notify';

				// If they need to be approved add more info...
				if ($this->_details['notify_type'] == 'approval')
				{
					$replacements['APPROVALLINK'] = $scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve';
					$emailtype .= '_approval';
				}

				$emaildata = loadEmailTemplate($emailtype, $replacements, empty($modSettings['userLanguage']) ? $language : $this_lang);

				// And do the actual sending...
				foreach ($recipients as $email_address)
					Mail::send($email_address, $emaildata['subject'], $emaildata['body'], null, 'newmember' . $this->_details['new_member_id'], $emaildata['is_html'], 0);
			}
		}

		// And now we're all done.
		return true;
	}
}
