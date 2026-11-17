<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table): void {
            $table->id();
            $table->timestampTz('starts_at')->unique();
            $table->timestampTz('ends_at');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE slots ADD CONSTRAINT slots_ends_after_starts CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};
