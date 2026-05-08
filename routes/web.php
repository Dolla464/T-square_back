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
Route::get('/phpinfo-test', function () {
    phpinfo();
});
Route::get('/test-gd', function () {
    return [
        'loaded' => extension_loaded('gd'),
        'functions' => get_extension_funcs('gd'),
        'png_func' => function_exists('imagecreatefrompng'),
    ];
});
//require __DIR__.'/auth.php';
