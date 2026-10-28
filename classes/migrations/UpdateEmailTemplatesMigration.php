<?php

namespace APP\plugins\generic\reviewReminder\classes\migrations;

use APP\facades\Repo;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateEmailTemplatesMigration extends Migration
{
    private const REMOVED_TEMPLATE_KEYS = ['REVIEW_REMINDER'];

    public function up(): void
    {
        $this->removeTemplates(self::REMOVED_TEMPLATE_KEYS);

        $plugin = new ReviewReminderPlugin();
        $plugin->pluginPath = 'plugins/generic/reviewReminder';
        $emailLocales = $this->getEmailLocales($plugin);

        $plugin->addLocaleData();
        Repo::emailTemplate()->dao->installEmailTemplates(
            $plugin->getInstallEmailTemplatesFile(),
            $emailLocales
        );
    }

    private function removeTemplates(array $templateKeys): void
    {
        $customizedTemplateIds = DB::table('email_templates')->whereIn('email_key', $templateKeys)->pluck('email_id');
        DB::table('email_templates_settings')->whereIn('email_id', $customizedTemplateIds)->delete();
        DB::table('email_templates')->whereIn('email_id', $customizedTemplateIds)->delete();
        DB::table('email_templates_default_data')->whereIn('email_key', $templateKeys)->delete();
    }

    private function getEmailLocales($plugin): array
    {
        $pluginLocalesDirectory = $plugin->getPluginPath() . '/locale/';
        $emailLocales = [];
        $localeDirectories = scandir($pluginLocalesDirectory);

        foreach ($localeDirectories as $directory) {
            if ($directory !== '.' && $directory !== '..' && is_dir($pluginLocalesDirectory . $directory)) {
                $emailsPoFile = $pluginLocalesDirectory . $directory . '/emails.po';
                if (file_exists($emailsPoFile)) {
                    $emailLocales[] = $directory;
                }
            }
        }

        return $emailLocales;
    }
}
