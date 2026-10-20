# Review Reminder Plugin

Other languages: [Português do Brasil](README.pt_BR.md) · [Español](README.es.md)

[![OJS compatibility](https://img.shields.io/badge/OJS-3.5.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_5_0)
[![GitHub release](https://img.shields.io/github/v/release/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/releases)
[![License type](https://img.shields.io/github/license/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/blob/main/LICENSE)

This README describes the version for **OJS 3.5.0.x**. The plugin adds two features for reviewers:

| Feature | When | What the reviewer receives |
| --- | --- | --- |
| Calendar attachment | Review invitation (including a subsequent round) or manual reminder sent by an editor | An `invite.ics` file attached to the OJS email itself |
| Weekly reminder | Mondays, 00:00 UTC | One email per journal listing pending reviews |

Both features are enabled, per journal, by enabling the plugin. The weekly reminder can be turned off in the plugin's **Settings**, in **Website > Plugins > Installed Plugins**, without affecting the calendar attachment. The weekly reminder's day and time cannot be changed.

## Weekly reminder

Example of the email, sent in the journal's primary language with the journal's primary contact as sender:

> **Subject:** Pending Reviews Reminder
>
> Dear Maria Silva,
>
> The manuscripts below are awaiting your review.
>
> [Submission title A](#) - Due date: 2026-11-25<br>
> [Submission title B](#) - Due date: 2026-12-02
>
> This is an automated email. If you have already submitted your review for these manuscripts, please disregard this email.

- Each title links to the review page, which asks the reviewer to sign in. The email has no calendar attachment.
- The email text comes from the plugin and is not listed in the journal's email templates, so it cannot be edited there.
- It lists reviews in the latest round of submissions still active in the editorial workflow, for which the reviewer has been notified and that are not completed, declined, or cancelled. This includes unanswered invitations and overdue reviews.
- Reviewers without pending reviews and disabled accounts receive no email. Reviewers with pending reviews in several journals receive one email from each journal.
- Each sending is recorded in the submission's *Activity Log* and shown in the review's *History*, in a block separate from OJS reminders. The review's own reminder date is not changed.

The weekly reminder is independent of OJS automatic reminders, which keep working as configured. A reviewer may receive both.

### Scheduling

Sending relies on the OJS 3.5 task scheduler and is scheduled for Mondays at 00:00 UTC. Configure the cron job recommended by OJS on the server:

```
* * * * * cd /path/to/ojs && php lib/pkp/tools/scheduler.php run >> /dev/null 2>&1
```

Without cron, OJS uses its built-in runner (`task_runner` in `config.inc.php`), which only runs at the end of a site request. In that case the reminder is sent only if a request happens during that minute; otherwise, that week's sending does not happen.

## Calendar attachment

The `invite.ics` file contains an event with the journal name, the submission title, and a link to the review page. The event runs from the email sending until 23:59:59 on the **review completion deadline**, in the time zone of the reviewer's calendar app.

Importing the event is optional and does not accept the invitation. The plugin sets no alarms and does not update the event if the deadline changes. The attachment is not added to OJS automatic overdue reminders.

## Installation

> [!IMPORTANT]
> The OJS 3.5 version is not yet available in the Plugin Gallery.

1. Download the `.tar.gz` package for OJS 3.5 from the [Releases page](https://github.com/lepidus/reviewReminder/releases).
2. In **Website > Plugins > Installed Plugins**, use **Upload a New Plugin** to upload the package.
3. Enable **Review Reminder** in each journal that needs it.
4. Make sure OJS can send emails and that the scheduler is configured.

Optionally, enable **One-click Reviewer Access** in **Workflow > Review > Setup**. The attachment then uses the same one-click link as the OJS email, so it opens the review without signing in. Links in the weekly reminder always ask the reviewer to sign in.

![How to enable One-click Reviewer Access](https://i.imgur.com/cHjoXsI.gif)

Disabling the plugin in a journal stops adding the attachment to future emails and removes the journal from future weekly reminders. Reviews, deadlines, and already imported events are not changed.

## Credits

Sponsored by the [South African Medical Association](http://samedical.org/). Developed by [Lepidus Tecnologia](https://lepidus.com.br/).

## License

__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2024-2026 Lepidus Tecnologia__
