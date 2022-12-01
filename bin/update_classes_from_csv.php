<?php

require 'autoload.php';

use Opencontent\Opendata\Api\TagRepository;
use Opencontent\Opendata\Api\Structs\TagTranslationStruct;
use Opencontent\Opendata\Api\Structs\TagSynonymStruct;
use Symfony\Component\Yaml\Yaml;

$script = eZScript::instance([
    'description' => "Update calss and attribute translations from csv",
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
    'debug-timing' => true,
]);

$script->startup();
$options = $script->getOptions(
    '[language:]',
    '',
    [
        'language' => 'language code (e.g. ita-IT,ger-DE,eng-GB)',
    ]
);
$script->initialize();
$cli = eZCLI::instance();
$output = new ezcConsoleOutput();

$language = $options['language'];
if (empty($language)) {
    $cli->error('Missing argiment language');
    $script->shutdown();
    exit();
}
$googleSpreadsheetUrl = 'https://docs.google.com/spreadsheets/d/1Sr21vupXSjru__6NfteiFbM6kmarNhyETzYgjSp_ngc';

$question = ezcConsoleQuestionDialog::YesNoQuestion(
    $output,
    "Utilizzo il Google Spreadsheet predefinito: $googleSpreadsheetUrl",
    "y"
);

if (ezcConsoleDialogViewer::displayDialog($question) == "n") {
    $opts = new ezcConsoleQuestionDialogOptions();
    $opts->text = "Inserisci l'url del Google Spreadsheet";
    $opts->showResults = true;
    $question = new ezcConsoleQuestionDialog($output, $opts);
    $googleSpreadsheetUrl = ezcConsoleDialogViewer::displayDialog($question);
}

$googleSpreadsheetTemp = explode(
    '/',
    str_replace('https://docs.google.com/spreadsheets/d/', '', $googleSpreadsheetUrl)
);
$googleSpreadsheetId = array_shift($googleSpreadsheetTemp);

$sheet = new \Opencontent\Google\GoogleSheet($googleSpreadsheetId);
$sheets = $sheet->getSheetTitleList();

$menu = new ezcConsoleMenuDialog($output);
$menu->options = new ezcConsoleMenuDialogOptions();
$menu->options->text = "Please choose a possibility:\n";
$menu->options->validator = new ezcConsoleMenuDialogDefaultValidator($sheets);
$choice = ezcConsoleDialogViewer::displayDialog($menu);

$sheetName = $sheets[$choice];
$csv = $sheet->getSheetDataHash($sheetName);

foreach ($csv as $item) {
    if (!isset($item['context'])) {
        $cli->error('context not found');
        continue;
    }

    $context = $item['context'];
    $context = str_replace('classes/', '', $context);
    $context = str_replace('.yml', '', $context);
    $parts = explode('/', $context);

    $cli->output($item['context'] . ' ', false);
    if (!isset($item[$language]) || empty($item[$language])) {
        $cli->error('translation not found');
        continue;
    }

    $property = false;

    $classIdentifier = trim($parts[0]);
    if (count($parts) === 2) {
        $attributeIdentifier = false;
        $property = trim($parts[1]);
    } else {
        $attributeIdentifier = trim($parts[1]);
        $property = trim($parts[2]);
    }

    $class = eZContentClass::fetchByIdentifier($classIdentifier);
    if (!$class instanceof eZContentClass) {
        $cli->error('class not found');
        continue;
    }

    if ($attributeIdentifier) {
        $dataMap = $class->dataMap();
        if (!isset($dataMap[$attributeIdentifier])) {
            $cli->error('attribute not found');
            continue;
        }
        $entity = $dataMap[$attributeIdentifier];
    } else {
        $entity = $class;
    }

    if ($property === 'serialized_name_list') {
        $entity->setName($item[$language], $language);
        $entity->store();
        $cli->warning('update name ' . get_class($entity) . ' in ' . $item[$language]);

//        $initialLanguageID = eZContentLanguage::idByLocale('ita-IT');
//        $class->setAttribute('initial_language_id', $initialLanguageID);
//        $class->setAlwaysAvailableLanguageID($initialLanguageID);

    } elseif ($property === 'serialized_description_list') {
        $entity->setDescription($item[$language], $language);
        $entity->store();
        $cli->warning('update description ' . get_class($entity) . ' in ' . $item[$language]);

//        $initialLanguageID = eZContentLanguage::idByLocale('ita-IT');
//        $class->setAttribute('initial_language_id', $initialLanguageID);
//        $class->setAlwaysAvailableLanguageID($initialLanguageID);

    } else {
        $cli->warning('property ' . $property . '  not found ' . implode('.', $parts));
    }
}

eZCache::clearAll();

$script->shutdown();