<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('external_account_id')->nullable();
            $table->timestamp('last_imported_at')->nullable();
            $table->date('graduation_date')->nullable();
            $table->string('graduation_status', 120)->nullable();
            $table->string('degree_program', 160)->nullable();
        });

        DB::table('students')->update([
            'external_account_id' => DB::raw('google_user_id'),
            'last_imported_at' => DB::raw('last_synced_at'),
        ]);

        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique(['google_user_id']);
            $table->dropColumn(['google_user_id', 'last_synced_at']);
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->string('external_account_id')->nullable(false)->unique()->change();
            $table->index('graduation_date');
            $table->index('graduation_status');
        });

        Schema::table('audit_events', function (Blueprint $table): void {
            $table->string('target_account_id')->nullable();
        });

        DB::table('audit_events')->update([
            'target_account_id' => DB::raw('target_google_id'),
        ]);

        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropIndex(['target_google_id']);
            $table->dropColumn('target_google_id');
            $table->index('target_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->string('target_google_id')->nullable();
        });

        DB::table('audit_events')->update([
            'target_google_id' => DB::raw('target_account_id'),
        ]);

        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropIndex(['target_account_id']);
            $table->dropColumn('target_account_id');
            $table->index('target_google_id');
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->string('google_user_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
        });

        DB::table('students')->update([
            'google_user_id' => DB::raw('external_account_id'),
            'last_synced_at' => DB::raw('last_imported_at'),
        ]);

        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique(['external_account_id']);
            $table->dropIndex(['graduation_date']);
            $table->dropIndex(['graduation_status']);
            $table->dropColumn([
                'external_account_id',
                'last_imported_at',
                'graduation_date',
                'graduation_status',
                'degree_program',
            ]);
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->string('google_user_id')->nullable(false)->unique()->change();
        });
    }
};
