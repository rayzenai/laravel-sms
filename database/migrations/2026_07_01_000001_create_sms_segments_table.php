<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sms_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // The saved query definition (a condition tree) — never the results.
            $table->json('conditions')->nullable();
            // The match count the last time the segment was used, for at-a-glance
            // scale without recomputing.
            $table->unsignedInteger('previous_count')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_segments');
    }
};
