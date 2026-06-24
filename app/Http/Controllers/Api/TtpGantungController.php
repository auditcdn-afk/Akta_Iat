<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanTtpGantung;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TtpGantungController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanTtpGantung::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanTtpGantung::updateOrCreate(
            ['plan_audit_id' => $planId],
            [
                'tgl_audit' => $request->input('tglAudit') ?: null,
                'ttp_json'  => $request->input('ttp', []),
                'updated_by' => $who,
            ]
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
    }

    public function parseHtml(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file']);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['html', 'htm'], true)) {
            return response()->json(['message' => 'File harus berformat .html atau .htm.'], 422);
        }

        $content = file_get_contents($file->getRealPath());

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $content);
        $rows = $doc->getElementsByTagName('tr');

        $items       = [];
        $leasing     = '';
        $no          = 0;

        foreach ($rows as $tr) {
            $cells = $tr->getElementsByTagName('td');
            if ($cells->length === 0) continue;

            // Leasing group header: second cell has colspan=6 with bold name
            if ($cells->length >= 2) {
                $cell1 = $cells->item(1);
                $span  = $cell1 ? (int)($cell1->getAttribute('colspan') ?: 0) : 0;
                if ($span === 6) {
                    $name = trim(strip_tags($cell1->textContent));
                    $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $name = trim(preg_replace('/\s+/', ' ', $name));
                    if ($name !== '' && $name !== "\xc2\xa0") {
                        $leasing = $name;
                    }
                    continue;
                }
            }

            // Data row: first cell is a number, second is TTP no
            if ($cells->length < 10) continue;
            $noCell  = trim($cells->item(0)->textContent);
            $ttpNo   = trim($cells->item(1)->textContent);
            if (!is_numeric($noCell) || $ttpNo === '') continue;
            // TTP no must look like DX123456 or similar alpha+digits
            if (!preg_match('/[A-Za-z]/', $ttpNo)) continue;

            $no++;
            $tglTtp       = $this->normDate(trim($cells->item(2)->textContent));
            $noFaktur     = trim($cells->item(3)->textContent);
            $nama         = trim($cells->item(4)->textContent);
            $nilai        = $this->parseNum($cells->item(5)->textContent);
            $sudahCair    = $this->parseNum($cells->item(6)->textContent);
            $pencTgl      = $this->normDate(trim($cells->item(7)->textContent));
            $pencNilai    = $this->parseNum($cells->item(8)->textContent);
            $belumCair    = $this->parseNum($cells->item(9)->textContent);
            $keterangan   = $cells->length > 10 ? trim($cells->item(10)->textContent) : '';
            $keterangan   = preg_replace('/\s+/', ' ', $keterangan);

            $items[] = [
                'leasing'      => $leasing,
                'noTtp'        => $ttpNo,
                'tglTtp'       => $tglTtp,
                'noFaktur'     => $noFaktur,
                'nama'         => $nama,
                'nilai'        => $nilai,
                'sudahCair'    => $sudahCair,
                'pencTgl'      => $pencTgl,
                'pencNilai'    => $pencNilai,
                'belumCair'    => $belumCair,
                'keterangan'   => $keterangan,
                'fisik'        => false,
            ];
        }

        return response()->json(['data' => $items]);
    }

    private function parseNum(mixed $val): float
    {
        $s = strip_tags((string)$val);
        $s = preg_replace('/[^0-9.\-]/', '', $s);
        return ($s === '' || $s === '-') ? 0 : (float)$s;
    }

    private function normDate(string $val): string
    {
        $val = trim(strip_tags($val));
        if ($val === '' || $val === '-') return '';
        // DD-MM-YYYY
        if (preg_match('#^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$#', $val, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $val)) return substr($val, 0, 10);
        return '';
    }
}
