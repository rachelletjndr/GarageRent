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
        if (! Schema::hasTable('perpanjangan_sewa')) {
            Schema::create('perpanjangan_sewa', function (Blueprint $table): void {
                $table->id();
                $table->string('nomor_perpanjangan', 30)->unique();
                $table->foreignId('penyewaan_id')
                    ->constrained('penyewaan')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
                $table->foreignId('admin_id')
                    ->constrained('users')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
                $table->date('tanggal_selesai_lama');
                $table->date('tanggal_selesai_baru');
                $table->unsignedInteger('selisih_hari');
                $table->decimal('tarif_harian_snapshot', 12, 2);
                $table->decimal('biaya_perpanjangan', 12, 2);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perpanjangan_sewa');
    }
};
