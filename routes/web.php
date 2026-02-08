<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RecapController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/health', [HealthController::class, 'show'])->name('health');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'showForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.store')->middleware('throttle:login');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Authenticated routes
Route::middleware(['auth', 'force.password.change'])->group(function (): void {
    // Chat
    Route::get('/', fn () => redirect()->route('chat.index'));
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{conversation}/stream', [ChatController::class, 'stream'])->name('chat.stream');
    Route::post('/chat/search', [ChatController::class, 'search'])->name('chat.search');

    // Conversations
    Route::get('/conversations', fn () => redirect()->route('chat.index'))->name('conversations.index');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::put('/conversations/{conversation}', [ConversationController::class, 'update'])->name('conversations.update');
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');

    // Recaps
    Route::get('/recaps', [RecapController::class, 'index'])->name('recaps.index');
    Route::get('/recaps/{recap}', [RecapController::class, 'show'])->name('recaps.show');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications.update');
});

// Password change (auth but exempt from force password change)
Route::middleware('auth')->group(function (): void {
    Route::get('/password/change', [PasswordChangeController::class, 'showForm'])->name('password.change');
    Route::post('/password/change', [PasswordChangeController::class, 'update'])->name('password.change.store');
});

// Admin routes
Route::middleware(['auth', 'force.password.change', 'admin'])->prefix('admin')->group(function (): void {
    // Users
    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users.index');
    Route::get('/users/create', [\App\Http\Controllers\Admin\UserController::class, 'create'])->name('admin.users.create');
    Route::post('/users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('admin.users.store');
    Route::put('/users/{user}/role', [\App\Http\Controllers\Admin\UserController::class, 'updateRole'])->name('admin.users.role');
    Route::put('/users/{user}/status', [\App\Http\Controllers\Admin\UserController::class, 'updateStatus'])->name('admin.users.status');
    Route::delete('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy');

    // Sources
    Route::get('/sources', [\App\Http\Controllers\Admin\SourceController::class, 'index'])->name('admin.sources.index');
    Route::get('/sources/create', [\App\Http\Controllers\Admin\SourceController::class, 'create'])->name('admin.sources.create');
    Route::post('/sources', [\App\Http\Controllers\Admin\SourceController::class, 'store'])->name('admin.sources.store');
    Route::get('/sources/{source}/edit', [\App\Http\Controllers\Admin\SourceController::class, 'edit'])->name('admin.sources.edit');
    Route::put('/sources/{source}', [\App\Http\Controllers\Admin\SourceController::class, 'update'])->name('admin.sources.update');
    Route::delete('/sources/{source}', [\App\Http\Controllers\Admin\SourceController::class, 'destroy'])->name('admin.sources.destroy');
    Route::post('/sources/{source}/reindex', [\App\Http\Controllers\Admin\SourceController::class, 'reindex'])->name('admin.sources.reindex');
    Route::post('/sources/{source}/rechunk', [\App\Http\Controllers\Admin\SourceController::class, 'rechunk'])->name('admin.sources.rechunk');
    Route::post('/sources/rechunk-all', [\App\Http\Controllers\Admin\SourceController::class, 'rechunkAll'])->name('admin.sources.rechunk-all');
    Route::post('/sources/upload', [\App\Http\Controllers\Admin\SourceController::class, 'upload'])->name('admin.sources.upload');

    // Settings
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'edit'])->name('admin.settings.edit');
    Route::put('/settings/branding', [\App\Http\Controllers\Admin\SettingsController::class, 'updateBranding'])->name('admin.settings.branding');
    Route::put('/settings/llm', [\App\Http\Controllers\Admin\SettingsController::class, 'updateLlm'])->name('admin.settings.llm');
    Route::put('/settings/embedding', [\App\Http\Controllers\Admin\SettingsController::class, 'updateEmbedding'])->name('admin.settings.embedding');
    Route::post('/settings/models/refresh', [\App\Http\Controllers\Admin\SettingsController::class, 'refreshModels'])->name('admin.settings.models.refresh');
    Route::put('/settings/chat', [\App\Http\Controllers\Admin\SettingsController::class, 'updateChat'])->name('admin.settings.chat');
    Route::put('/settings/recap', [\App\Http\Controllers\Admin\SettingsController::class, 'updateRecap'])->name('admin.settings.recap');
    Route::put('/settings/email', [\App\Http\Controllers\Admin\SettingsController::class, 'updateEmail'])->name('admin.settings.email');
    Route::post('/settings/email/test', [\App\Http\Controllers\Admin\SettingsController::class, 'testEmail'])->name('admin.settings.email.test');
});
