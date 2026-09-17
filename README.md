# Review Reminder Plugin

Other languages:

- [Português do Brasil](README.pt_BR.md)
- [Español](README.es.md)

[![OJS compatibility](https://img.shields.io/badge/OJS-3.5.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_5_0)
[![GitHub release](https://img.shields.io/github/v/release/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/releases)
[![License type](https://img.shields.io/github/license/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/blob/main/LICENSE)

Review Reminder adds two features to **OJS 3.5.0.x**:

| Feature | When it happens | What the reviewer receives |
| --- | --- | --- |
| Calendar attachment | OJS sends a review invitation (including a subsequent round) or an editor sends a manual review reminder | An `invite.ics` file attached to the OJS email |
| Weekly reminder | Every Monday, through the OJS task scheduler | One email per journal listing the reviewer's incomplete reviews, their due dates, and links |

This README describes the OJS 3.5 version. Earlier versions may send a separate calendar reminder email and use a different scheduling mechanism.

## Calendar attachment

The plugin attaches an iCalendar (`.ics`) file to the existing invitation or manual reminder email. It does not send a separate email for this attachment, and the reviewer does not need to accept the invitation before receiving it.

The calendar event contains:

- The journal name.
- The submission title and a link to its review page in the event description.
- A period starting when the attachment is generated and ending on the review due date, at 23:59:59.

The event uses the **review completion deadline**, not the deadline for accepting or declining the invitation. These details are stored in the attachment; the plugin does not add an explanation of the review period to the email body.

To use it, open or import `invite.ics` in a calendar application that supports iCalendar. Importing the event is optional and does not accept the review invitation or submit a review. Calendar notifications depend on the reviewer's calendar settings; the plugin does not define an advance alarm or synchronize later deadline changes with imported events.

### Time zone

The plugin uses the OJS time zone configured under `[general]`, in the `time_zone` setting of `config.inc.php`. Calendar applications may display the event according to their own time zone settings.

### Links and reviewer access

The calendar event includes a link to the review page even when one-click reviewer access is disabled. The reviewer may need to sign in to OJS to open that page.

One-click reviewer access is an OJS setting, not a requirement for this plugin. The plugin uses the review URL supplied by OJS, with a regular review-page URL as a fallback. It does not create access tokens or bypass OJS authentication. The weekly email also uses regular review-page links, which may require signing in.

## Weekly reminder

Each weekly email lists the submission titles, review due dates, and review-page links for **one reviewer in one journal**. Reviewers with pending reviews in multiple enabled journals receive a separate email for each journal. Reviewers without eligible pending reviews receive no weekly email.

Eligible reviews are those for which the reviewer has been notified, in the latest review round and current review stage, that have not been completed, declined, or cancelled. The submission must still be active in the editorial workflow. An invitation awaiting the reviewer's response can therefore appear in the list.

The list includes both overdue reviews and reviews whose deadlines are still in the future. Sending is weekly; it is not triggered a fixed number of days before an invitation or review deadline. The weekly email uses the `PENDING_REVIEWS_REMINDER` template and does not include a calendar attachment.

### Scheduled tasks and cron

The plugin registers one weekly task with the **OJS 3.5 task scheduler**, scheduled for Mondays. It does not install a separate operating-system cron job.

The OJS scheduler must be running for weekly emails to be sent. OJS can run scheduled tasks through its built-in task runner or through a server cron job invoking the OJS scheduler. The built-in runner depends on web requests, so execution can be delayed when there is no site traffic.

If the server already runs the OJS scheduler, no additional cron entry is needed for this plugin.

## Relationship to OJS reminders

OJS has its own automatic reminders for overdue invitation responses and overdue reviews. Their settings and sending remain controlled by OJS. This plugin adds the weekly summary independently, so a reviewer may receive both an OJS automatic reminder and a plugin weekly email.

The plugin's calendar attachment applies to invitations and **manual** review reminders. It is not added to OJS's automatic overdue reminders.

## Installation and activation

> [!IMPORTANT]
> The Review Reminder version for **OJS 3.5 is not yet available in the Plugin Gallery**. Install it manually using a compatible release package.

1. Visit the [Releases page](https://github.com/lepidus/reviewReminder/releases) and download the plugin's `.tar.gz` package for **OJS 3.5**. Check the release's compatibility information before downloading.
2. In the journal dashboard, open **Website > Plugins > Installed Plugins**, select **Upload a New Plugin**, and upload the downloaded package.
3. Locate **Review Reminder** in **Installed Plugins** and enable it for the journal.
4. Ensure that OJS email delivery works and that its task scheduler is running if you want weekly reminders.

In an installation hosting multiple journals, enable the plugin separately in each journal that needs it. Enabling the plugin activates both features; it has no separate settings to enable only calendar attachments or only weekly emails.

## Configure the One-click Reviewer Access

To let reviewers access their assigned reviews through a secure link in the OJS invitation email, go to **Workflow > Review > Setup** and enable **One-click Reviewer Access**, if it is not already enabled.

This setting is optional. The plugin can attach calendar events and send weekly reminders without it. Enabling it does not make every link in calendar attachments or weekly reminders a one-click access link; regular review-page links may still require signing in.

![Tutorial showing how to enable One-click Reviewer Access](https://i.imgur.com/cHjoXsI.gif)

## What happens if the plugin is disabled?

Disabling Review Reminder for a journal stops adding calendar attachments to future invitation and manual reminder emails, and excludes that journal from future weekly summaries.

OJS continues to manage review assignments, deadlines, invitations, manual reminders, and its own configured automatic reminders. Disabling the plugin does not delete submissions or reviews, change deadlines, or remove events that reviewers have already imported into their calendars.

## Credits
This plugin was sponsored by the [South African Medical Association](http://samedical.org/).

Developed by [Lepidus Tecnologia](https://lepidus.com.br/).


## License
__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2024-2026 Lepidus Tecnologia__
