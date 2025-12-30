<?php
// admin/includes/SimpleXLSX.php

class SimpleXLSX {
    public static function parse($filename) {
        $data = [];
        $zip = new ZipArchive;
        if ($zip->open($filename)) {
            // 1. Baca Shared Strings (Teks)
            $strings = [];
            if ($xml = $zip->getFromName('xl/sharedStrings.xml')) {
                $xml = simplexml_load_string($xml);
                foreach ($xml->si as $si) {
                    $strings[] = (string)$si->t;
                }
            }

            // 2. Baca Sheet 1
            if ($xml = $zip->getFromName('xl/worksheets/sheet1.xml')) {
                $xml = simplexml_load_string($xml);
                foreach ($xml->sheetData->row as $row) {
                    $rowData = [];
                    foreach ($row->c as $c) {
                        $v = (string)$c->v;
                        // Jika tipe string (s), ambil dari array strings
                        if (isset($c['t']) && (string)$c['t'] === 's') {
                            $v = $strings[intval($v)];
                        }
                        $rowData[] = trim($v);
                    }
                    // Hapus baris kosong
                    if(array_filter($rowData)) {
                        $data[] = $rowData;
                    }
                }
            }
            $zip->close();
        }
        return $data;
    }
}
?>