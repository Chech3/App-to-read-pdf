<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfFileController;
use App\Http\Controllers\PdfAnnotatorController;

Route::get('/', function () {
    return view('welcome');
});


Route::resource('pdfs', PdfFileController::class);

Route::get('/pdfs/{pdf}/annotate', [PdfAnnotatorController::class, 'show'])->name('pdfs.annotate');
Route::post('/pdfs/{pdf}/annotate', [PdfAnnotatorController::class, 'store'])->name('pdfs.annotate.save');



