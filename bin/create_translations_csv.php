<?php
require 'autoload.php';

use Opencontent\I18n\TsParser;

$script = eZScript::instance(['description' => "Create translations csv for extension",
    'use-session' => false,
    'use-modules' => false,
    'debug-timing' => true
]);

$script->startup();
$options = $script->getOptions(
    '[extension:][list]',
    '',
    ['extension' => 'extension name']
);
$script->initialize();
$cli = eZCLI::instance();

try {

    if ($options['list']){
        $extensions = eZDir::findSubdirs('extension');
        sort($extensions);
        foreach ($extensions as $extension){
            if (file_exists("./extension/$extension/translations/untranslated/translation.ts")){
                $cli->output($extension);
            }
        }
    }else {


        $extensionName = $options['extension'];
        if (!$extensionName) {
            throw new Exception("Missing extension parameter");
        }

        $basePath = "extension/$extensionName/translations";
        if (!is_dir($basePath)) {
            throw new Exception("Directory $basePath not found");
        }
        $files = eZDir::recursiveFind($basePath, '.ts');

        $data = $translationsList = $sourceList = [];
        foreach ($files as $file) {
            $tsParser = new TsParser($file);
            $tsData = $tsParser->getData();
            foreach ($tsData as $context => $values) {
                foreach ($values as $source => $translation) {
                    $sourceList[] = $context . '@' . $source;
                    $translationsList[$tsParser->getCurrentLanguage()][$context . '@' . $source] = $translation;
                }
            }
        }

        $sourceList = array_unique($sourceList);

        unset($translationsList['untranslated']);

        $filename = $extensionName . '.csv';
        $filepath = './openpa_tools/translations_tools/' . $filename;
        eZFile::create($filename, dirname($filepath), null);
        $output = fopen($filepath, 'a');

        foreach ($sourceList as $sourceIdentifier) {
            list($context, $source) = explode('@', $sourceIdentifier);
            $fields = [
                $context, $source
            ];
            foreach (array_keys($translationsList) as $lang) {
                $fields[] = isset($translationsList[$lang][$sourceIdentifier]) ? $translationsList[$lang][$sourceIdentifier] : '';
            }

            fputcsv(
                $output,
                $fields
            );
        }

        $cli->warning($filepath);
    }

} catch (Exception $e) {
    $cli->error($e->getMessage());
}

$script->shutdown();
