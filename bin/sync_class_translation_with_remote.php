<?php
require 'autoload.php';


$script = eZScript::instance(['description' => "Sync class translation from remote",
    'use-session' => false,
    'use-modules' => false,
    'debug-timing' => true
]);

$script->startup();
$options = $script->getOptions(
    '[remote:][languages:]',
    '',
    [
        'remote' => 'remote url (default is https://trasparenza.comune.bolzano.it)',
        'languages' => 'comma sepeparated language codes (default is ita-IT,ger-DE,eng-GB)',
    ]
);
$script->initialize();
$cli = eZCLI::instance();
$output = new ezcConsoleOutput();

$languages = $options['languages'] ? explode(',', $options['languages']) : ['ger-DE'];
$remote = $options['remote'] ? $options['remote'] : 'https://trasparenza.comune.bolzano.it';
$remoteBaseUrl = rtrim($remote, '/') . '/';

class MyHttpClient extends \Opencontent\Opendata\Rest\Client\HttpClient
{
    public function get($path)
    {
        $url = $this->buildUrl($path);
        eZCLI::instance()->output($url);
        return $this->request('GET', $url);
    }
}

$client = new MyHttpClient(
    $remoteBaseUrl,
    'admin',
    'Krop0t!boO44',
    'classes'

);

$remote = $client->get('');
OCClassTools::setRemoteUrl($remoteBaseUrl . 'classtools/definition/');
foreach ($remote['classes'] as $class) {
//    if ($class['identifier'] != 'document') continue;
    try {
        $cli->warning('Fetch ' . $class['identifier'] . '... ', false);
        $tools = new OCClassTools($class['identifier'], false);
        $tools->compare();
        $data = $tools->getData();
        $cli->warning('ok');
        foreach ($data->notices as $field => $notices) {
            foreach ($notices as $property => $notice) {
                $cli->output(' - ' . $field . '/' . $property);
                if (strpos($property, 'serialized_') !== false) {
                    if ($field == 'properties') {
                        $tools->syncSingleProperty($property);
                    } else {
                        $tools->syncSingleAttribute($field . '/' . $property);
                    }
                }
            }
        }

        $contentClass = eZContentClass::fetchByIdentifier($class['identifier']);
        if ($contentClass instanceof eZContentClass) {
            $remoteRequestUrl = $remoteBaseUrl . 'classtools/extra_definition/' . $class['identifier'];
            eZCLI::instance()->output($remoteRequestUrl);
            $remoteData = json_decode(eZHTTPTool::getDataByURL($remoteRequestUrl), true);
            if($remoteData == false){
                throw new Exception("Dati remoti non trovati", 1);
            }
            $diff = OCClassExtraParametersManager::instance($contentClass)->compare($remoteData);
            foreach ($remoteData['attribute_group'] as $key => $values) {
                foreach ($languages as $language) {
                    if (strpos($key, $language . '::') !== false && isset($values['*'])) {
                        $row = array(
                            'class_identifier' => $contentClass->attribute('identifier'),
                            'attribute_identifier' => '*',
                            'handler' => 'attribute_group',
                            OCClassExtraParameters::getKeyDefinitionName() => $key,
                            'value' => $values['*']
                        );
                        $cli->output(' - ' . $key . ' -> ' . $values['*']);
                        $parameter = new OCClassExtraParameters($row);
                        $parameter->store();
                    }
                }
            }
        }

    }catch (Exception $e){
        $cli->error($e->getMessage());
    }

}

$script->shutdown();