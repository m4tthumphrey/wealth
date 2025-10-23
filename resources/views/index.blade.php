<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <ul class="flex justify-between">
            @foreach ($totals as $total)
                <li class="p-10 text-center md:p-20">
                    <div class="text-sm md:text-lg">{{ $total->name }}</div>
                    <div class="text-xl font-bold md:text-3xl lg:text-5xl">&pound;{{ number_format($total->total) }}</div>
                </li>
            @endforeach
        </ul>
        <table class="w-full text-sm md:text-xl">
            <thead>
                <tr>
                    <td class="p-3 border-b font-bold">Who</td>
                    <td class="p-3 border-b font-bold">Description</td>
                    <td class="p-3 border-b font-bold">Regular Amount</td>
                    <td class="p-3 border-b font-bold">Current Amount</td>
                </tr>
            </thead>
            <tbody>
            @foreach($sources as $source)
            <tr>
                <td class="p-3 border-b">{{ $source->who }}</td>
                <td class="p-3 border-b">{{ $source->description }}</td>
                <td class="p-3 border-b"><div class="flex w-full"><span class="py-1">&pound;</span><input type="number" value="{{ $source->regular_amount }}" data-id="{{ $source->id }}" data-name="regular" class="amount p-1 grow max-w-16 md:max-w-none" /></div></td>
                <td class="p-3 border-b"><div class="flex w-full"><span class="py-1">&pound;</span><input type="number" value="{{ $source->current_amount }}" data-id="{{ $source->id }}" data-name="current" class="amount p-1 grow max-w-16 md:max-w-none" /></div></td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </body>
</html>
