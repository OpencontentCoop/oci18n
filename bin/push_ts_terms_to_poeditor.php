<?php

require 'autoload.php';

use Opencontent\I18n\CliTools;
use Opencontent\I18n\PoEditorClient;
use Opencontent\I18n\PoEditorTools;
use Opencontent\I18n\TsParser;

$script = eZScript::instance([
    'description' => "Update ts file from poeditor",
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
    'debug-timing' => true,
]);

$script->startup();
$options = $script->getOptions(
    '[token:][tag:]',
    '',
    [
        'token' => 'api token',
        'tag' => 'tag',
    ]
);
$script->initialize();
$cli = eZCLI::instance();

$token = $options['token'] ?? CliTools::ask("Inserisci il token api PoEditor (lo trovi qui: https://poeditor.com/account/api)");
if (empty($token)) {
    $cli->error('Missing token');
    $script->shutdown();
    exit();
}

$tag = $options['tag'] ?? CliTools::selectExtension();
if (empty($tag)) {
    $cli->error('Missing tag');
    $script->shutdown();
    exit();
}

if (!is_dir("./extension/$tag")){
    $cli->error("Extension $tag not found");
    $script->shutdown();
    exit();
}

$client = (new PoEditorClient($token));
$project = PoEditorTools::selectProject($client, 'Opencity Italia - CMS - framework');

$poeData = [];
$terms = $client->getTerms($project['id']);
foreach ($terms as $term) {
    $poeData[$term['tags'][0]][$term['context']][] = $term['term'];
}

$tsParser = new TsParser("./extension/$tag/translations/untranslated/translation.ts");
$data = $tsParser->getData();
$localData = [];
foreach ($data as $context => $terms) {
    $localData[$tag][$context] = array_keys($terms);
}
unset($localData[$tag]['context']); //data header

$addTermList = [];
foreach ($localData[$tag] as $context => $terms) {
    $diff = array_diff($terms, $poeData[$tag][$context]);
    if (!empty($diff)) {
        foreach ($diff as $term) {
            $cli->output(sprintf('[%s] %s', $context, $term));
            $addTermList[] = [
                'term' => $term,
                'context' => $context,
                'tags' => [$tag],
            ];
        }
    }
}

if (!empty($addTermList)) {
    if (CliTools::yesNo('Aggiorno poeditor con i nuovi vocaboli?')) {
        $response = $client->addTerms($project['id'], $addTermList);
        print_r($response);
    }
}

$script->shutdown();