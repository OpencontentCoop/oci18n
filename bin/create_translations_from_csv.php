<?php
require 'autoload.php';

use Opencontent\I18n\TsParser;

$script = eZScript::instance(['description' => "From csv to ts",
    'use-session' => false,
    'use-modules' => false,
    'debug-timing' => true
]);

$script->startup();
$options = $script->getOptions(
    '[source:][csv_source:][csv_context_column:][csv_source_column:][csv_target_column:][language:]',
    '',
    [
        'source' => 'percorso del file extension/*/translations/untranslated/translation.ts',
        'csv_source' => 'percorso del file csv che contiene le traduzioni',
        'csv_context_column' => 'colonna context del csv da considerare',
        'csv_source_column' => 'colonna source del csv da considerare',
        'csv_target_column' => 'colonna target del csv da considerare',
        'language' => 'codice della lingua che si sta creando esempio ita-IT'
    ]
);
$script->initialize();
$cli = eZCLI::instance();

try {

    $language = $options['language'];

    $tsParser = new TsParser($options['source']);
    $baseData = $tsParser->getData();

    $contextColumn = $options['csv_context_column'];
    $sourceColumn = $options['csv_source_column'];
    $targetColumn = $options['csv_target_column'];

    $csvData = [];
    $doc = new SQLICSVDoc(new SQLICSVOptions([
        'csv_path' => $options['csv_source'],
        'delimiter' => ','
    ]));
    $doc->parse();
    foreach ($doc->rows as $item){
        $csvData[(string)$item->{$contextColumn}][(string)$item->{$sourceColumn}] = (string)$item->{$targetColumn};
    }

    $translationData = [];
    $unTranslationData = [];
    foreach ($csvData as $context => $values){
        foreach ($values as $source => $target){
            if (isset($baseData[$context][$source])){
                $translationData[$context][$source] = $target;
            }else{
                $unTranslationData[$context][$source] = $target;
            }
        }
    }

    $cli->output("## Stringhe trovate nel csv e non nel untranslated/translation.ts");
    foreach ($unTranslationData as $context => $values){
        foreach ($values as $source => $target){
            $cli->error("[$context] $source");
        }
    }
    $cli->output();

    $cli->output("## Stringhe presenti in untranslated/translation.ts e non trovate nel csv");
    foreach ($baseData as $context => $values){
        foreach ($values as $source => $target){
            if (!isset($translationData[$context][$source])) {
                $cli->warning("[$context] $source");
            }
        }
    }
    $cli->output();

    $domDocument = $tsParser->getDOMDocument($translationData);
    $newFilename = $tsParser->getFilenameInLanguage($language);

    $do = true;
    if (file_exists($newFilename)){
        $output = new ezcConsoleOutput();
        $question = ezcConsoleQuestionDialog::YesNoQuestion(
            $output,
            "Il file $newFilename esiste già: sovrascrivo?",
            "y"
        );
        $do = ezcConsoleDialogViewer::displayDialog( $question ) == "y";
    }

    if ($do){
        eZFile::create(basename($newFilename), dirname($newFilename), null);
        /** @var DOMDocument $domDocument */
        $domDocument->preserveWhiteSpace = false;
        $domDocument->formatOutput = true;
        $domDocument->save($newFilename);
    }

} catch (Exception $e) {
    $cli->error($e->getMessage());
}

$script->shutdown();
