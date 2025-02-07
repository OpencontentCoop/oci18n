<?php

namespace Opencontent\I18n;

use Symfony\Component\Yaml\Yaml;

class CliTools
{
    private static $extensionList = [
        'ocbootstrap',
        'occustomfind',
        'oceditorialstuff',
        'ocevents',
        'ocmultibinary',
        'ocopendata_forms',
        'octranslate',
        'openpa_agenda',
        'openpa_bootstrapitalia',
        'openpa_newsletter',
    ];

    /**
     * @return void
     * @throws \Exception
     */
    public static function runWithSpreadsheet()
    {
        $cli = \eZCLI::instance();
        $output = new \ezcConsoleOutput();
        $googleSpreadsheetUrl = 'https://docs.google.com/spreadsheets/d/1Sr21vupXSjru__6NfteiFbM6kmarNhyETzYgjSp_ngc';

        $question = \ezcConsoleQuestionDialog::YesNoQuestion(
            $output,
            "Utilizzo il Google Spreadsheet predefinito: $googleSpreadsheetUrl",
            "y"
        );

        if (\ezcConsoleDialogViewer::displayDialog($question) == "n") {
            $opts = new \ezcConsoleQuestionDialogOptions();
            $opts->text = "Inserisci l'url del Google Spreadsheet";
            $opts->showResults = true;
            $question = new \ezcConsoleQuestionDialog($output, $opts);
            $googleSpreadsheetUrl = \ezcConsoleDialogViewer::displayDialog($question);
        }

        $googleSpreadsheetTemp = explode(
            '/',
            str_replace('https://docs.google.com/spreadsheets/d/', '', $googleSpreadsheetUrl)
        );
        $googleSpreadsheetId = array_shift($googleSpreadsheetTemp);

        $sheet = new \Opencontent\Google\GoogleSheet($googleSpreadsheetId);
        $sheets = $sheet->getSheetTitleList();

        $menu = new \ezcConsoleMenuDialog($output);
        $menu->options = new \ezcConsoleMenuDialogOptions();
        $menu->options->text = "Please choose a possibility:\n";
        $menu->options->validator = new \ezcConsoleMenuDialogDefaultValidator($sheets);
        $choice = \ezcConsoleDialogViewer::displayDialog($menu);

        $extensionName = $sheets[$choice];
        if (!is_dir("./extension/$extensionName")) {
            throw new \Exception("Extension $extensionName not installed");
        }
        $csv = $sheet->getSheetDataHash($extensionName);

        $data = [];
        foreach ($csv as $item) {
            $context = $item['context'];
            unset($item['context']);
            $source = $item['source'];
            unset($item['source']);
            $data['untranslated'][$context][$source] = '';
            foreach ($item as $key => $value) {
                $data[$key][$context][$source] = $value;
            }
        }

        foreach ($data as $language => $values) {
            $success = TsParser::storeTsFile($extensionName, $language, $values);
            if ($success) {
                $cli->warning("Store file $success");
            }
        }
    }

