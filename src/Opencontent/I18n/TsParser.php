<?php

namespace Opencontent\I18n;

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
            throw new Exception("Source not found or invalid");
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
            $data[$item[2]] = '[' . $item[0] . '] ' . $item[1];
        }

        return $data;
    }

    public function toCSV()
    {
        if (!$this->isParsed) {
            $this->parse();
        }

        $filename = str_replace('/', '-', $this->sourcePath) . '.csv';
        eZFile::create($filename, false, null);

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

}