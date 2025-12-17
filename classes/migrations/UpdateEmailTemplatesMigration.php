<?php

namespace APP\plugins\generic\reviewReminder\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use PKP\plugins\PluginRegistry;
use APP\facades\Repo;

class UpdateEmailTemplatesMigration extends Migration
{
    public function up(): void
    {
        PluginRegistry::loadCategory('generic');
        $plugin = PluginRegistry::getPlugin('generic', 'reviewreminderplugin');
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
