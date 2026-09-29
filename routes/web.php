<?php

use App\Http\Controllers\EventRegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EventRegistrationController::class, 'index'])->name('events.index');
Route::get('/event/{id}', [EventRegistrationController::class, 'show'])->name('events.show');
Route::post('/event/{id}/review', [EventRegistrationController::class, 'review'])->name('events.register.review');
Route::post('/event/{id}/process', [EventRegistrationController::class, 'process'])->middleware('throttle:10,1')->name('events.register.process');
Route::get('/event/{id}/status', [EventRegistrationController::class, 'status'])->name('events.register.status');
Route::get('/event/{id}/status/check', [EventRegistrationController::class, 'checkStatus'])->middleware('throttle:30,1')->name('events.register.status.check');
Route::post('/event/{id}/retry', [EventRegistrationController::class, 'retry'])->middleware('throttle:5,1')->name('events.register.retry');
if (app()->environment(['local', 'testing'])) {
    Route::post('/event/{id}/demo-payment', [EventRegistrationController::class, 'simulatePayment'])->name('events.register.demo-payment');
}

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\WebhookController;

Route::prefix('admin')->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:admin-login')->name('admin.login.submit');
    Route::post('logout', [AuthController::class, 'logout'])->name('admin.logout');

    Route::middleware('auth')->group(function () {
        Route::get('events', [EventController::class, 'index'])->name('admin.events.index');
        Route::get('events/create', [EventController::class, 'create'])->name('admin.events.create');
        Route::post('events', [EventController::class, 'store'])->name('admin.events.store');
        Route::get('events/{event}/edit', [EventController::class, 'edit'])->name('admin.events.edit');
        Route::put('events/{event}', [EventController::class, 'update'])->name('admin.events.update');
        Route::delete('events/{event}', [EventController::class, 'destroy'])->name('admin.events.destroy');

        Route::get('registrations', [RegistrationController::class, 'index'])->name('admin.registrations.index');
        Route::get('registrations/export', [RegistrationController::class, 'export'])->name('admin.registrations.export');
        Route::get('registrations/{registration}', [RegistrationController::class, 'show'])->name('admin.registrations.show');
        Route::patch('registrations/{registration}/payment', [RegistrationController::class, 'updatePayment'])->name('admin.registrations.payment.update');
    });
});

Route::post('webhook/mercadopago', [WebhookController::class, 'handle'])->name('webhook.mercadopago');
