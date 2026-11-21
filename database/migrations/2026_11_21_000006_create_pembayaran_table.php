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
        if (! Schema::hasTable('pembayaran')) {
            Schema::create('pembayaran', function (Blueprint $table): void {
                $table->id();
                $table->string('nomor_pembayaran', 30)->unique();
                $table->foreignId('penyewaan_id')
                    ->constrained('penyewaan')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
                $table->unsignedBigInteger('perpanjangan_id')->nullable();
                $table->string('jenis_pembayaran', 20)->default('sewa_baru');
                $table->decimal('nominal', 12, 2);
                $table->string('bukti_transfer', 255);
                $table->dateTime('waktu_bayar')->useCurrent();
                $table->string('status_verifikasi', 20)->default('valid');
                $table->foreignId('diverifikasi_oleh')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
