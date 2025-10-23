<?php

namespace App\Http\Controllers;

use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\View\View;

class Controller
{
    public function index(): View
    {
        $sources = Source::all();

        return view('index', [
            'sources' => $sources
        ]);
    }

    public function update(Request $request): array
    {
        $id    = $request->get('id');
        $name  = $request->get('name');
        $value = $request->get('value');

        $source = Source::findOrFail($id);

        if ($name === 'current') {
            $source->current_amount = $value;
            $source->save();

            $source->values()->create([
                'value' => $value
            ]);
        } else {
            $source->regular_amount = $value;
            $source->save();
        }

        return [
            'status' => 'ok'
        ];
    }
}
