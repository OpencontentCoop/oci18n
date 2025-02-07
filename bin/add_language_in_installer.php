<?php
require 'autoload.php';

use Opencontent\I18n\CreateInstallerTranslation;

$script = eZScript::instance(['description' => "",
    'use-session' => false,
    'use-modules' => false,
    'debug-timing' => true
]);

$script->startup();
$options = $script->getOptions(
    '[language:][installer_directory:]',
    '',
    [
        'language' => 'language',
        'installer_directory' => 'installer data',
    ]
);
$script->initialize();
$cli = eZCLI::instance();

try {

    $language = $options['language'];
    if (empty($language)) {
        $cli->error('Missing language');
        $script->shutdown();
        exit();
    }
    $installerDirectory = rtrim($options['installer_directory'], '/');
    if (empty($installerDirectory)) {
        $cli->error('Missing installer_directory');
        $script->shutdown();
        exit();
    }

    $modules = eZDir::findSubitems($installerDirectory . '/modules', 'd');

    $createTool = new CreateInstallerTranslation([$language]);
    $createTool->parseInstaller($installerDirectory);

    foreach ($modules as $module) {
        eZCLI::instance()->warning('contents/' . $installerDirectory . '/modules/' . $module);
        $createTool->parseInstaller($installerDirectory . '/modules/' . $module);
    }

} catch (Exception $e) {
    $cli->error($e->getMessage());
    $cli->output($e->getTraceAsString());
}

$script->shutdown();
