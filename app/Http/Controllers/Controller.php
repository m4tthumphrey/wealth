<?php

namespace App\Http\Controllers;

use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use stdClass;

class Controller
{
    public function index(): View
    {
        $sources = Source::all();
        $totals  = DB::select("
            select c.name, sum(s.current_amount) as total
            from sources s
            join source_categories c on (c.id = s.category_id)
            group by c.id");

        $totals[] = (object) [
            'name'  => 'Total',
            'total' => array_reduce($totals, function ($carry, $item) {
                return $carry + $item->total;
            })
        ];

        return view('index', [
            'sources' => $sources,
            'totals'  => $totals
        ]);
    }

    public function update(Request $request): array
    {
        $id    = $request->get('id');
        $name  = $request->get('name');
        $value = $request->integer('value');

        $source = Source::findOrFail($id);

        if ($name === 'current' && $value !== $source->current_amount) {
            $source->current_amount = $value;
            $source->save();

            $source->values()->create([
                'value' => $value
            ]);
        } elseif ($value !== $source->regular_amount) {
            $source->regular_amount = $value;
            $source->save();
        }

        return [
            'status' => 'ok'
        ];
    }
}
