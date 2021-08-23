<?php
require 'autoload.php';

use Opencontent\Opendata\Api\TagRepository;
use Opencontent\Opendata\Api\Structs\TagTranslationStruct;
use Opencontent\Opendata\Api\Structs\TagSynonymStruct;
use Symfony\Component\Yaml\Yaml;

$script = eZScript::instance([
    'description' => "Create db translations csv from installer directory",
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
    'debug-timing' => true
]);

$script->startup();
$options = $script->getOptions(
    '[language:]',
    '',
    [
        'language' => 'language code (e.g. ita-IT,ger-DE,eng-GB)'
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
$installerDirectory = rtrim($options['installer_directory'] . '/') . '/';
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

$googleSpreadsheetTemp = explode('/',
    str_replace('https://docs.google.com/spreadsheets/d/', '', $googleSpreadsheetUrl));
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
$tagRepository = new TagRepository();

foreach ($csv as $item) {

    $url = $item['url'];
    if (strpos($item['ita-IT'], '/') !== false || strpos($item['ita-IT'], '(') !== false) {
        $url = str_replace($item['ita-IT'], '', $url);
        $url .= urlencode($item['ita-IT']);
    }

    $tagObject = eZTagsObject::fetchByUrl($url);
    $localized = false;
    if (isset($item[$language])) {
        $localized = $item[$language];
    }
    if ($tagObject instanceof eZTagsObject && $localized) {
        $cli->output($item['url'] . ' ', false);
        $cli->warning($localized . ' ', false);

        $translation = new TagTranslationStruct();
        $translation->tagId = $tagObject->attribute('id');
        $translation->locale = $language;
        $translation->keyword = $localized;
        $translation->isMainTranslation = false;
        $translation->alwaysAvailable = false;
        $translation->forceUpdate = true;
        $result = $tagRepository->addTranslation($translation);
        $cli->output($result['message']);

        for ($i = 0; $i < 5; ++$i) {
            if (isset($item[$language . '-' . $i]) && !empty($item[$language . '-' . $i])) {
                $cli->warning(' - ' . $item[$language . '-' . $i] . ' ', false);
                $synonym = new TagSynonymStruct();
                $synonym->locale = $language;
                $synonym->tagId = $tagObject->attribute('id');
                $synonym->alwaysAvailable = false;
                $synonym->keyword = $item[$language . '-' . $i];
                $result = $tagRepository->addSynonym($synonym);
                $cli->output($result['message']);
            }
        }

    } else {
        $cli->error($url . ' ', false);
        if (!$tagObject instanceof eZTagsObject) {
            $cli->error("tag not found");
        } else {
            $cli->error('translation not found');
        }
    }
}

$script->shutdown();