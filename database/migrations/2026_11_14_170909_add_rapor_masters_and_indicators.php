<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
        Schema::create('assessment_subjects', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->text('indicator')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('assessment_periods', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->constrained();
            $table->string('semester')->nullable();
        });
        Schema::table('assessment_aspects', function (Blueprint $table) {
            $table->foreignId('assessment_subject_id')->nullable()->constrained();
            $table->text('indicator')->nullable();
        });
        Schema::table('santri_assessment_items', function (Blueprint $table) {
            $table->text('indicator')->nullable();
        });
        foreach (DB::table('assessment_aspects')->select('title')->distinct()->get() as $aspect) {
            $id = DB::table('assessment_subjects')->insertGetId(['title' => $aspect->title, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('assessment_aspects')->where('title', $aspect->title)->update(['assessment_subject_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('santri_assessment_items', fn (Blueprint $table) => $table->dropColumn('indicator'));
        Schema::table('assessment_aspects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assessment_subject_id');
            $table->dropColumn('indicator');
        });
        Schema::table('assessment_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_year_id');
            $table->dropColumn('semester');
        });
        Schema::dropIfExists('assessment_subjects');
        Schema::dropIfExists('academic_years');
    }
};
