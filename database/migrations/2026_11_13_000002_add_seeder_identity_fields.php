<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asramas', fn (Blueprint $t) => $t->string('gender')->nullable()->index());
        Schema::table('users', function (Blueprint $t) {
            $t->string('seed_key')->nullable()->unique();
            $t->boolean('credentials_ready')->default(true);
            $t->boolean('must_change_password')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['seed_key', 'credentials_ready', 'must_change_password']));
        Schema::table('asramas', fn (Blueprint $t) => $t->dropColumn('gender'));
    }
};