    public static function runWithPoEditor()
    {
        $cli = \eZCLI::instance();

        $token = self::ask(
            "Inserisci il token api PoEditor (lo trovi qui: https://poeditor.com/account/api)",
            getenv('POEDITOR_TOKEN')
        );
        $client = (new PoEditorClient($token));

        $language = self::selectLanguage();
        $tag = self::selectExtension(true, true);
        $importContentTypes = self::yesNo("Vuoi aggiornare i content types?", 'n');
        $importContents = self::yesNo("Vuoi aggiornare i contenuti?", 'n');
        $importTags = self::yesNo("Vuoi aggiornare i tags?", 'n');
        $installer = ($importContentTypes || $importContents || $importTags) ?
            self::ask(
                "Inserisci il percorso dell'installer da aggiornare",
                'vendor/opencity-labs/opencity-installer'
            ) : null;

        $languages = explode(",", $language);

        if ($tag === 'Tutte'){
            $extensions = self::$extensionList;
        }else{
            $extensions = [$tag];
        }

//        $languages = ['en'];
//        $extensions = [];
//        $importContentTypes = false;
//        $importTags = false;
//        $importContents = true;
//        $installer = 'vendor/opencity-labs/opencity-installer';

        foreach ($extensions as $extension) {
            if (is_dir("extension/$extension")) {
                $project = PoEditorTools::selectProject($client, 'Opencity Italia - CMS - framework');
                foreach ($languages as $language) {
                    $exportUrl = $client->getExportUrl(
                        $project['id'],
                        $language,
                        'ts',
                        [$extension]
                    );
                    $languageCode = PoEditorClient::$languageMap[$language];
                    if ($languageCode === 'eng-GB') {
                        $languageCode .= '@euro';
                    }
                    $data = file_get_contents($exportUrl);
                    if (!empty($data)) {
                        $filepath = "extension/$extension/translations/$languageCode/translation.ts";
                        \eZDir::mkdir(dirname($filepath), false, true);
                        file_put_contents($filepath, $data);
                        $cli->warning("File salvato in $filepath");
                    } else {
                        $cli->error('Nessun valore trovato per la lingua ' . $language);
                    }
                }
            }
        }

        if ($installer) {
            $installers = self::getInstallers($installer);

            if ($importContentTypes) {
                $classFileList = self::getInstallersClassData($installers, '/classes');
                $classExtraFileList = self::getInstallersClassData($installers, '/classextra');

                $project = PoEditorTools::selectProject($client, 'Opencity Italia - CMS - content-types');
                foreach ($languages as $language) {
                    $locale = PoEditorClient::$languageMap[$language] ?? null;
                    $exportUrl = $client->getExportUrl(
                        $project['id'],
                        $language,
                        'yml'
                    );
                    $exportData = Yaml::parse(file_get_contents($exportUrl));
                    foreach ($exportData as $classIdentifier => $values) {
                        if (isset($classFileList[$classIdentifier])) {
                            #$cli->warning("-> $classIdentifier");
                            $classData = $classFileList[$classIdentifier]['data'];
                            if (isset($classData['serialized_name_list'])) {
                                $key = sprintf('%s.%s', $classIdentifier, '_name');
                                if (!empty($values[$key])) {
                                    #$cli->output('  ' . $key . ' -> ' . $values[$key]);
                                    if ($locale) {
                                        $classData['serialized_name_list'][$locale] = $values[$key];
                                    }
                                }
                            }
                            if (isset($classData['serialized_description_list'])) {
                                $key = sprintf('%s.%s', $classIdentifier, '_description');
                                if (!empty($values[$key])) {
                                    #$cli->output('  ' . $key . ' -> ' . $values[$key]);
                                    $classData['serialized_description_list'][$locale] = $values[$key];
                                }
                            }
                            if (isset($classData['data_map'])) {
                                foreach ($classData['data_map'] as $identifier => $attribute) {
                                    if (isset($attribute['serialized_name_list'])) {
                                        $key = sprintf('%s.%s.%s', $classIdentifier, $identifier, '_name');
                                        if (!empty($values[$key])) {
                                            #$cli->output('  ' . $key . ' -> ' . $values[$key]);
                                            $classData['data_map'][$identifier]['serialized_name_list'][$locale] = $values[$key];
                                        }
                                    }
                                    if (isset($attribute['serialized_description_list'])) {
                                        $key = sprintf('%s.%s.%s', $classIdentifier, $identifier, '_description');
                                        if (!empty($values[$key])) {
                                            #$cli->output('  ' . $key . ' -> ' . $values[$key]);
                                            $classData['data_map'][$identifier]['serialized_description_list'][$locale] = $values[$key];
                                        }
                                    }
                                }
                            }
                            $classFileList[$classIdentifier]['data'] = $classData;
                            $classExtraData = $classExtraFileList[$classIdentifier]['data'] ?? null;
                            if ($classExtraData && isset($classExtraData['attribute_group'])) {
                                foreach ($classExtraData['attribute_group'] as $identifier => $attributeGroupDatum) {
                                    if (strpos($identifier, '::') === false && $identifier !== 'enabled') {
                                        $key = sprintf('%s._groups.%s', $classIdentifier, $identifier);
                                        if (!empty($values[$key])) {
                                            #$cli->output('  ' . $key . ' -> ' . $values[$key]);
                                            $classExtraData['attribute_group'][$locale . '::' . $identifier]['*'] = $values[$key];
                                        }
                                    }
                                }
                            }
                            $classExtraFileList[$classIdentifier]['data'] = $classExtraData;
                        } else {
                            $cli->error("Class $classIdentifier not found");
                        }
                    }
                }
                foreach ($classFileList as $classFile) {
                    $dataYaml = Yaml::dump($classFile['data'], 10);
                    file_put_contents($classFile['path'], $dataYaml);
                }
                foreach ($classExtraFileList as $identifier => $classFile) {
                    $dataYaml = Yaml::dump($classFile['data'], 10);
                    if (isset($classFile['path'])) {
                        file_put_contents($classFile['path'], $dataYaml);
                    } else {
                        $cli->error("Path of classextra $identifier not found");
                    }
                }
            }

            if ($importContents) {
                $exportFields = [];
                foreach (
                    [
                        'name',
                        'abstract',
                        'description',
                        'title',
                        'body',
                        'layout.global.blocks.*.name',
                        'layout.global.blocks.*.custom_attributes.show_all_text',
                        'layout.global.blocks.*.custom_attributes.intro_text',
                        'page.global.blocks.*.name',
                        'page.global.blocks.*.custom_attributes.show_all_text',
                        'page.global.blocks.*.custom_attributes.intro_text',
                        'question',
                        'answer',
                    ] as $field
                ) {
                    if (strpos($field, '*') !== false) {
                        for ($i = 0; $i < 50; $i++) {
                            $countField = str_replace('*', $i, $field);
                            $exportFields[] = $countField;
                        }
                    } else {
                        $exportFields[] = $field;
                    }
                }

                $contentFileList = self::getInstallersContentData($installers);
                $project = PoEditorTools::selectProject($client, 'Opencity Italia - CMS - contents');
                foreach ($languages as $language) {
                    $locale = PoEditorClient::$languageMap[$language] ?? null;
                    $exportUrl = $client->getExportUrl(
                        $project['id'],
                        $language,
                        'yml'
                    );
                    $exportData = Yaml::parse(file_get_contents($exportUrl));
                    foreach ($exportData as $name => $values) {
                        if (isset($contentFileList[$name])) {
                            $contentData = $contentFileList[$name]['data'];
                            #$cli->warning("-> $name");
                            foreach ($exportFields as $field) {
                                $key = sprintf('%s-%s', $contentFileList[$name]['identifier'], $field);
                                if (!empty($values[$key])) {
//                                    $cli->output('  ' . $key . ' -> ' . $values[$key]);
                                    if (strpos($field, '.') !== false) {
                                        $dot = new Dot($contentData['data'][$locale]);
                                        $dot->set($field, $values[$key]);
                                        $contentData['data'][$locale] = array_merge(
                                            $contentData['data'][$locale],
                                            $dot->jsonSerialize()
                                        );
                                    } else {
                                        $contentData['data'][$locale][$field] = $values[$key];
                                    }
                                }
                            }
                            $contentData = self::fixContent(
                                $contentData,
                                $contentFileList[$name]['class'],
                                $contentFileList[$name]['installer']
                            );
                            $contentFileList[$name]['data'] = $contentData;
                        } else {
                            $cli->error("-> $name");
                        }
                    }
                }
                foreach ($contentFileList as $contentFile) {
                    $dataYaml = Yaml::dump($contentFile['data'], 10);
                    file_put_contents($contentFile['path'], $dataYaml);
                }
            }

            if ($importTags) {
                $project = PoEditorTools::selectProject($client, 'Opencity Italia - CMS - tags');
                $updates = [];
                foreach ($languages as $language) {
//                    $locale = PoEditorClient::$languageMap[$language] ?? null;
                    $exportUrl = $client->getExportUrl(
                        $project['id'],
                        $language,
                        'yml'
                    );
                    $exportData = Yaml::parse(file_get_contents($exportUrl));
                    foreach ($exportData as $key => $exportDatum) {
                        [$filename, , $remoteId] = explode(':', $key, 3);
                        if (!empty($exportDatum[$remoteId . '.keyword'])) {
                            $updates[$filename][$remoteId]['keyword_' . $language] = $exportDatum[$remoteId . '.keyword'];
                        }
                        if (!empty($exportDatum[$remoteId . '.keyword'])) {
                            $updates[$filename][$remoteId]['keyword_' . $language] = $exportDatum[$remoteId . '.keyword'];
                        }
                        if (!empty($exportDatum[$remoteId . '.synonyms'])) {
                            $updates[$filename][$remoteId]['synonyms_' . $language] = $exportDatum[$remoteId . '.synonyms'];
                        }
                        if (!empty($exportDatum[$remoteId . '.description'])) {
                            $updates[$filename][$remoteId]['description_' . $language] = $exportDatum[$remoteId . '.description'];
                        }
                    }
                }
                foreach ($updates as $filename => $update) {
                    $filepath = $installer . '/tagtree_csv/' . $filename;
                    if (!file_exists($filepath)) {
                        $cli->error("File $filepath not found");
                    }
                    $values = [];
                    $row = 1;
                    $headers = [];
                    if (($handle = fopen($filepath, "r")) !== false) {
                        while (($data = fgetcsv($handle, 100000)) !== false) {
                            if ($row === 1) {
                                $headers = $data;
                            } else {
                                $value = [];
                                for ($j = 0, $jMax = count($headers); $j < $jMax; ++$j) {
                                    $value[$headers[$j]] = $data[$j];
                                }
                                $values[] = $value;
                            }
                            $row++;
                        }
                        fclose($handle);
                    }
                    $isModified = false;
                    foreach ($values as $index => $value) {
                        foreach ($value as $itemIdentifier => $itemValue) {
                            if (isset($update[$value['remote_id']][$itemIdentifier]) && $update[$value['remote_id']][$itemIdentifier] !== $itemValue) {
                                $values[$index][$itemIdentifier] = $update[$value['remote_id']][$itemIdentifier];
                                $isModified = true;
                            }
                        }
                    }
                    if ($isModified) {
                        $values = array_values($values);
                        array_unshift($values, $headers);
                        $fp = fopen($filepath, 'w');
                        foreach ($values as $row) {
                            fputcsv($fp, array_values($row));
                        }
                    }
                }
            }
        }
    }

