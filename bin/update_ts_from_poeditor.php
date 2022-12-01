<?php

require 'autoload.php';

use Opencontent\I18n\PoEditorClient;
use Opencontent\I18n\PoEditorTools;

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
$output = new ezcConsoleOutput();

$token = $options['token'];
if (empty($token)) {
    $cli->error('Missing token');
    $script->shutdown();
    exit();
}

$language = $options['language'];
if (empty($language)) {
    $cli->error('Missing language');
    $script->shutdown();
    exit();
}

$tag = $options['tag'];
if (empty($tag)) {
    $cli->error('Missing tag');
    $script->shutdown();
    exit();
}

if ($tag !== 'kernel' && !is_dir("./extension/$tag")){
    $cli->error("Extension $tag not found");
    $script->shutdown();
    exit();
}
$client = (new PoEditorClient($token));
$project = PoEditorTools::selectProject($client, 'Opencity Italia - CMS - framework');
$languages = explode(",", $language);
foreach ($languages as $language) {
    $exportUrl = $client->getExportUrl(
        $project['id'],
        $language,
        'ts',
        [$tag]
    );
    $languageCode = PoEditorClient::$languageMap[$language];
    $data = file_get_contents($exportUrl);
    if (!empty($data)) {
        $filepath = "extension/$tag/translations/$languageCode/translation.ts";
        file_put_contents($filepath, $data);
        $cli->warning("Store file $filepath");
    } else {
        $cli->error('No data found for language ' . $language);
    }
}

$script->shutdown();