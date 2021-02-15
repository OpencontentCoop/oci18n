<?php
require 'autoload.php';

$script = eZScript::instance(['description' => "Create tag translations csv from db",
    'use-session' => false,
    'use-modules' => false,
    'debug-timing' => true
]);

$script->startup();
$options = $script->getOptions(
    '[languages:]',
    '',
    [
        'languages' => 'comma sepeparated language codes (default is ita-IT,ger-DE,eng-GB)'
    ]
);
$script->initialize();
$cli = eZCLI::instance();

$languages = $options['languages'] ? explode(',', $options['languages']) : ['ita-IT', 'ger-DE', 'eng-GB'];


function serializeTag(eZTagsObject $tag)
{
    global $languages;
    $serialized = [
        'url' => $tag->getUrl(true)
    ];
    foreach ($languages as $language){
        $translation = $tag->translationByLocale($language);
        $serialized[$language] = $translation ? $translation->attribute('keyword') : '';
    }
    return $serialized;
}

function getTagChildren($tagID, &$data)
{
    $children = eZTagsObject::fetchList(
        array(
            'parent_id' => $tagID,
            'main_tag_id' => 0
        )
    );

    foreach ($children as $child) {
        $data[] = serializeTag($child);
        getTagChildren((int)$child->attribute('id'), $data);
    }
}


try {
    $fetchParams = array('parent_id' => 0, 'main_tag_id' => 0);
    $limits = array('offset' => 0, 'limit' => 200);
    $children = eZTagsObject::fetchList($fetchParams);
    foreach ($children as $index => $child) {
        $keyword = $child->attribute('keyword');
        $keywordIdentifier = eZCharTransform::instance()->transformByGroup($keyword, 'identifier');
        $cli->output($keyword . ' ', false );
        $csvData = [];
        $csvData[] = array_merge(['url'], $languages);
        $csvData[] = serializeTag($child);
        getTagChildren((int)$child->attribute('id'), $csvData);

        $filename = __DIR__ . '/../temp/translations_tag.' . $keywordIdentifier . '.csv';
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
    }

} catch (Exception $e) {
    $errCode = $e->getCode();
    $errCode = $errCode != 0 ? $errCode : 1; // If an error has occured, script must terminate with a status other than 0
    $cli->error($e->getMessage());
}

$script->shutdown();