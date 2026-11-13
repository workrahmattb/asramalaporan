<?php

namespace App\Http\Controllers;

use App\Services\ReportStatisticsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    public function __invoke(Request $request, ReportStatisticsService $stats): BinaryFileResponse
    {
        abort_unless(in_array($request->user()->role, ['super_admin', 'admin', 'koordinator']), 403);
        $data = $request->validate(['month' => 'required|date_format:Y-m', 'asrama_id' => 'nullable|integer', 'user_id' => 'nullable|integer']);
        $rows = $stats->monthly($request->user(), $data['month'], empty($data['asrama_id']) ? null : (int) $data['asrama_id'], empty($data['user_id']) ? null : (int) $data['user_id']);
        $values = [['Musyrifah', 'Asrama', 'Hari penempatan', 'Laporan terkirim', 'Rata-rata pelaksanaan (%)', 'Hari belum melapor']];
        foreach ($rows as $row) {
            $values[] = [$row['name'], $row['asrama'], $row['expected'], $row['submitted'], $row['average'], $row['missing']];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($values as $i => $cells) {
            $xml .= '<row r="'.($i + 1).'">';
            foreach ($cells as $j => $value) {
                $ref = chr(65 + $j).($i + 1);
                $xml .= is_numeric($value) ? '<c r="'.$ref.'"><v>'.$value.'</v></c>' : '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            } $xml .= '</row>';
        } $xml .= '</sheetData></worksheet>';
        $path = tempnam(sys_get_temp_dir(), 'simus-export-');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Rekap SIMUS" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        return response()->download($path, 'simus-'.$data['month'].'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }
}
