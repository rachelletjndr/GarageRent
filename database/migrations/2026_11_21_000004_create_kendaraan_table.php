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
        if (! Schema::hasTable('kendaraan')) {
            Schema::create('kendaraan', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('penyewa_id')
                    ->constrained('penyewa')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
                $table->string('plat_nomor', 20)->nullable()->unique();
                $table->string('tipe_kendaraan', 20);
                $table->string('merek', 50);
                $table->decimal('kapasitas_kwh', 6, 2)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kendaraan');
    }
};
