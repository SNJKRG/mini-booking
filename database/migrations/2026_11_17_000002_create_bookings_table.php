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
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('slot_id')->constrained('slots')->restrictOnDelete();
            $table->string('customer_name', 255);
            $table->string('customer_email', 255);
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();
        });

        // Не больше одной активной (неотменённой) брони на слот, и следит за этим сама БД:
        DB::statement('CREATE UNIQUE INDEX bookings_one_active_per_slot ON bookings (slot_id) WHERE cancelled_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
