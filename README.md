# Review Reminder Plugin

Other languages: [Português do Brasil](README.pt_BR.md) · [Español](README.es.md)

[![OJS compatibility](https://img.shields.io/badge/OJS-3.4.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_4_0)
[![GitHub release](https://img.shields.io/github/v/release/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/releases)
[![License type](https://img.shields.io/github/license/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/blob/main/LICENSE)

This README describes the version for **OJS 3.4.0.x**. The plugin sends two emails to reviewers:

| Email | When | Content |
| --- | --- | --- |
| Review reminder | When the reviewer accepts a review request | Review deadline, link to the review, and an `invite.ics` calendar file |
| Weekly reminder | Mondays, through the Acron plugin | One email per journal listing pending reviews |

Both emails are enabled, per journal, by enabling the plugin. The weekly reminder can be turned off in the plugin's **Settings**, in **Website > Plugins > Installed Plugins**, without affecting the reminder on review acceptance. The weekly reminder's day cannot be changed.

Both emails have the journal's primary contact as sender. The reminder on review acceptance is sent in the language the reviewer is using in OJS, and the weekly reminder in the journal's primary language. Their texts come from the plugin and are not listed in the journal's email templates, so they cannot be edited there. Each sending is recorded in the submission's *Activity Log* and shown in the review's *History*, in a block separate from OJS reminders.

## Review reminder

Example of the email sent when the reviewer accepts the request:

> **Subject:** Review Reminder
>
> Dear Maria Silva,
>
> You have a pending review in the journal Example Journal for the article Submission title.
>
> The review deadline is 2026-11-25. You can proceed with the review through this link: [Link to review](#)
>
> You can also use the attached reminder to add this event to your preferred calendar.

The `invite.ics` file contains an event titled "Review deadline: <submission title> (<journal name>)", from the moment of acceptance until 23:59:59 on the **review completion deadline**, in the time zone of the reviewer's calendar app. The event description links to the review. When **One-click Reviewer Access** is enabled, that link opens the review without signing in. The plugin sets no alarms and does not update the event if the deadline changes.

## Weekly reminder

Example of the weekly email:

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

- Each title links to the review page, which may require signing in. The email has no calendar attachment.
- It lists reviews in the latest round of submissions still active in the editorial workflow, for which the reviewer has been notified and that are not completed, declined, or cancelled. This includes unanswered invitations and overdue reviews.
- Reviewers without pending reviews and disabled accounts receive no email. Reviewers with pending reviews in several journals receive one email from each journal.

The weekly reminder is independent of OJS automatic reminders, which keep working as configured. A reviewer may receive both.

### Scheduling

The weekly task is registered through the **Acron** plugin, which must be enabled in **Website > Plugins > Installed Plugins**. Acron runs tasks during site requests: the email goes out on the first request on Monday, in the `time_zone` set in `config.inc.php`. If a week passes without the task running, it runs on the next request, whatever the day. The first sending happens on the first request after the plugin is enabled, whatever the day.

If the server runs scheduled tasks through cron instead of Acron, add the plugin's tasks file explicitly, for example:

```
0 * * * * cd /path/to/ojs && php tools/runScheduledTasks.php plugins/generic/reviewReminder/scheduledTasks.xml
```

## Installation

Install **Review Reminder** from the Plugin Gallery in **Website > Plugins**, or download the `.tar.gz` package for OJS 3.4 from the [Releases page](https://github.com/lepidus/reviewReminder/releases) and upload it with **Upload a New Plugin**. Then enable it in each journal that needs it and make sure OJS can send emails.

Optionally, enable **One-click Reviewer Access** in **Workflow > Review > Setup**.

![How to enable One-click Reviewer Access](https://i.imgur.com/cHjoXsI.gif)

Disabling the plugin in a journal stops both emails for that journal. Reviews, deadlines, and already imported events are not changed.

## Credits

Sponsored by the [South African Medical Association](http://samedical.org/). Developed by [Lepidus Tecnologia](https://lepidus.com.br/).

## License

__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2024-2026 Lepidus Tecnologia__
