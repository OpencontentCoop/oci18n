<?php

namespace Opencontent\I18n;

use eZCLI;
use eZDir;
use Symfony\Component\Yaml\Yaml;

class YmlExportableContents extends YmlExportableContentTypes
{
    protected $targetPath = '/_translations/contents';

    private $exportFields = [
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
        'legal_name',
        'main_function',
//        'titolo',
//        'contenuto_obbligo',
//        'riferimenti_normativi'
    ];

    private function parseContentStrings($filepath, $dir = null)
    {
        eZCLI::instance()->output(' - ' . $filepath);
        $data = Yaml::parse(file_get_contents($filepath));

        if ($dir) {
            $dir = $dir . ':';
        }
        $classIdentifier = $data['metadata']['classIdentifier'];
        $name = $dir . basename($filepath);
        $exportFields = [];
        $ita = (new Dot($data['data'][$this->fallbackLanguage]))->flatten();
        foreach ($this->exportFields as $field) {
            if (strpos($field, '*') !== false) {
                for ($i = 0; $i < 50; $i++) {
                    $countField = str_replace('*', $i, $field);
                    if (isset($ita[$countField]) && !empty($ita[$countField])) {
                        $exportFields[] = $countField;
                    }
                }
            } elseif (isset($ita[$field]) && !empty($ita[$field])) {
                $exportFields[] = $field;
            }
        }

        foreach ($this->languages as $language) {
            $flatten = isset($data['data'][$language]) ? (new Dot($data['data'][$language]))->flatten() : [];
            foreach ($exportFields as $field) {
                $key = sprintf('%s-%s', $name, $field);
                $this->data[$language][$classIdentifier . '.' . $name][$key] = $flatten[$field] ?? '';
            }
        }
    }

    public function parseInstaller($installerDirectory)
    {
        $contents = $installerDirectory . '/contents';
        $items = eZDir::findSubitems($contents, 'f');
        foreach ($items as $item) {
            $this->parseContentStrings($contents . '/' . $item);
        }
        $contentTrees = $installerDirectory . '/contenttrees';
        $dirs = eZDir::findSubitems($contentTrees, 'd');
        foreach ($dirs as $dir) {
            $items = eZDir::findSubitems($contentTrees . '/' . $dir, 'f');
            foreach ($items as $item) {
                $this->parseContentStrings($contentTrees . '/' . $dir . '/' . $item, $dir);
            }
        }
    }
}