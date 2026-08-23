<?php

namespace App\Http\Controllers;

use App\Models\Wealth\Screenshot;
use App\Models\Wealth\Source;
use App\Services\Wealth\ScreenshotTextParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WealthController
{
    public function index(): View
    {
        return view('wealth.index');
    }

    public function data(): array
    {
        $sources = DB::select("
            SELECT s.id, s.who, s.description, s.category_id, c.name as category_name,
                   s.regular_amount, s.current_amount
            FROM wealth_sources s
            JOIN wealth_source_categories c ON c.id = s.category_id
            ORDER BY s.category_id, s.id
        ");
        $totals  = DB::select("
            select c.name, sum(s.current_amount) as total
            from wealth_sources s
            join wealth_source_categories c on (c.id = s.category_id)
            group by c.id");

        $totals[] = (object) [
            'name'  => 'Total',
            'total' => array_reduce($totals, function ($carry, $item) {
                return $carry + $item->total;
            })
        ];

        return [
            'sources' => $sources,
            'totals'  => $totals,
        ];
    }

    public function history(): array
    {
        $totalOverTime = DB::select("
            SELECT d.date, SUM(COALESCE((
                SELECT sv.value
                FROM wealth_source_values sv
                WHERE sv.source_id = s.id AND DATE(sv.created_at) <= d.date
                ORDER BY sv.created_at DESC
                LIMIT 1
            ), 0)) as total
            FROM (SELECT DISTINCT DATE(created_at) as date FROM wealth_source_values) d
            CROSS JOIN wealth_sources s
            GROUP BY d.date
            ORDER BY d.date ASC
        ");

        $bySource = DB::select("
            SELECT sv.source_id as id, sv.value, DATE(sv.created_at) as date
            FROM wealth_source_values sv
            INNER JOIN (
                SELECT source_id, DATE(created_at) as date, MAX(id) as max_id
                FROM wealth_source_values
                GROUP BY source_id, DATE(created_at)
            ) latest ON sv.id = latest.max_id
            ORDER BY sv.source_id, DATE(sv.created_at) ASC
        ");

        return [
            'total_over_time' => $totalOverTime,
            'by_source'       => $bySource,
        ];
    }

    public function update(Request $request): array
    {
        $id    = $request->get('id');
        $name  = $request->get('name');
        $value = $request->integer('value');

        $source = Source::findOrFail($id);

        if ($name === 'current' && $value !== $source->current_amount) {
            $this->updateAmount($source, $value);
        } elseif ($value !== $source->regular_amount) {
            $source->regular_amount = $value;
            $source->save();
        }

        return [
            'status' => 'ok'
        ];
    }

    public function screenshot(Request $request, ScreenshotTextParser $parser): array
    {
        $text = $request->get('text');
        $app  = $request->get('app', 'Unknown');

        Screenshot::create([
            'text' => $text,
            'app'  => $app,
        ]);

        $updates = $parser->parse($text, $app);

        foreach ($updates as $sourceId => $amount) {
            if ($amount) {
                $this->updateAmount(Source::findOrFail($sourceId), (int) $amount);
            }
        }

        return [
            'status' => 'ok'
        ];
    }

    private function updateAmount(Source $source, int $amount)
    {
        if ($amount > 0 && $amount != $source->current_amount) {
            $source->current_amount = $amount;
            $source->save();

            $source->values()->create([
                'value' => $amount
            ]);
        }
    }
}
