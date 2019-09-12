<?php
require 'autoload.php';

use Opencontent\I18n\TsParser;

$script = eZScript::instance(['description' => "Compare translation strings in old file and new file",
    'use-session' => false,
    'use-modules' => false,
    'debug-timing' => true
]);

$script->startup();
$options = $script->getOptions(
    '[old:][new:]',
    ''
);
$script->initialize();
$cli = eZCLI::instance();

try {

    $tsParserOld = new TsParser($options['old']);
    $tsParserNew = new TsParser($options['new']);

    $oldData = $tsParserOld->getStringsHash();
    $newData = $tsParserNew->getStringsHash();

    $diff = array_diff(
        array_keys($oldData),
        array_keys($newData)
    );

    foreach ($diff as $string) {
        $cli->output($oldData[$string] . ' -> ' . $string);
    }


} catch (Exception $e) {
    $cli->error($e->getMessage());
}

$script->shutdown();
