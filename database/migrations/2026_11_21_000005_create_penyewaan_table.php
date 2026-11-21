<?php

use App\Enums\StatusSewaEnum;
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
        if (! Schema::hasTable('penyewaan')) {
            Schema::create('penyewaan', function (Blueprint $table): void {
                $table->id();
                $table->string('nomor_transaksi', 30)->unique();
                $table->foreignId('penyewa_id')
                    ->constrained('penyewa')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
                $table->string('garasi_id', 10);
                $table->foreign('garasi_id')
                    ->references('id')
                    ->on('garasi')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
                $table->foreignId('kendaraan_id')
                    ->constrained('kendaraan')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
                $table->date('tanggal_mulai');
                $table->date('tanggal_selesai');
                $table->string('tipe_sewa', 20);
                $table->unsignedInteger('durasi');
                $table->decimal('tarif_pokok', 12, 2);
                $table->decimal('biaya_listrik', 12, 2)->default(0.00);
                $table->decimal('total_bayar', 12, 2);
                $table->string('status_sewa', 20)->default(StatusSewaEnum::Aktif->value);
                $table->timestamps();

                $table->index(['garasi_id', 'tanggal_mulai', 'tanggal_selesai', 'status_sewa'], 'idx_penyewaan_jadwal');
                $table->index(['penyewa_id', 'status_sewa'], 'idx_penyewaan_penyewa');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penyewaan');
    }
};
