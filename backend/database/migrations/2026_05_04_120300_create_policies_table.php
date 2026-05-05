<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('action', 32);
            $table->json('rule_json');
            $table->timestamp('execution_at')->nullable()->index();
            $table->string('cron_expression')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->string('last_status', 32)->default('idle');
            $table->text('hold_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};
