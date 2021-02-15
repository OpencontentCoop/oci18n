<?php

namespace Opencontent\I18n;

use Google\Spreadsheet\CellEntry;
use Google\Spreadsheet\Worksheet;

class GoogleSheetCsvParser
{
    public static $useCsvUrl = false;

    public static $delimiter = ",";

    public static function parse(Worksheet $worksheet, $skip_empty_lines = true, $trim_fields = false)
    {
        if (self::$useCsvUrl) {
            $csv = self::parseFromCSVUrl($worksheet, $skip_empty_lines, $trim_fields);
        } else {
            $csv = self::parseFromCellFeed($worksheet, $skip_empty_lines, $trim_fields);
        }

        $headers = array_shift($csv);
        array_walk($csv, function (&$a) use ($headers) {
            $a = array_combine($headers, $a);
        });

        return $csv;
    }

    private static function parseFromCSVUrl(Worksheet $worksheet, $skip_empty_lines, $trim_fields)
    {
        $csv_string = $worksheet->getCsv();
        $delimiter = self::$delimiter;
        $csv = self::parseCsv($csv_string, $delimiter, $skip_empty_lines, $trim_fields, true);
        return $csv;
    }

    private static function parseCsv($csv_string, $delimiter, $skip_empty_lines, $trim_fields, $encode)
    {
        return array_map(
            function ($line) use ($delimiter, $trim_fields, $encode) {
                return array_map(
                    function ($field) use ($encode) {
                        if ($encode) {
                            $field = utf8_decode(urldecode($field));
                        }
                        return str_replace('!!Q!!', '"', $field);
                    },
                    $trim_fields ? array_map('trim', explode($delimiter, $line)) : explode($delimiter, $line)
                );
            },
            preg_split(
                $skip_empty_lines ? ($trim_fields ? '/( *\R)+/s' : '/\R+/s') : '/\R/s',
                preg_replace_callback(
                    '/"(.*?)"/s',
                    function ($field) use ($encode) {
                        return $encode ? urlencode(utf8_encode($field[1])) : $field[1];
                    },
                    $enc = preg_replace('/(?<!")""/', '!!Q!!', $csv_string)
                )
            )
        );
    }

    private static function parseFromCellFeed(Worksheet $worksheet, $skip_empty_lines, $trim_fields)
    {
        $cellFeed = $worksheet->getCellFeed();
        $rowCount = $worksheet->getRowCount();
        $colCount = $worksheet->getColCount();
        $realColCount = 1;
        for ($col = 1; $col <= $colCount; $col++) {
            $cell = $cellFeed->getCell(1, $col);
            if ($cell instanceof CellEntry && !empty($cell->getContent())) {
                $realColCount = $col;
            }
        }

        $csv = [];
        for ($row = 1; $row <= $rowCount; $row++) {
            $line = [];
            for ($col = 1; $col <= $realColCount; $col++) {
                $cell = $cellFeed->getCell($row, $col);
                if ($cell instanceof CellEntry) {
                    $content = $cell->getContent();
                    if ($trim_fields) {
                        $content = trim($content);
                    }
                    $line[$col] = $content;
                } else {
                    $line[$col] = '';
                }
            }
            if (trim(implode('', $line)) != '' || !$skip_empty_lines) {
                $csv[$row] = $line;
            }
        }

        return $csv;
    }
}