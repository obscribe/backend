<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SPA catch-all: serve the Vue frontend for any non-API route
// This enables client-side routing in production (Docker/self-host)
Route::fallback(function () {
    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return response()->file($indexPath, [
            'Content-Type' => 'text/html',
        ]);
    }
    // In development without built frontend, show Laravel welcome
    return view('welcome');
});
