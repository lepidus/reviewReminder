<?php

namespace APP\plugins\generic\reviewReminder\classes\migrations;

use APP\facades\Repo;
use APP\plugins\generic\reviewReminder\ReviewReminderPlugin;
use Illuminate\Database\Migrations\Migration;

class UpdateEmailTemplatesMigration extends Migration
{
    public function up(): void
    {
        $plugin = new ReviewReminderPlugin();
        $plugin->pluginPath = 'plugins/generic/reviewReminder';
        $emailLocales = $this->getEmailLocales($plugin);

        $plugin->addLocaleData();
        Repo::emailTemplate()->dao->installEmailTemplates(
            $plugin->getInstallEmailTemplatesFile(),
            $emailLocales
        );
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
