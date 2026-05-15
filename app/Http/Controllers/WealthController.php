<?php

namespace App\Http\Controllers;

use App\Models\Wealth\Screenshot;
use App\Models\Wealth\Source;
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

    public function screenshot(Request $request): array
    {
        $text = $request->get('text');
        $app  = $request->get('app', 'Unknown');

        $screenshot = Screenshot::create([
            'text' => $text,
            'app'  => $app
        ]);

        $lines  = explode("\n", $text);
        $updates = [];

        switch ($app) {
            case 'Trading 212':
                if (str_contains($lines[1], '212 Invest')) {
                    $updates[3] = $lines[3];
                } elseif (str_contains($lines[1], 'CashISA')) {
                    $updates[4] = $lines[3];
                }
                break;
            case 'Chip':
                $updates[10] = $lines[7];
                break;
            case 'MyAviva':
                $updates[9] = $lines[8];
                break;
            case 'NatWest':
                $updates[1] = $lines[17];
                break;
            case 'Chrome':
                if (str_contains($lines[2], 'retiready.co.uk')) {
                    $updates[5] = $lines[10];
                    $updates[6] = $lines[12];
                }
                break;
            case 'HL':
                $updates[7] = $lines[6];
                break;
            default:
                if (str_contains($lines[2], 'COURTIERS')) {
                    $updates[8] = str_replace(['£', 'k'], '', $lines[8]) * 100;
                }
                break;
        }

        if (count($updates)) {
            foreach ($updates as $sourceId => $amount) {
                $source = Source::findOrFail($sourceId);
                $amount = str_replace(['£', ','], '', $amount);

                if ($amount) {
                    $this->updateAmount($source, (int) $amount);
                }
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
