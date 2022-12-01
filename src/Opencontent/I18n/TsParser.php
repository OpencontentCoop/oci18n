<?php

namespace Opencontent\I18n;

use SimpleXMLElement;
use DOMDocument;
use DOMElement;
use DOMImplementation;

class TsParser
{
    private $sourcePath;

    private $xml;

    private $currentLanguage;

    private $data;

    private $isParsed = false;

    public function __construct($sourcePath)
    {
        $this->sourcePath = $sourcePath;
        $this->xml = new SimpleXMLElement(file_get_contents($this->sourcePath));
        if (!$this->xml) {
            throw new \Exception("Source not found or invalid");
        }

        $this->currentLanguage = 'translation';
        $dirname = dirname($this->sourcePath);
        if ($dirname) {
            $parts = explode('/', $dirname);
            $this->currentLanguage = array_pop($parts);
        }
    }

    public function parse()
    {
        $this->data = [[
            'context',
            'source',
            $this->currentLanguage
        ]];

        foreach ($this->xml->context as $context) {
            $contextName = (string)$context->name;
            foreach ($context->message as $message) {
                $source = (string)$message->source;
                $translation = (string)$message->translation;
                $this->data[] = [$contextName, $source, $translation];
            }
        }

        $this->isParsed = true;
    }

    public function getData()
    {
        if (!$this->isParsed) {
            $this->parse();
        }
        $data = [];
        foreach ($this->data as $item) {
            $data[$item[0]][$item[1]] = $item[2];
        }

        return $data;
    }

    public function getStringsHash()
    {
        if (!$this->isParsed) {
            $this->parse();
        }
        $data = [];
        foreach ($this->data as $item) {
            $translation = $item[2];
            if (empty($translation)){
                $translation = trim($item[1]);
            }
            $data[$translation] = '[' . $item[0] . '] ' . $item[1];
        }

        return $data;
    }

    public function toCSV()
    {
        if (!$this->isParsed) {
            $this->parse();
        }

        $filename = str_replace('/', '-', $this->sourcePath) . '.csv';
        \eZFile::create($filename, false, null);

        if (count($this->data) > 1) {
            $output = fopen($filename, 'a');

            foreach ($this->data as $fields) {
                fputcsv(
                    $output,
                    $fields
                );
            }
        }

        return $filename;
    }

    public function cloneToLanguage($language, $data)
    {
        $doc = new DOMDocument();
        $string = file_get_contents($this->sourcePath);
        $string = preg_replace( array('/\s{2,}/', '/[\t\n]/'), ' ', $string );
        $string = preg_replace("/>s+</", "><", $string );
        $string = str_replace("> <", "><", $string );
        $string = str_replace( array( '&amp;nbsp;', "\xC2\xA0" ), ' ', $string ); // from ezxhtmlxmloutput.php
        $doc->loadXML($string);

        /** @var DOMElement [] $contexts */
        $contexts = $doc->getElementsByTagName('context');
        foreach ($contexts as $context) {
            $contextName = $context->getElementsByTagName('name')->item(0)->nodeValue;

            /** @var DOMElement [] $messages */
            $messages = $context->getElementsByTagName('message');
            foreach ($messages as $message){
                $source = $message->getElementsByTagName('source')->item(0)->nodeValue;
                $translation = $message->getElementsByTagName('translation')->item(0);
                $locations = $message->getElementsByTagName('location');
                for ($i = $locations->length; --$i >= 0; ) {
                    $location = $locations->item($i);
                    $location->parentNode->removeChild($location);
                }
                if (isset($data[$contextName][$source])){
                    $string = $data[$contextName][$source];
                    $translation->removeAttribute('type');
                    $translation->nodeValue = $string;
                }
            }
        }

        $filename = str_replace($this->currentLanguage, $language, $this->sourcePath);

        return [
            'filepath' => $filename,
            'dom' => $doc
        ];
    }

    /**
     * @return mixed
     */
    public function getFilenameInLanguage($language)
    {
        $filename = str_replace($this->currentLanguage, $language, $this->sourcePath);

        return $filename;
    }

    /**
     * @param array $data
     * @return DOMDocument
     */
    public function getDOMDocument($data = array())
    {
        $doc = new DOMDocument();
        $string = file_get_contents($this->sourcePath);
        $string = preg_replace( array('/\s{2,}/', '/[\t\n]/'), ' ', $string );
        $string = preg_replace("/>s+</", "><", $string );
        $string = str_replace("> <", "><", $string );
        $string = str_replace( array( '&amp;nbsp;', "\xC2\xA0" ), ' ', $string ); // from ezxhtmlxmloutput.php
        $doc->loadXML($string);

        /** @var DOMElement [] $contexts */
        $contexts = $doc->getElementsByTagName('context');
        foreach ($contexts as $context) {
            $contextName = $context->getElementsByTagName('name')->item(0)->nodeValue;

            /** @var DOMElement [] $messages */
            $messages = $context->getElementsByTagName('message');
            foreach ($messages as $message){
                $source = $message->getElementsByTagName('source')->item(0)->nodeValue;
                $translation = $message->getElementsByTagName('translation')->item(0);
                $locations = $message->getElementsByTagName('location');
                for ($i = $locations->length; --$i >= 0; ) {
                    $location = $locations->item($i);
                    $location->parentNode->removeChild($location);
                }
                if (isset($data[$contextName][$source])){
                    $string = $data[$contextName][$source];
                    $translation->removeAttribute('type');
                    $translation->nodeValue = $string;
                }
            }
        }

        return $doc;
    }

    /**
     * @return mixed
     */
    public function getCurrentLanguage()
    {
        return $this->currentLanguage;
    }
    
    public static function storeTsFile($extensionName, $language, $data)
    {
        $filepath = "extension/$extensionName/translations/$language/translation.ts";

        \eZDir::mkdir(dirname($filepath), false, true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $implementation = new DOMImplementation();
        $dtd = $implementation->createDocumentType('TS', '', '');
        $dom = $implementation->createDocument('', '', $dtd);
        $dom->encoding = 'utf-8';

        $tsNode = $dom->createElement('TS');
        $tsNode->setAttribute('version', '2.0');
        foreach ($data as $context => $values){
            $contextNode = $dom->createElement('context');

            $nameNode = $dom->createElement('name');
            $nameNode->nodeValue = trim($context);
            $contextNode->appendChild($nameNode);

            foreach ($values as $source => $translation){
                $messageNode = $dom->createElement('message');

                $sourceNode = $dom->createElement('source');
                $sourceNode->nodeValue = trim($source);
                $messageNode->appendChild($sourceNode);

                $translationNode = $dom->createElement('translation');
                if ($translation == ''){
                    $translationNode->setAttribute('type', 'unfinished');
                }else{
                    $translationNode->nodeValue = trim($translation);
                }
                $messageNode->appendChild($translationNode);

                $contextNode->appendChild($messageNode);
            }
            $tsNode->appendChild($contextNode);
        }
        $dom->appendChild($tsNode);

        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->save($filepath);
        
        return $filepath;
    }

}