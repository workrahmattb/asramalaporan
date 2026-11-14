<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('santris', function (Blueprint $table) {
            $table->renameColumn('nis', 'nisn');
            $table->string('gender')->nullable()->change();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('father_phone', 50)->nullable();
            $table->string('mother_phone', 50)->nullable();
        });
        Schema::table('santri_assessments', fn (Blueprint $table) => $table->renameColumn('student_nis', 'student_nisn'));
    }

    public function down(): void
    {
        Schema::table('santri_assessments', fn (Blueprint $table) => $table->renameColumn('student_nisn', 'student_nis'));
        DB::table('santris')->whereNull('gender')->update(['gender' => '']);
        Schema::table('santris', function (Blueprint $table) {
            $table->renameColumn('nisn', 'nis');
            $table->string('gender')->nullable(false)->change();
            $table->dropColumn(['birth_place', 'birth_date', 'father_name', 'mother_name', 'father_phone', 'mother_phone']);
        });
    }
};
