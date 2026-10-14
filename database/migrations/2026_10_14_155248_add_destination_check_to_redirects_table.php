<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('redirects', 'destination_health')) {
            return;
        }

        Schema::table('redirects', function (Blueprint $table) {
            $table->string('destination_health')->nullable()->after('last_hit')->index();
            $table->unsignedSmallInteger('destination_status')->nullable()->after('destination_health');
            $table->string('destination_error')->nullable()->after('destination_status');
            $table->timestamp('destination_checked_at')->nullable()->after('destination_error');
        });
    }

    public function down(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->dropIndex(['destination_health']);
            $table->dropColumn(['destination_health', 'destination_status', 'destination_error', 'destination_checked_at']);
        });
    }
};
