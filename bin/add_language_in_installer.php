<?php
require 'autoload.php';

use Opencontent\I18n\TsParser;

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

    $installerDirectory = rtrim($options['installer_directory'], '/');

} catch (Exception $e) {
    $cli->error($e->getMessage());
    $cli->output($e->getTraceAsString());
}

$script->shutdown();
