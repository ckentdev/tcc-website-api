<?php

use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/vote/auth', [StudentController::class, 'auth'])
        ->middleware('throttle:student-auth')
        ->name('student.auth');
});
