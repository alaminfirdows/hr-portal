<?php

use Illuminate\Support\Facades\Route;
use Modules\Projects\Http\Controllers\ProjectController;
use Modules\Projects\Http\Controllers\ProjectFieldController;
use Modules\Projects\Http\Controllers\ProjectMemberController;

// Fixed paths first so "fields" is never read as a project id.
Route::get('projects/fields', [ProjectFieldController::class, 'index'])->name('projects.fields.index');
Route::post('projects/fields', [ProjectFieldController::class, 'store'])->name('projects.fields.store');
Route::put('projects/fields/{definition}', [ProjectFieldController::class, 'update'])->name('projects.fields.update');
Route::delete('projects/fields/{definition}', [ProjectFieldController::class, 'destroy'])->name('projects.fields.destroy');

Route::post('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');

Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
Route::put('projects/{project}/members/{member}', [ProjectMemberController::class, 'update'])->name('projects.members.update');
Route::delete('projects/{project}/members/{member}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');

Route::resource('projects', ProjectController::class)->except(['destroy']);
