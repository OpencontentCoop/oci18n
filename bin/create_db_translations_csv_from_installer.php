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
        'languages' => 'comma sepeparated language codes (default is ita-IT,ger-DE,eng-GB)'
    ]
);
$script->initialize();
$cli = eZCLI::instance();

$languages = $options['languages'] ? explode(',', $options['languages']) : ['ita-IT', 'ger-DE', 'eng-GB'];

$csvData = [array_merge(['context'], $languages)];

$installerDirectory = rtrim($options['installer_directory'] . '/') . '/';

$intaller = Yaml::parse(file_get_contents($installerDirectory . '/installer.yml'));
$intallerName = eZCharTransform::instance()->transformByGroup($intaller['name'], 'urlalias');
$intallerVersion = $intaller['version'];

$classes = $installerDirectory . 'classes';
$items = eZDir::findSubitems($classes, 'f');
foreach ($items as $item) {
    $data = Yaml::parse(file_get_contents($classes . '/' . $item));
    if (isset($data['serialized_name_list'])) {
        $csvDatum = ['context' => 'classes/' . $item . '/serialized_name_list',];
        foreach ($languages as $language) {
            $csvDatum[$language] = isset($data['serialized_name_list'][$language]) ? $data['serialized_name_list'][$language] : '';
        }
        $csvData[] = array_values($csvDatum);
    }
    if (isset($data['serialized_description_list'])) {
        $csvDatum = ['context' => 'classes/' . $item . '/serialized_description_list',];
        foreach ($languages as $language) {
            $csvDatum[$language] = isset($data['serialized_description_list'][$language]) ? $data['serialized_description_list'][$language] : '';
        }
        $csvData[] = array_values($csvDatum);
    }
    if (isset($data['data_map'])) {
        foreach ($data['data_map'] as $identifier => $attribute) {
            if (isset($attribute['serialized_name_list'])) {
                $csvDatum = ['context' => 'classes/' . $item . '/' . $identifier . '/serialized_name_list',];
                foreach ($languages as $language) {
                    $csvDatum[$language] = isset($attribute['serialized_name_list'][$language]) ? $attribute['serialized_name_list'][$language] : '';
                }
                $csvData[] = array_values($csvDatum);
            }
            if (isset($attribute['serialized_description_list'])) {
                $csvDatum = ['context' => 'classes/' . $item . '/' . $identifier . '/serialized_description_list',];
                foreach ($languages as $language) {
                    $csvDatum[$language] = isset($attribute['serialized_description_list'][$language]) ? $attribute['serialized_description_list'][$language] : '';
                }
                $csvData[] = array_values($csvDatum);
            }
        }
    }
}

$classeExtra = $installerDirectory . 'classextra';
$items = eZDir::findSubitems($classeExtra, 'f');
foreach ($items as $item) {
    $data = Yaml::parse(file_get_contents($classeExtra . '/' . $item));
    if (isset($data['attribute_group'])) {
        foreach ($data['attribute_group'] as $identifier => $attributeGroupDatum) {
            if (strpos($identifier, '::') === false && $identifier !== 'enabled') {
                $csvDatum = ['context' => 'classextra/' . $item . '/' . $identifier];
                foreach ($languages as $language) {
                    if (isset($data['attribute_group'][$language . '::' . $identifier]['*'])) {
                        $csvDatum[$language] = $data['attribute_group'][$language . '::' . $identifier]['*'];
                    } elseif ($language == 'ita-IT') {
                        $csvDatum[$language] = $data['attribute_group'][$identifier]['*'];
                    } else {
                        $csvDatum[$language] = '';
                    }
                }
                $csvData[] = array_values($csvDatum);
            }
        }
    }
}

$filename = 'translations_' . $intallerName . '.' . $intallerVersion . '.csv';
$filepath = $filename;
eZFile::create($filename, false, null);
$output = fopen($filepath, 'a');
foreach ($csvData as $csvDatum) {
    fputcsv(
        $output,
        $csvDatum
    );
}
$cli->warning(realpath($filepath));

$script->shutdown();