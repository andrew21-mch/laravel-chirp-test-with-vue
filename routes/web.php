<?php

use App\Http\Controllers\BotManController;
use App\Http\Controllers\CrispController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ChirpController;
use App\Http\Controllers\UploadController;
use Crisp\CrispClient;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});


Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::resource('chirps', ChirpController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->middleware(['auth', 'verified']);


Route::post('/', CrispController::class);

Route::post('uploadfile', [UploadController::class, 'upload'])->name('uploads');
Route::get('uploadfile', [UploadController::class,'file'])->name('file');
// routes/web.php
Route::delete('/delete-upload/{filename}',[UploadController::class, 'delete'])->name('delete.upload');
Route::get('/galery/{id}',[UploadController::class, 'show'])->name('galery.show');
Route::post('/botman', [BotManController::class, 'handle']);


require __DIR__.'/auth.php';
