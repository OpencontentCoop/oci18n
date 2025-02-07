<?php
require 'autoload.php';

use Opencontent\I18n\TsParser;

$script = eZScript::instance(['description' => "Clean xlf file",
    'use-session' => false,
    'use-modules' => false,
    'debug-timing' => true
]);

$script->startup();
$options = $script->getOptions(
    '[source:]',
    '',
    [
        'source' => 'percorso del file xlf',
    ]
);
$script->initialize();
$cli = eZCLI::instance();

try {

    $doc = new DOMDocument();
    $string = file_get_contents($options['source']);
    $doc->loadXML($string);
    /** @var DOMElement[] $targets */
    $targets = $doc->getElementsByTagName('target');
    foreach ($targets as $target) {
        $cli->output($target->nodeValue);
    }

//    /** @var DOMDocument $domDocument */
//    $domDocument->preserveWhiteSpace = false;
//    $domDocument->formatOutput = true;
//    $domDocument->save($options['source']);

} catch (Exception $e) {
    $cli->error($e->getMessage());
    $cli->output($e->getTraceAsString());
}

$script->shutdown();
