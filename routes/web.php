<?php

use App\Http\Controllers\FirstPasswordController;
use App\Http\Controllers\ReportExportController;
use App\Http\Middleware\EnsureActive;
use App\Livewire\Admin\ManageAsrama;
use App\Livewire\Admin\ManageMusyrifah;
use App\Livewire\Admin\ManageTasks;
use App\Livewire\Dashboard\AdminDashboard;
use App\Livewire\Dashboard\KoordinatorDashboard;
use App\Livewire\Dashboard\MusyrifahDashboard;
use App\Livewire\Reports\MonthlyRecap;
use App\Livewire\Reports\ReportDetail;
use App\Livewire\Reports\ReportForm;
use App\Livewire\Reports\ReportHistory;
use App\Livewire\Reports\ReportMonitoring;
use App\Models\DailyReportPhoto;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware('auth')->group(function () {
    Route::get('account/change-password', [FirstPasswordController::class, 'edit'])->name('password.first-change');
    Route::post('account/change-password', [FirstPasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.first-change.save');
});
Route::redirect('/', '/dashboard')->name('home');
Route::middleware(['auth', EnsureActive::class])->group(function () {
    Route::get('dashboard', fn () => redirect()->route(match (auth()->user()->role) {
        'super_admin', 'admin' => 'dashboard.admin','koordinator' => 'dashboard.koordinator',default => 'dashboard.musyrifah'
    }))->name('dashboard');
    Route::get('dashboard/musyrifah', MusyrifahDashboard::class)->name('dashboard.musyrifah');
    Route::get('dashboard/koordinator', KoordinatorDashboard::class)->name('dashboard.koordinator');
    Route::get('dashboard/admin', AdminDashboard::class)->name('dashboard.admin');
    Route::get('reports', ReportHistory::class)->name('reports.index');
    Route::get('reports/monthly', MonthlyRecap::class)->name('reports.monthly');
    Route::get('reports/export', ReportExportController::class)->name('reports.export');
    Route::get('reports/create', ReportForm::class)->name('reports.create');
    Route::get('reports/monitoring', ReportMonitoring::class)->name('reports.monitoring');
    Route::get('reports/{report}/edit', ReportForm::class)->name('reports.edit');
    Route::get('reports/{report}', ReportDetail::class)->name('reports.show');
    Route::get('photos/{photo}', function (DailyReportPhoto $photo) {
        Gate::authorize('view', $photo->report);

        return Storage::disk('local')->response($photo->file_path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    })->name('reports.photos');
    Route::get('admin/users', ManageMusyrifah::class)->name('admin.users');
    Route::get('admin/asramas', ManageAsrama::class)->name('admin.asramas');
    Route::get('admin/tasks', ManageTasks::class)->name('admin.tasks');
});
require __DIR__.'/settings.php';
