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
    '[language:][token:][tag:]',
    '',
    [
        'language' => 'language code (e.g. it,de,fr,es,en)',
        'token' => 'api token',
        'tag' => 'tag',
    ]
);
$script->initialize();
$cli = eZCLI::instance();

$token = $options['token'] ?? CliTools::ask(
    "Inserisci il token api PoEditor (lo trovi qui: https://poeditor.com/account/api)"
);
if (empty($token)) {
    $cli->error('Missing token');
    $script->shutdown();
    exit();
}

$language = $options['language'] ?? CliTools::selectLanguage();
if (empty($language)) {
    $cli->error('Missing language');
    $script->shutdown();
    exit();
}

$tag = $options['tag'] ?? CliTools::selectExtension();
if (empty($tag)) {
    $cli->error('Missing tag');
    $script->shutdown();
    exit();
}

if (!is_dir("./extension/$tag")) {
    $cli->error("Extension $tag not found");
    $script->shutdown();
    exit();
}

$client = (new PoEditorClient($token));
$project = PoEditorTools::selectProject($client, 'Opencity Italia - CMS - framework');

$poeData = [];
$terms = $client->getTerms($project['id'], $language);
foreach ($terms as $term) {
    $poeData[$term['tags'][0]][$term['context']][$term['term']] = $term['translation']['content'];
}

$languageCode = PoEditorClient::$languageMap[$language];
$tsParser = new TsParser("./extension/$tag/translations/$languageCode/translation.ts");
$data = $tsParser->getData();
$localData = [];
$addTranslationList = [];
foreach ($data as $context => $terms) {
    if ($context === 'context'){
        continue;
    }
    foreach ($terms as $term => $translation) {
        if (empty($poeData[$tag][$context][$term])) {
            $cli->output(sprintf('[%s] %s -> %s', $context, $term, $translation));
            $addTranslationList[] = [
                'term' => $term,
                'context' => $context,
                'translation' => [
                    'content' => $translation
                ],
            ];
        }elseif ($poeData[$tag][$context][$term] !== $translation) {
            $cli->warning(sprintf('[%s] %s -> %s <--> %s', $context, $term, $translation, $poeData[$tag][$context][$term]));
        }
    }
    unset($localData[$tag]['context']); //data header
}

if (!empty($addTranslationList)) {
    if (CliTools::yesNo('Aggiorno poeditor con le nuove traduzioni?')) {
        $response = $client->addTranslations($project['id'], $language, $addTranslationList);
        print_r($response);
    }
}

$script->shutdown();