<?php

namespace Opencontent\I18n;

use eZCLI;
use eZDir;
use Symfony\Component\Yaml\Yaml;

class YmlExportableContentTypes
{
    protected $fallbackLanguage = 'ita-IT';

    protected $data = [];

    protected $targetPath = '/_translations/content-types';

    protected $languages;

    public function __construct($languages)
    {
        $this->languages = $languages;
    }

    private function addName($language, $classIdentifier, $data)
    {
        $key = sprintf('%s.%s', $classIdentifier, '_name');
        $this->add($language, $classIdentifier, $key, $data);
    }

    private function addDescription($language, $classIdentifier, $data)
    {
        $key = sprintf('%s.%s', $classIdentifier, '_description');
        $this->add($language, $classIdentifier, $key, $data);
    }

    private function addFieldName($language, $classIdentifier, $field, $data)
    {
        $key = sprintf('%s.%s.%s', $classIdentifier, $field, '_name');
        $this->add($language, $classIdentifier, $key, $data);
    }

    private function addFieldDescription($language, $classIdentifier, $field, $data)
    {
        $key = sprintf('%s.%s.%s', $classIdentifier, $field, '_description');
        $this->add($language, $classIdentifier, $key, $data);
    }

    private function addGroupName($language, $classIdentifier, $group, $data)
    {
        $key = sprintf('%s._groups.%s', $classIdentifier, $group);
        $this->add($language, $classIdentifier, $key, $data);
    }

    private function add($language, $classIdentifier, $key, $data)
    {
        if (!isset($this->data[$language][$classIdentifier][$key])) {
            eZCLI::instance()->output('   -> ' . $key);
            $this->data[$language][$classIdentifier][$key] = $data;
        }
    }

    protected function toArray(): array
    {
        foreach (array_keys($this->data) as $language) {
            ksort($this->data[$language]);
        }

        return $this->data;
    }

    public function parseInstaller($installerDirectory)
    {
        $classes = $installerDirectory . '/classes';
        $items = eZDir::findSubitems($classes, 'f');
        foreach ($items as $item) {
            $this->parseClassStrings($classes . '/' . $item);
        }
        $classeExtra = $installerDirectory . '/classextra';
        $items = eZDir::findSubitems($classeExtra, 'f');
        foreach ($items as $item) {
            $this->parseClassExtraStrings($classeExtra . '/' . $item);
        }
    }

    private function parseClassStrings($filepath)
    {
        eZCLI::instance()->output(' - ' . $filepath);
        $data = Yaml::parse(file_get_contents($filepath));
        $classIdentifier = $data['identifier'] ?? null;
        if (!empty($classIdentifier)) {
            if (isset($data['serialized_name_list'])) {
                foreach ($this->languages as $language) {
                    $this->addName($language, $classIdentifier, $data['serialized_name_list'][$language] ?? '');
                }
            }
            if (isset($data['serialized_description_list'])) {
                foreach ($this->languages as $language) {
                    $this->addDescription(
                        $language,
                        $classIdentifier,
                        $data['serialized_description_list'][$language] ?? ''
                    );
                }
            }
            if (isset($data['data_map'])) {
                foreach ($data['data_map'] as $identifier => $attribute) {
                    if (isset($attribute['serialized_name_list'])) {
                        foreach ($this->languages as $language) {
                            $this->addFieldName(
                                $language,
                                $classIdentifier,
                                $identifier,
                                $attribute['serialized_name_list'][$language] ?? ''
                            );
                        }
                    }
                    if (isset($attribute['serialized_description_list'])) {
                        foreach ($this->languages as $language) {
                            $this->addFieldDescription(
                                $language,
                                $classIdentifier,
                                $identifier,
                                $attribute['serialized_description_list'][$language] ?? ''
                            );
                        }
                    }
                }
            }
        }
    }

    private function parseClassExtraStrings($filepath)
    {
        eZCLI::instance()->output(' - ' . $filepath);
        $classIdentifier = str_replace('.yml', '', basename($filepath));
        $data = Yaml::parse(file_get_contents($filepath));
        if (isset($data['attribute_group'])) {
            foreach ($data['attribute_group'] as $identifier => $attributeGroupDatum) {
                if (strpos($identifier, '::') === false && $identifier !== 'enabled') {
                    foreach ($this->languages as $language) {
                        if (isset($data['attribute_group'][$language . '::' . $identifier]['*'])) {
                            $this->addGroupName(
                                $language,
                                $classIdentifier,
                                $identifier,
                                $data['attribute_group'][$language . '::' . $identifier]['*']
                            );
                        } elseif ($language == $this->fallbackLanguage) {
                            $this->addGroupName(
                                $language,
                                $classIdentifier,
                                $identifier,
                                $data['attribute_group'][$language . '::' . $identifier]['*']
                            );
                        } else {
                            $this->addGroupName($language, $classIdentifier, $identifier, '');
                        }
                    }
                }
            }
        }
    }

    public function dumpTo($directory)
    {
        $ymlArray = $this->toArray();
        $targetDirectory = $directory . $this->targetPath;
        eZDir::mkdir($targetDirectory, false, true);
        $reverseLanguageMap = array_flip(PoEditorClient::$languageMap);
        foreach ($ymlArray as $language => $values) {
            ksort($values);
            $yamlData = Yaml::dump($values, 10);
            $lang = $reverseLanguageMap[$language];
            $filename = $targetDirectory . '/' . $lang . '.yml';
            eZCLI::instance()->error($filename);
            file_put_contents($filename, $yamlData);
        }
    }
}