<?php

declare(strict_types=1);

use App\Http\Controllers\Web\DomainController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\HostingController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\OrderController;
use App\Http\Controllers\Web\PageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website (Antler template, layouts.front)
|--------------------------------------------------------------------------
| Auth routes (login, register, logout, password reset) are registered by
| Laravel Fortify — see App\Providers\FortifyServiceProvider for the views.
*/

Route::get('/', [HomeController::class, 'index'])->name('front.home');

Route::get('/webhosting', [HostingController::class, 'webhosting'])->name('front.webhosting');
Route::get('/gamehosting', [HostingController::class, 'gamehosting'])->name('front.gamehosting');
Route::get('/vps', [HostingController::class, 'vps'])->name('front.vps');

Route::get('/domeny', [DomainController::class, 'index'])->name('front.domains');
Route::post('/domeny/overit', [DomainController::class, 'check'])
    ->middleware('throttle:domain-check')
    ->name('front.domains.check');

Route::get('/objednavka/{plan}', [OrderController::class, 'start'])->name('front.order');

Route::get('/kontakt', [PageController::class, 'contact'])->name('front.contact');
Route::get('/o-nas', [PageController::class, 'about'])->name('front.about');
Route::get('/faq', [PageController::class, 'faq'])->name('front.faq');
Route::get('/obchodni-podminky', [PageController::class, 'legal'])->name('front.legal');
Route::get('/gdpr', [PageController::class, 'gdpr'])->name('front.gdpr');
Route::get('/cookies', [PageController::class, 'cookies'])->name('front.cookies');
Route::get('/sla', [PageController::class, 'sla'])->name('front.sla');
Route::get('/refundace', [PageController::class, 'refundPolicy'])->name('front.refund-policy');

Route::get('/lang/{locale}', LocaleController::class)->name('locale.switch');
