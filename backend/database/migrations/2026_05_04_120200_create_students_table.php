<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('google_user_id')->unique();
            $table->string('primary_email');
            $table->string('full_name')->nullable();
            $table->string('department')->nullable()->index();
            $table->string('school_year')->nullable()->index();
            $table->boolean('suspended')->default(false)->index();
            $table->timestamp('deletion_scheduled_at')->nullable()->index();
            $table->boolean('priority_flag')->default(false);
            $table->text('compliance_notes')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
