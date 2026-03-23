<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('password/confirm', fn () => redirect()->route('admin'))->name('password.confirm');

Auth::routes(['reset' => false, 'verify' => false]);

$hasUser = fn () => \App\Models\User::count() > 0;

Route::get('/', function () use ($hasUser) {
    if (Auth::check()) {
        return redirect('/admin/home');
    }
    return $hasUser() ? redirect('/login') : redirect('/register');
})->name('home');
Route::get('admin', function () use ($hasUser) {
    if (Auth::check()) {
        return redirect('/admin/home');
    }
    return $hasUser() ? redirect('/login') : redirect('/register');
})->name('admin');

Route::middleware(['auth', 'XSS'])->group(function () {
    Route::get('admin/home', [App\Http\Controllers\PageHomeController::class, 'index']);
    Route::put('admin/home', [App\Http\Controllers\PageHomeController::class, 'update']);
    Route::post('admin/summernote/upload', App\Http\Controllers\SummernoteUploadController::class)->name('admin.summernote.upload');
    Route::post('admin/ai-generate', [App\Http\Controllers\AiGenerateController::class, 'generate'])->name('admin.ai.generate');
});

Route::namespace('Admin')->middleware(['auth', 'XSS'])->group(function () {
    Route::get('admin/skills', [App\Http\Controllers\SkillController::class, 'index']);
    Route::post('admin/skills', [App\Http\Controllers\SkillController::class, 'store']);
    Route::get('admin/skills/{id}', [App\Http\Controllers\SkillController::class, 'show']);
    Route::put('admin/skills/{id}', [App\Http\Controllers\SkillController::class, 'update']);
    Route::delete('admin/skills/{id}', [App\Http\Controllers\SkillController::class, 'destroy']);
    Route::get('admin/skills/order-up/{id}', [App\Http\Controllers\SkillController::class, 'orderUp']);
    Route::get('admin/skills/order-down/{id}', [App\Http\Controllers\SkillController::class, 'orderDown']);
});

Route::namespace('Admin')->middleware(['auth', 'XSS'])->group(function () {
    Route::get('admin/experiences', [App\Http\Controllers\ExperienceController::class, 'index']);
    Route::post('admin/experiences', [App\Http\Controllers\ExperienceController::class, 'store']);
    Route::get('admin/experiences/{id}', [App\Http\Controllers\ExperienceController::class, 'show']);
    Route::put('admin/experiences/{id}', [App\Http\Controllers\ExperienceController::class, 'update']);
    Route::delete('admin/experiences/{id}', [App\Http\Controllers\ExperienceController::class, 'destroy']);
    Route::get('admin/experiences/order-up/{id}', [App\Http\Controllers\ExperienceController::class, 'orderUp']);
    Route::get('admin/experiences/order-down/{id}', [App\Http\Controllers\ExperienceController::class, 'orderDown']);
});

Route::namespace('Admin')->middleware(['auth', 'XSS'])->group(function () {
    Route::get('admin/testimonials', [App\Http\Controllers\TestimonialController::class, 'index']);
    Route::post('admin/testimonials', [App\Http\Controllers\TestimonialController::class, 'store']);
    Route::get('admin/testimonials/{id}', [App\Http\Controllers\TestimonialController::class, 'show']);
    Route::put('admin/testimonials/{id}', [App\Http\Controllers\TestimonialController::class, 'update']);
    Route::delete('admin/testimonials/{id}', [App\Http\Controllers\TestimonialController::class, 'destroy']);
    Route::get('admin/testimonials/order-up/{id}', [App\Http\Controllers\TestimonialController::class, 'orderUp']);
    Route::get('admin/testimonials/order-down/{id}', [App\Http\Controllers\TestimonialController::class, 'orderDown']);
});

Route::namespace('Admin')->middleware(['auth', 'XSS'])->group(function () {
    Route::get('admin/services', [App\Http\Controllers\ServiceController::class, 'index']);
    Route::post('admin/services', [App\Http\Controllers\ServiceController::class, 'store']);
    Route::get('admin/services/{id}', [App\Http\Controllers\ServiceController::class, 'show']);
    Route::put('admin/services/{id}', [App\Http\Controllers\ServiceController::class, 'update']);
    Route::delete('admin/services/{id}', [App\Http\Controllers\ServiceController::class, 'destroy']);
    Route::get('admin/services/order-up/{id}', [App\Http\Controllers\ServiceController::class, 'orderUp']);
    Route::get('admin/services/order-down/{id}', [App\Http\Controllers\ServiceController::class, 'orderDown']);
});

