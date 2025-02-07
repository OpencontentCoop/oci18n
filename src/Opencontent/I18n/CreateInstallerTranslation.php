<?php

namespace Opencontent\I18n;

use eZCLI;
use eZDir;
use Symfony\Component\Yaml\Yaml;

class CreateInstallerTranslation
{
    protected $data = [];

    protected $languages;

    public function __construct($languages)
    {
        $this->languages = $languages;
    }

    private function duplicateLanguage($filepath, $dir = null)
    {
        eZCLI::instance()->output(' - ' . $filepath);
        $data = Yaml::parse(file_get_contents($filepath));

        foreach ($this->languages as $language) {
            $data['metadata']['languages'][] = $language;
            $data['data'][$language] = $data['data']['ita-IT'];
        }
        $dataYaml = Yaml::dump($data, 10);
        file_put_contents($filepath, $dataYaml);
    }

    public function parseInstaller($installerDirectory)
    {
        $contents = $installerDirectory . '/contents';
        $items = eZDir::findSubitems($contents, 'f');
        foreach ($items as $item) {
            $this->duplicateLanguage($contents . '/' . $item);
        }
        $contentTrees = $installerDirectory . '/contenttrees';
        $dirs = eZDir::findSubitems($contentTrees, 'd');
        foreach ($dirs as $dir) {
            $items = eZDir::findSubitems($contentTrees . '/' . $dir, 'f');
            foreach ($items as $item) {
                $this->duplicateLanguage($contentTrees . '/' . $dir . '/' . $item, $dir);
            }
        }
    }
}