    private static function fixContent($data, $classIdentifier, $installerPath): array
    {
        $classFilepath = $installerPath . '/classes/' . $classIdentifier . '.yml';
        $class = Yaml::parse(file_get_contents($classFilepath));
        $required = [];
        foreach ($class['data_map'] as $identifier => $attribute) {
            if (isset($attribute['is_required']) && $attribute['is_required']) {
                $required[] = $identifier;
            }
        }
        foreach ($data['data'] as $locale => $values) {
            if ($locale !== 'ita-IT') {
                foreach ($required as $identifier) {
                    if (empty($values[$identifier])) {
                        $data['data'][$locale][$identifier] = $data['ita-IT'][$identifier] ?? '';
                    }
                }
            }
        }
        return $data;
    }

    public static function getInstallers($installerDirectory): array
    {
        $installers = [$installerDirectory];
        $modules = \eZDir::findSubitems($installerDirectory . '/modules', 'd');
        foreach ($modules as $module) {
            $installers[] = $installerDirectory . '/modules/' . $module;
        }

        return $installers;
    }

    private static function getInstallersContentData($installers): array
    {
        $collection = [];
        foreach ($installers as $installer) {
            $contents = $installer . '/contents';
            $items = \eZDir::findSubitems($contents, 'f');
            foreach ($items as $item) {
                $collection[] = [
                    'path' => $contents . '/' . $item,
                    'dir' => null,
                    'installer' => $installer,
                    'data' => [],
                    'class' => [],
                ];
            }
            $contentTrees = $installer . '/contenttrees';
            $dirs = \eZDir::findSubitems($contentTrees, 'd');
            foreach ($dirs as $dir) {
                $items = \eZDir::findSubitems($contentTrees . '/' . $dir, 'f');
                foreach ($items as $item) {
                    $collection[] = [
                        'path' => $contentTrees . '/' . $dir . '/' . $item,
                        'dir' => $dir,
                        'installer' => $installer,
                        'data' => [],
                        'class' => [],
                    ];
                }
            }
        }
        $hashCollection = [];
        foreach ($collection as $item) {
            $fileContents = file_get_contents($item['path']);
            $contentData = Yaml::parse($fileContents);
            $dir = $item['dir'];
            if ($dir) {
                $dir = $dir . ':';
            }
            $classIdentifier = $contentData['metadata']['classIdentifier'];
            $name = $classIdentifier . '.' . $dir . basename($item['path']);
            if (isset($hashCollection[$name])){
                $useAlreadyExists = (
                    mb_strlen(json_encode($hashCollection[$name]['data'])) >
                    mb_strlen(json_encode($contentData))
                );
                \eZCLI::instance()->warning("Duplicate $name in " . $item['path'] . ' ' . (int)$useAlreadyExists);
                if ($useAlreadyExists) {
                    continue;
                }
            }
            $hashCollection[$name] = $item;
            $hashCollection[$name]['identifier'] = $dir . basename($item['path']);
            $hashCollection[$name]['data'] = $contentData;
            $hashCollection[$name]['class'] = $classIdentifier;
        }
        return $hashCollection;
    }

