<?php

require 'autoload.php';

use Opencontent\I18n\PoEditorClient;
use Opencontent\I18n\PoEditorTools;
use Opencontent\I18n\Dot;
use Symfony\Component\Yaml\Yaml;

$script = eZScript::instance([
    'description' => "Update installer from poeditor",
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
    'debug-timing' => true,
]);

$script->startup();
$options = $script->getOptions(
    '[language:][token:][installer_directory:]',
    '',
    [
        'language' => 'language code (e.g. it,de,fr,es,en)',
        'token' => 'api token',
        'installer_directory' => 'installer data',
    ]
);
$script->initialize();
$cli = eZCLI::instance();
$output = new ezcConsoleOutput();

$importContentTypes = false;
$importContents = false;
$importTags = true;

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

$installerDirectory = rtrim($options['installer_directory'], '/');
if (empty($installerDirectory)) {
    $cli->error('Missing installer_directory');
    $script->shutdown();
    exit();
}

$installers = [$installerDirectory];
$modules = eZDir::findSubitems($installerDirectory . '/modules', 'd');
foreach ($modules as $module) {
    $installers[] = $installerDirectory . '/modules/' . $module;
}

$client = (new PoEditorClient($token));

if ($importContentTypes) {
    function getInstallersClassData($installers, $path)
    {
        $collection = [];
        foreach ($installers as $installer) {
            $items = eZDir::findSubitems($installer . $path, 'f');
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

    $classFileList = getInstallersClassData($installers, '/classes');
    $classExtraFileList = getInstallersClassData($installers, '/classextra');

    $project = PoEditorTools::selectProject($client, 'Opencity Italia - CMS - content-types');
    $languages = explode(",", $language);
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
                $cli->warning("-> $classIdentifier");
                $classData = $classFileList[$classIdentifier]['data'];
                if (isset($classData['serialized_name_list'])) {
                    $key = sprintf('%s.%s', $classIdentifier, '_name');
                    if (isset($values[$key])) {
                        $cli->output('  ' . $key . ' -> ' . $values[$key]);
                        if ($locale) {
                            $classData['serialized_name_list'][$locale] = $values[$key];
                        }
                    }
                }
                if (isset($classData['serialized_description_list'])) {
                    $key = sprintf('%s.%s', $classIdentifier, '_description');
                    if (isset($values[$key])) {
                        $cli->output('  ' . $key . ' -> ' . $values[$key]);
                        $classData['serialized_description_list'][$locale] = $values[$key];
                    }
                }
                if (isset($classData['data_map'])) {
                    foreach ($classData['data_map'] as $identifier => $attribute) {
                        if (isset($attribute['serialized_name_list'])) {
                            $key = sprintf('%s.%s.%s', $classIdentifier, $identifier, '_name');
                            if (isset($values[$key])) {
                                $cli->output('  ' . $key . ' -> ' . $values[$key]);
                                $classData['data_map'][$identifier]['serialized_name_list'][$locale] = $values[$key];
                            }
                        }
                        if (isset($attribute['serialized_description_list'])) {
                            $key = sprintf('%s.%s.%s', $classIdentifier, $identifier, '_description');
                            if (isset($values[$key])) {
                                $cli->output('  ' . $key . ' -> ' . $values[$key]);
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
                            if (isset($values[$key])) {
                                $cli->output('  ' . $key . ' -> ' . $values[$key]);
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
    foreach ($classExtraFileList as $classFile) {
        $dataYaml = Yaml::dump($classFile['data'], 10);
        file_put_contents($classFile['path'], $dataYaml);
    }
}

if ($importContents) {
    function getInstallersContentData($installers)
    {
        $collection = [];
        foreach ($installers as $installer) {
            $contents = $installer . '/contents';
            $items = eZDir::findSubitems($contents, 'f');
            foreach ($items as $item) {
                $collection[] = [
                    'path' => $contents . '/' . $item,
                    'dir' => null,
                    'data' => [],
                ];
            }
            $contentTrees = $installer . '/contenttrees';
            $dirs = eZDir::findSubitems($contentTrees, 'd');
            foreach ($dirs as $dir) {
                $items = eZDir::findSubitems($contentTrees . '/' . $dir, 'f');
                foreach ($items as $item) {
                    $collection[] = [
                        'path' => $contentTrees . '/' . $dir . '/' . $item,
                        'dir' => $dir,
                        'data' => [],
                    ];
                }
            }
        }
        $hashCollection = [];
        foreach ($collection as $index => $item) {
            $contentData = Yaml::parse(file_get_contents($item['path']));
            $dir = $item['dir'];
            if ($dir) {
                $dir = $dir . ':';
            }
            $classIdentifier = $contentData['metadata']['classIdentifier'];
            $name = $classIdentifier . '.' . $dir . basename($item['path']);
            $hashCollection[$name] = $item;
            $hashCollection[$name]['identifier'] = $dir . basename($item['path']);
            $hashCollection[$name]['data'] = $contentData;
        }
        return $hashCollection;
    }

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

    $contentFileList = getInstallersContentData($installers);
    $project = PoEditorTools::selectProject($client, 'Opencity Italia - CMS - contents');
    $languages = explode(",", $language);
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
                $cli->warning("-> $name");
                foreach ($exportFields as $field) {
                    $key = sprintf('%s-%s', $contentFileList[$name]['identifier'], $field);
                    if (!empty($values[$key])) {
                        $cli->output('  ' . $key . ' -> ' . $values[$key]);
                        if (strpos($field, '.') !== false){
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

if ($importTags){
    $project = PoEditorTools::selectProject($client, 'Opencity Italia - CMS - tags');
    $languages = explode(",", $language);
    foreach ($languages as $language) {
        $locale = PoEditorClient::$languageMap[$language] ?? null;
        $exportUrl = $client->getExportUrl(
            $project['id'],
            $language,
            'yml'
        );
        $exportData = Yaml::parse(file_get_contents($exportUrl));
        print_r($exportData);
    }
}

$script->shutdown();