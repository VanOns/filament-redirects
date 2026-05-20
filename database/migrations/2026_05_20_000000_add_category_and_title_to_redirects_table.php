<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->string('category')->nullable()->after('type')->index();
            $table->string('title')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'title']);
        });
    }
};
