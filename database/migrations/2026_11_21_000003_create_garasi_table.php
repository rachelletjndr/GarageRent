<?php

use App\Enums\StatusGarasiEnum;
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
        if (! Schema::hasTable('garasi')) {
            Schema::create('garasi', function (Blueprint $table): void {
                $table->string('id', 10)->primary();
                $table->string('tipe_garasi', 20);
                $table->decimal('harga_sewa_harian', 12, 2);
                $table->decimal('harga_sewa_bulanan', 12, 2)
                    ->virtualAs('harga_sewa_harian * 25');
                $table->decimal('tarif_kwh', 10, 2)->nullable();
                $table->string('status', 20)->default(StatusGarasiEnum::Available->value);
                $table->timestamps();

                $table->index(['tipe_garasi', 'status'], 'idx_garasi_tipe_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garasi');
    }
};
