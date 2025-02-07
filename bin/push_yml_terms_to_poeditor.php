<?php

require 'autoload.php';

use Opencontent\I18n\CliTools;
use Opencontent\I18n\PoEditorClient;
use Opencontent\I18n\PoEditorTools;
use Opencontent\I18n\TsParser;
use Symfony\Component\Yaml\Yaml;

$script = eZScript::instance([
    'description' => "Update ts file from poeditor",
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
    'debug-timing' => true,
]);

$script->startup();
$options = $script->getOptions(
    '[token:][language:][installer_directory:]',
    '',
    [
        'token' => 'api token',
        'language' => 'language',
        'installer_directory' => 'installer data',
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

$token = $options['token'] ?? CliTools::ask(
    "Inserisci il token api PoEditor (lo trovi qui: https://poeditor.com/account/api)"
);
if (empty($token)) {
    $cli->error('Missing token');
    $script->shutdown();
    exit();
}

$installerDirectory = $options['installer_directory'] ?? CliTools::ask('Inserisci il percorso dell\'installer');
$installerDirectory = rtrim($installerDirectory, '/');

$client = (new PoEditorClient($token));

function syncTermsAndItalianTranslations($client, $installerDirectory, $projectName, $path, $dryRun = false, $skipDelete = false)
{
    global $cli;

    $cli->warning(strtoupper($projectName));

    $project = PoEditorTools::selectProject($client, $projectName);
    $poeData = [];
    $terms = $client->getTerms($project['id']);
    foreach ($terms as $term) {
        $poeData[trim($term['context'], '"')][] = $term['term'];
    }
    $localData = Yaml::parseFile($installerDirectory . $path . '/it.yml');
    $addTermList = [];
    foreach ($localData as $context => $terms) {
        if (!isset($poeData[$context])) {
            foreach (array_keys($terms) as $term) {
                if (!empty($terms[$term])) {
                    $cli->output(sprintf('[%s] %s', $context, $term));
                    $addTermList[] = [
                        'term' => $term,
                        'context' => $context,
                    ];
                }
            }
        } else {
            $diff = array_diff(array_keys($terms), $poeData[$context]);
            if (!empty($diff)) {
                foreach ($diff as $term) {
                    if (!empty($terms[$term])) {
                        $cli->output(sprintf('[%s] %s', $context, $term));
                        $addTermList[] = [
                            'term' => $term,
                            'context' => '"' . $context . '"',
                        ];
                    }
                }
            }
        }
    }
    if (!empty($addTermList)) {
        if (!$dryRun && CliTools::yesNo('Aggiorno poeditor con i nuovi vocaboli? ' . count($addTermList))) {
            $response = $client->addTerms($project['id'], $addTermList);
            print_r($response);
        }
    }
    $language = 'it';
    $poeData = [];
    $terms = $client->getTerms($project['id'], $language);
    foreach ($terms as $term) {
        $poeData[trim($term['context'], '"')][$term['term']] = $term['translation']['content'];
    }
    $deleteTermsList = [];
    $addTranslationList = [];
    $updateTranslationList = [];
    foreach ($localData as $context => $terms) {
        foreach ($terms as $term => $translation) {
            if (!empty($translation)) {
                if (empty($poeData[$context][$term])) {
                    $cli->output(sprintf('[%s] %s -> %s', $context, $term, $translation));
                    $addTranslationList[] = [
                        'term' => $term,
                        'context' => '"' . $context . '"',
                        'translation' => [
                            'content' => $translation,
                        ],
                    ];
                } elseif ($poeData[$context][$term] !== $translation) {
                    $cli->warning(
                        sprintf(
                            '[%s] %s -> locale: <%s> - poeditor: <%s>',
                            $context,
                            $term,
                            $translation,
                            $poeData[$context][$term]
                        )
                    );
                    $updateTranslationList[] = [
                        'term' => $term,
                        'context' => '"' . $context . '"',
                        'translation' => [
                            'content' => $translation,
                        ],
                    ];
                }
            } elseif (isset($poeData[$context][$term]) && !$skipDelete) {
                $cli->error(sprintf('[%s] %s -> %s', $context, $term, $translation));
                $deleteTermsList[] = [
                    'term' => $term,
                    'context' => '"' . $context . '"',
                ];
            }
        }
    }
    if (!empty($addTranslationList)) {
        if (!$dryRun && CliTools::yesNo('Aggiorno poeditor con le nuove traduzioni? ' . count($addTranslationList))) {
            $response = $client->addTranslations($project['id'], $language, $addTranslationList);
            print_r($response);
        }
    }
    if (!empty($updateTranslationList)) {
        if (!$dryRun && CliTools::yesNo('Aggiorno poeditor con le traduzioni modificate? ' . count($updateTranslationList))) {
            $response = $client->updateTranslations($project['id'], $language, $updateTranslationList);
            print_r($response);
        }
    }
    if (!empty($deleteTermsList) && !$skipDelete) {
        if (!$dryRun && CliTools::yesNo('Rimuovo da poeditor i termini senza traduzioni in italiano? ' . count($deleteTermsList))) {
            $response = $client->deleteTerms($project['id'], $deleteTermsList);
            print_r($response);
        }
    }
}

$dryRun = false;

syncTermsAndItalianTranslations($client, $installerDirectory, 'Opencity Italia - CMS - content-types', '/_translations/content-types', $dryRun);
$cli->output();
syncTermsAndItalianTranslations($client, $installerDirectory, 'Opencity Italia - CMS - contents', '/_translations/contents', $dryRun);
$cli->output();
syncTermsAndItalianTranslations($client, $installerDirectory, 'Opencity Italia - CMS - tags', '/_translations/tags', $dryRun, true);
$cli->output();


$script->shutdown();