<?php
require 'autoload.php';

use Opencontent\I18n\TsParser;
use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;
use Google\Spreadsheet\SpreadsheetService;

$script = eZScript::instance(['description' => "Sync ts file with google spreadhsheet",
    'use-session' => false,
    'use-modules' => false,
    'debug-timing' => true
]);

$script->startup();
$options = $script->getOptions();
$script->initialize();
$cli = eZCLI::instance();
$output = new ezcConsoleOutput();

try {

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

    $serviceRequest = new DefaultServiceRequest("");
    ServiceRequestFactory::setInstance($serviceRequest);
    $spreadsheetService = new SpreadsheetService();

    $worksheetFeed = $spreadsheetService->getPublicSpreadsheet($googleSpreadsheetId);
    $feedTitle = (string)$worksheetFeed->getXml()->title;
    $entries = $worksheetFeed->getEntries();
    $sheets = array();
    foreach ($entries as $entry) {
        $sheets[] = $entry->getTitle();
    }

    $menu = new ezcConsoleMenuDialog($output);
    $menu->options = new ezcConsoleMenuDialogOptions();
    $menu->options->text = "Please choose a possibility:\n";
    $menu->options->validator = new ezcConsoleMenuDialogDefaultValidator($sheets);
    $choice = ezcConsoleDialogViewer::displayDialog($menu);

    $extensionName = $sheets[$choice];
    if (!is_dir("./extension/$extensionName")){
        throw new Exception("Extension $extensionName not installed");
    }
    $worksheet = $worksheetFeed->getByTitle($extensionName);
    $csvData = $worksheet->getCsv();

    $csv = array_map('str_getcsv', explode("\n", $csvData));
    array_walk($csv, function(&$a) use ($csv) {
        $a = array_combine($csv[0], $a);
    });
    $headers = array_shift($csv); # remove column header

    unset($headers['context']);

    $data = [];
    foreach ($csv as $item){
        $context = $item['context'];
        unset($item['context']);
        $source = $item['source'];
        unset($item['source']);
        $data['untranslated'][$context][$source] = '';
        foreach ($item as $key => $value){
            $data[$key][$context][$source] = $value;
        }
    }

    foreach ($data as $language => $values){
        $success = TsParser::storeTsFile($extensionName, $language, $values);
        if ($success){
            $cli->warning("Store file $success");
        }
    }


} catch (Exception $e) {
    $cli->error($e->getMessage());
}

$script->shutdown();
