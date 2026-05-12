<?php

use Illuminate\Support\Facades\Route;
use Spatie\LaravelPdf\Facades\Pdf;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/test-pdf', function () {
    return Pdf::view('certificate', ['name' => 'Anas Aln3san', 'course' => 'Laravel Backend'])
        ->format('a4')
        ->landscape()
        ->name('test-certificate.pdf');
});

// require __DIR__.'/auth.php';
