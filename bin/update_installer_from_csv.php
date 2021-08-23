<?php
require 'autoload.php';

use Symfony\Component\Yaml\Yaml;

$script = eZScript::instance(['description' => "Create db translations csv from installer directory",
    'use-session' => false,
    'use-modules' => false,
    'debug-timing' => true
]);

$script->startup();
$options = $script->getOptions(
    '[installer_directory:][languages:]',
    '',
    [
        'installer_directory' => 'installer directory path',
        'languages' => 'comma sepeparated language codes (default is ita-IT,ger-DE,eng-GB)',
    ]
);
$script->initialize();
$cli = eZCLI::instance();
$output = new ezcConsoleOutput();

$languages = $options['languages'] ? explode(',', $options['languages']) : ['ita-IT', 'ger-DE', 'eng-GB'];
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
$csv = $sheet->getSheetDataArray($sheetName);

switch ($sheetName){
    case 'OpenCity-Trasparenza':
        $installerDirectory .= 'modules/trasparenza/';
        break;
    case 'OpenCity-Valuation':
        $installerDirectory .= 'modules/valuation/';
        break;
    case 'OpenCity-Newsletter':
        $installerDirectory .= 'modules/newsletter/';
        break;
}

$updateActions = [];
foreach ($csv as $row) {
    $contextParts = explode('/', $row['context']);
    if (!isset($contextParts[1])) continue;
    $fileName = $contextParts[0] . '/' . $contextParts[1];
    if ($contextParts[0] == 'classes') {
        $field = null;
        if (in_array($contextParts[2], ['serialized_name_list', 'serialized_description_list'])) {
            $property = $contextParts[2];
        } else {
            $field = $contextParts[2];
            $property = $contextParts[3];
        }
        $data = $row;
        unset($data['context']);
        foreach ($row as $key => $value){
            if (empty($value) || !in_array($key, $languages)){
                unset($data[$key]);
            }
        }
        $updateActions[$fileName][] = [
            'field' => $field,
            'property' => $property,
            'data' => $data,
            'type' => 'class',
        ];
    }elseif ($contextParts[0] == 'classextra') {
        $attributeGroup = $contextParts[2];
        $data = $row;
        unset($data['context']);
        foreach ($row as $key => $value){
            if (empty($value) || !in_array($key, $languages)){
                unset($data[$key]);
            }
        }
        foreach ($data as $language => $translation) {
            $updateActions[$fileName][] = [
                'attribute_group' => $language . '::' . $attributeGroup,
                'data' => $translation,
                'type' => 'classextra',
            ];
        }
    }
}

foreach ($updateActions as $fileName => $updates){
    $fileData = Yaml::parse(file_get_contents($installerDirectory . '/' . $fileName));
    foreach ($updates as $update) {
        if ($update['type'] == 'class') {
            if ($update['field']) {
                if (isset($fileData['data_map'][$update['field']][$update['property']])) {
                    $fileData['data_map'][$update['field']][$update['property']] = array_merge(
                        $fileData['data_map'][$update['field']][$update['property']],
                        $update['data']
                    );
                } else {
                    $fileData['data_map'][$update['field']][$update['property']] = $update['data'];
                }
            } else {
                if (isset($fileData[$update['property']])) {
                    $fileData[$update['property']] = array_merge(
                        $fileData[$update['property']],
                        $update['data']
                    );
                } else {
                    $fileData[$update['property']] = $update['data'];
                }
            }
        } elseif ($update['type'] == 'classextra') {
            $fileData['attribute_group'][$update['attribute_group']]['*'] = $update['data'];
        }
    }

    $dataYaml = Yaml::dump($fileData, 10);
    file_put_contents($installerDirectory . '/' . $fileName, $dataYaml);
    $cli->output("Update $fileName");
}

$script->shutdown();