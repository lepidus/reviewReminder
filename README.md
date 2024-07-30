# Review Reminder Plugin 

[![OJS compatibility](https://img.shields.io/badge/ojs-3.4.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_4_0)
[![GitHub release](https://img.shields.io/github/v/release/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/releases)
[![License type](https://img.shields.io/github/license/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/blob/main/LICENSE)

This plugin sends a reminder to the reviewer's email address when they are assigned to a submission. The reminder informs them of the review period, which can be added to major digital calendars.

## Installation

1. Enter the administration area of ​​your OJS website through the __Dashboard__.
2. Navigate to `Settings`>` Website`> `Plugins`> `Upload a new plugin`.
3. Under __Upload file__ select the file __reviewReminder.tar.gz__.
4. Click __Save__ and the plugin will be installed on your website.

## Time Zone

The time zone of the evaluation deadline is according to the OJS.
This setting is defined in the `config.inc.php` file, in the `time_zone` field.

## Configure the One-click Reviewer Access

If it is not already enabled, you can go to `Workflow > Review > Setup` to enable a secure link in the email invitation to reviewers. This will allow the reviewer to receive a direct link to the assigned review.

![Video Tutorial](https://i.imgur.com/cHjoXsI.gif)

# Credits
This plugin was sponsored by the [South African Medical Association](http://samedical.org/).

Developed by [Lepidus Tecnologia](https://lepidus.com.br/).


# License
__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2024 Lepidus Tecnologia__