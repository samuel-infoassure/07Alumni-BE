<?php

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/**
 * SPA catch-all — serves the Ionic Vue frontend for every non-API route.
 * The built index.html is published into public/ via `npm run build:web`.
 */
Route::get('/{any?}', function (): Response {
    $html = public_path('index.html');

    if (! file_exists($html)) {
        abort(503, 'Frontend not built. Run: npm run build:web in 07-engr-alumni/');
    }

    return response(file_get_contents($html))
        ->header('Content-Type', 'text/html; charset=utf-8');
})->where('any', '.*');