Route::namespace('Admin')->middleware(['auth', 'XSS'])->group(function () {
    Route::get('admin/sections', [App\Http\Controllers\SectionController::class, 'index']);
    Route::put('admin/sections', [App\Http\Controllers\SectionController::class, 'update']);
});

Route::namespace('Admin')->middleware(['auth', 'XSS'])->group(function () {
    Route::get('admin/general', [App\Http\Controllers\GeneralController::class, 'index']);
    Route::put('admin/general', [App\Http\Controllers\GeneralController::class, 'update']);
    Route::get('admin/ai', [App\Http\Controllers\GeneralController::class, 'aiIndex']);
    Route::put('admin/ai', [App\Http\Controllers\GeneralController::class, 'updateAi']);
    Route::get('admin/terminal', [App\Http\Controllers\GeneralController::class, 'terminalIndex']);
    Route::put('admin/terminal', [App\Http\Controllers\GeneralController::class, 'updateTerminal']);
});

Route::namespace('Admin')->middleware(['auth', 'XSS'])->group(function () {
    Route::get('admin/profile', [App\Http\Controllers\ProfileController::class, 'index']);
    Route::put('admin/profile', [App\Http\Controllers\ProfileController::class, 'update']);
});

Route::namespace('Admin')->middleware(['auth', 'XSS'])->group(function () {
    Route::get('admin/articles/categories', [App\Http\Controllers\CategoryController::class, 'index']);
    Route::post('admin/articles/categories', [App\Http\Controllers\CategoryController::class, 'store']);
    Route::get('admin/articles/categories/{id}', [App\Http\Controllers\CategoryController::class, 'show']);
    Route::put('admin/articles/categories/{id}', [App\Http\Controllers\CategoryController::class, 'update']);
    Route::delete('admin/articles/categories/{id}', [App\Http\Controllers\CategoryController::class, 'destroy']);
});

Route::namespace('Admin')->middleware(['auth', 'XSS'])->group(function () {
    Route::get('admin/articles/posts', [App\Http\Controllers\ArticleController::class, 'index']);
    Route::get('admin/articles/post', [App\Http\Controllers\ArticleController::class, 'create']);
    Route::post('admin/articles/post', [App\Http\Controllers\ArticleController::class, 'store']);
    Route::delete('admin/articles/post/{id}', [App\Http\Controllers\ArticleController::class, 'destroy']);
    Route::get('admin/articles/post/{id}', [App\Http\Controllers\ArticleController::class, 'show']);
    Route::put('admin/articles/post/{id}', [App\Http\Controllers\ArticleController::class, 'update']);
});
Route::namespace('Admin')->middleware(['auth', 'XSS'])->group(function () {
    Route::get('admin/projects/categories', [App\Http\Controllers\ProjectCategoryController::class, 'index']);
    Route::post('admin/projects/categories', [App\Http\Controllers\ProjectCategoryController::class, 'store']);
    Route::put('admin/projects/categories/{id}', [App\Http\Controllers\ProjectCategoryController::class, 'update']);
    Route::delete('admin/projects/categories/{id}', [App\Http\Controllers\ProjectCategoryController::class, 'destroy']);
});

Route::namespace('Admin')->middleware(['auth', 'XSS'])->group(function () {
    Route::get('admin/projects/projects', [App\Http\Controllers\ProjectController::class, 'index']);
    Route::get('admin/projects/project', [App\Http\Controllers\ProjectController::class, 'create']);
    Route::post('admin/projects/project', [App\Http\Controllers\ProjectController::class, 'store']);
    Route::delete('admin/projects/project/{id}', [App\Http\Controllers\ProjectController::class, 'destroy']);
    Route::get('admin/projects/project/{id}', [App\Http\Controllers\ProjectController::class, 'show']);
    Route::put('admin/projects/project/{id}', [App\Http\Controllers\ProjectController::class, 'update']);
});