    private static function getInstallersClassData($installers, $path): array
    {
        $collection = [];
        foreach ($installers as $installer) {
            $items = \eZDir::findSubitems($installer . $path, 'f');
            foreach ($items as $item) {
                $classData = Yaml::parse(file_get_contents($installer . $path . '/' . $item));
                if ($path === '/classes') {
                    $classIdentifier = $classData['identifier'] ?? null;
                } else {
                    $classIdentifier = str_replace('.yml', '', basename($item));
                }
                if (!empty($classIdentifier)) {
                    $collection[$classIdentifier] = [
                        'path' => $installer . $path . '/' . $item,
                        'data' => $classData,
                    ];
                }
            }
        }
        return $collection;
    }

    public static function ask($questionText, $default = null): ?string
    {
        $output = new \ezcConsoleOutput();
        $opts = new \ezcConsoleQuestionDialogOptions();
        $opts->text = $questionText;
        $opts->showResults = true;
        $opts->validator = new \ezcConsoleQuestionDialogTypeValidator(
            \ezcConsoleQuestionDialogTypeValidator::TYPE_STRING, $default
        );
        $question = new \ezcConsoleQuestionDialog($output, $opts);
        return \ezcConsoleDialogViewer::displayDialog($question);
    }

    public static function select($questionText, $list): ?string
    {
        $output = new \ezcConsoleOutput();
        $select = new \ezcConsoleMenuDialog($output);
        $select->options = new \ezcConsoleMenuDialogOptions();
        $select->options->text = "$questionText\n";
        $select->options->validator = new \ezcConsoleMenuDialogDefaultValidator($list);
        $index = \ezcConsoleDialogViewer::displayDialog($select);
        return $list[$index] ?? null;
    }

    public static function yesNo($questionText, $default = 'y'): bool
    {
        $output = new \ezcConsoleOutput();
        $question = \ezcConsoleQuestionDialog::YesNoQuestion($output, "$questionText", $default);
        return \ezcConsoleDialogViewer::displayDialog($question) == "y";
    }

    public static function selectLanguage(): ?string
    {
        return self::select("Che lingua vuoi aggiornare?", ['it', 'de', 'en', 'es', 'fr']);
    }

    public static function selectExtension($appendNone = false, $appendAll = false): ?string
    {
        $extensionList = self::$extensionList;
        if ($appendNone){
            array_unshift($extensionList, 'Nessuna: non voglio aggiornare il framework');
        }
        if ($appendAll){
            $extensionList[] = 'Tutte';
        }
        return self::select("Che estensione del framework vuoi aggiornare?", $extensionList);
    }
}