<?php
require 'autoload.php';

use Opencontent\I18n\TsParser;

$script = eZScript::instance(['description' => "From ts to csv",
    'use-session' => false,
    'use-modules' => false,
    'debug-timing' => true
]);

$script->startup();
$options = $script->getOptions(
    '[source:]',
    '',
    ['source' => 'ts source file path']
);
$script->initialize();
$cli = eZCLI::instance();

try {

    $tsParser = new TsParser($options['source']);
    $filename = $tsParser->toCSV();

    $cli->warning($filename);

} catch (Exception $e) {
    $cli->error($e->getMessage());
}

$script->shutdown();
