<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Üye doğrulama (dolandırıcılığa karşı):
 * - TC kimlik: kayıtta istenir; şifreli saklanır, aynı numarayla ikinci hesap açılmasın diye
 *   ayrıca tek yönlü özeti (tc_hash) tutulur.
 * - ÇKS (Çiftçi Kayıt Sistemi): satılık ilan verenler numarayı ve e-Devlet belgesini gönderir,
 *   yönetici panelden onaylar. Belge herkese açık klasörde değil, gizli diskte durur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('tc_kimlik')->nullable()->after('phone');
            $table->string('tc_hash', 64)->nullable()->unique()->after('tc_kimlik');

            $table->string('cks_number', 30)->nullable()->after('tc_hash');
            $table->string('cks_document_path')->nullable()->after('cks_number');
            $table->string('cks_status', 20)->nullable()->index()->after('cks_document_path');
            $table->string('cks_rejection_reason')->nullable()->after('cks_status');
            $table->timestamp('cks_submitted_at')->nullable()->after('cks_rejection_reason');
            $table->timestamp('cks_reviewed_at')->nullable()->after('cks_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tc_hash']);
            $table->dropIndex(['cks_status']);
            $table->dropColumn([
                'tc_kimlik', 'tc_hash',
                'cks_number', 'cks_document_path', 'cks_status', 'cks_rejection_reason', 'cks_submitted_at', 'cks_reviewed_at',
            ]);
        });
    }
};
