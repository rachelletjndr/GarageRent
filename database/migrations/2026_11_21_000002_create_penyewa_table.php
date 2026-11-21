<?php

use App\Enums\VerificationStatusEnum;
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
        if (! Schema::hasTable('penyewa')) {
            Schema::create('penyewa', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')
                    ->unique()
                    ->constrained('users')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
                $table->char('nik', 16)->unique();
                $table->string('nama_lengkap', 100);
                $table->string('no_telp', 20);
                $table->string('status_verifikasi', 20)
                    ->default(VerificationStatusEnum::Pending->value);
                $table->foreignId('diverifikasi_oleh')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
                $table->timestamp('tanggal_verifikasi')->nullable();
                $table->text('catatan_penolakan')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penyewa');
    }
};
