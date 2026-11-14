<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('santris', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('nis')->nullable()->unique();
            $t->string('gender');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('santri_placements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('santri_id')->constrained();
            $t->foreignId('asrama_id')->constrained();
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->timestamps();
            $t->index(['santri_id', 'start_date', 'end_date']);
        });
        Schema::create('guidance_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('santri_id')->constrained();
            $t->foreignId('asrama_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->date('note_date');
            $t->string('category');
            $t->text('content');
            $t->text('follow_up')->nullable();
            $t->timestamps();
            $t->index(['santri_id', 'note_date']);
        });
        Schema::create('assessment_periods', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->date('start_date');
            $t->date('end_date');
            $t->boolean('is_active')->default(false);
            $t->json('rating_labels');
            $t->timestamps();
        });
        Schema::create('assessment_aspects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assessment_period_id')->constrained();
            $t->string('title');
            $t->unsignedInteger('sort_order');
            $t->timestamps();
        });
        Schema::create('santri_assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('santri_id')->constrained();
            $t->foreignId('assessment_period_id')->constrained();
            $t->foreignId('asrama_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('student_name');
            $t->string('student_nis')->nullable();
            $t->string('asrama_name');
            $t->string('period_name');
            $t->string('status')->default('draft')->index();
            $t->text('development')->nullable();
            $t->text('next_focus')->nullable();
            $t->text('reviewer_notes')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['santri_id', 'assessment_period_id']);
            $t->index(['asrama_id', 'assessment_period_id']);
        });
        Schema::create('santri_assessment_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('santri_assessment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assessment_aspect_id')->constrained();
            $t->string('aspect_title');
            $t->unsignedInteger('sort_order');
            $t->json('rating_labels');
            $t->string('rating')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['santri_assessment_id', 'assessment_aspect_id'], 'assessment_item_aspect_unique');
        });
        Schema::create('assessment_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('santri_assessment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->string('action');
            $t->json('changes');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['assessment_audits', 'santri_assessment_items', 'santri_assessments', 'assessment_aspects', 'assessment_periods', 'guidance_notes', 'santri_placements', 'santris'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
