<?php

namespace Opencontent\I18n;

use eZCLI;
use eZDir;
use SQLICSVDoc;
use SQLICSVException;
use SQLICSVOptions;

class YmlExportableTags extends YmlExportableContentTypes
{
    protected $targetPath = '/_translations/tags';

    /**
     * @throws SQLICSVException
     */
    public function parseInstaller($installerDirectory)
    {
        $tagTree = $installerDirectory . '/tagtree_csv';
        $items = eZDir::findSubitems($tagTree, 'f');
        foreach ($items as $item) {
            $this->parseCsvTagFile($tagTree . '/' . $item);
        }
    }

    /**
     * @throws SQLICSVException
     */
    private function parseCsvTagFile($filepath)
    {
        eZCLI::instance()->output(' - ' . $filepath);
        $doc = new SQLICSVDoc(new SQLICSVOptions([
            'csv_path' => $filepath,
            'delimiter' => ',',
            'enclosure' => '"',
        ]));
        $doc->parse();
        $dataSource = $doc->rows;
        $cloneDataSource = clone $dataSource;
        foreach ($dataSource as $row) {
            $key = sprintf(
                '%s:%s:%s',
                basename($filepath),
                $this->findParentRow($cloneDataSource, $row)->remoteId,
                $row->remoteId
            );
            $keyword = sprintf('%s.%s', $row->remoteId, 'keyword');
            $synonym = sprintf('%s.%s', $row->remoteId, 'synonym');
            $description = sprintf('%s.%s', $row->remoteId, 'description');
            foreach ($this->languages as $language) {
                $reverseLanguageMap = array_flip(PoEditorClient::$languageMap);
                $locale = $reverseLanguageMap[$language] ?? null;
                $suffix = $locale ?? '';
                $this->data[$language][$key][$keyword] = $row->{'keyword' . ucfirst($suffix)} ?? '';
                $this->data[$language][$key][$synonym] = $row->{'synonym' . ucfirst($suffix)} ?? '';
                $this->data[$language][$key][$description] = $row->{'description' . ucfirst($suffix)} ?? '';
            }
        }
    }

    private function findParentRow($dataSource, $row)
    {
        $parentId = $row->parentId;
        foreach ($dataSource as $item) {
            if ($item->id == $parentId) {
                return $item;
            }
        }
        return (object)['remoteId' => 'root'];
    }
}