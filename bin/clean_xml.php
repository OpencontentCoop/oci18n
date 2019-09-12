<?php
require 'autoload.php';

use Opencontent\I18n\TsParser;

$script = eZScript::instance(['description' => "Clean xsm file",
    'use-session' => false,
    'use-modules' => false,
    'debug-timing' => true
]);

$script->startup();
$options = $script->getOptions(
    '[source:]',
    '',
    [
        'source' => 'percorso del file extension/*/translations/*/translation.ts',
    ]
);
$script->initialize();
$cli = eZCLI::instance();

try {

    $tsParser = new TsParser($options['source']);
    $domDocument = $tsParser->getDOMDocument($translationData);

    /** @var DOMDocument $domDocument */
    $domDocument->preserveWhiteSpace = false;
    $domDocument->formatOutput = true;
    $domDocument->save($options['source']);

} catch (Exception $e) {
    $cli->error($e->getMessage());
}

$script->shutdown();
