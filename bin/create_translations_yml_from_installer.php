<?php

require 'autoload.php';

use Opencontent\I18n\PoEditorClient;
use Opencontent\I18n\YmlExportableContentTypes;
use Opencontent\I18n\YmlExportableContents;
use Opencontent\I18n\YmlExportableTags;

$cli = eZCLI::instance();
$script = eZScript::instance([
    'description' => "Create yml translation from installer",
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
    'debug-timing' => true,
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

$languages = isset($options['languages']) ? explode(',', $options['languages']) : array_values(
    PoEditorClient::$languageMap
);
$installerDirectory = rtrim($options['installer_directory'], '/');

$exportContentTypes = true;
$exportContents = true;
$exportTags = true;

$modules = eZDir::findSubitems($installerDirectory . '/modules', 'd');

if ($exportContentTypes) {
    $contentTypes = new YmlExportableContentTypes($languages);
    eZCLI::instance()->warning('content-types/' . $installerDirectory);
    $contentTypes->parseInstaller($installerDirectory);
    foreach ($modules as $module) {
        eZCLI::instance()->warning('content-types/' . $installerDirectory . '/modules/' . $module);
        $contentTypes->parseInstaller($installerDirectory . '/modules/' . $module);
    }
    $contentTypes->dumpTo($installerDirectory);
}

if ($exportContents) {
    $contents = new YmlExportableContents($languages);
    eZCLI::instance()->warning('contents/' . $installerDirectory);
    $contents->parseInstaller($installerDirectory);
    foreach ($modules as $module) {
        eZCLI::instance()->warning('contents/' . $installerDirectory . '/modules/' . $module);
        $contents->parseInstaller($installerDirectory . '/modules/' . $module);
    }
    $contents->dumpTo($installerDirectory);
}

if ($exportTags) {
    $tags = new YmlExportableTags($languages);
    eZCLI::instance()->warning('tagtree_csv/' . $installerDirectory);
    $tags->parseInstaller($installerDirectory);
    foreach ($modules as $module) {
        eZCLI::instance()->warning('tagtree_csv/' . $installerDirectory . '/modules/' . $module);
        $tags->parseInstaller($installerDirectory . '/modules/' . $module);
    }
    $tags->dumpTo($installerDirectory);
}

$script->shutdown();