<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('musyrifah');
            $t->boolean('is_active')->default(true);
        });
        Schema::create('asramas', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->string('unit')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('asrama_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asrama_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('assignment_role');
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->index(['user_id', 'start_date', 'end_date']);
        });
        Schema::create('daily_tasks', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('scheduled_time')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('daily_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('asrama_id')->constrained();
            $t->date('report_date');
            $t->string('status')->default('draft')->index();
            $t->text('obstacles')->nullable();
            $t->text('notes')->nullable();
            $t->text('reviewer_notes')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'report_date', 'asrama_id']);
            $t->index(['asrama_id', 'report_date']);
        });
        Schema::create('daily_report_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('daily_report_id')->constrained()->cascadeOnDelete();
            $t->foreignId('daily_task_id')->constrained();
            $t->string('task_title');
            $t->unsignedInteger('sort_order');
            $t->boolean('is_completed')->default(false);
            $t->timestamps();
            $t->unique(['daily_report_id', 'daily_task_id']);
        });
        Schema::create('daily_report_photos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('daily_report_id')->constrained()->cascadeOnDelete();
            $t->string('file_path');
            $t->string('original_name');
            $t->string('mime_type');
            $t->unsignedBigInteger('file_size');
            $t->timestamps();
        });
        Schema::create('report_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('daily_report_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->string('action');
            $t->json('changes');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['report_audits', 'daily_report_photos', 'daily_report_items', 'daily_reports', 'daily_tasks', 'asrama_user', 'asramas'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'is_active']));
    }
};
