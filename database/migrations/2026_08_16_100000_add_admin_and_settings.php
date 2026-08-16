<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun admin portal dan setelan portal.
 *
 * **Kenapa kolom `role` terpisah, bukan menambah 'admin' ke `account_type`.**
 * `account_type` menjawab "mitra jenis apa orang ini" — umum, pelanggan, atau
 * vendor. Admin bukan jenis mitra; ia tidak punya `partner_links`, tidak punya
 * data di ERP, dan tidak boleh muncul di daftar mitra mana pun. Menumpuknya ke
 * enum yang sama akan membuat setiap query "semua pelanggan" harus ingat
 * mengecualikan admin, dan cepat atau lambat ada yang lupa.
 *
 * Admin portal juga BUKAN staf ERP. Staf memutuskan nasib data mitra di ERP;
 * admin portal hanya memantau kesehatan portalnya sendiri — pengajuan yang
 * gagal terkirim, lalu lintas API, dan banner pemberitahuan. Ia sengaja tidak
 * bisa menyetujui apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('mitra')->after('account_type'); // mitra | admin
            $table->index('role');
        });

        /*
         * Setelan portal. Key/value, meniru `system_settings` di ERP supaya
         * tim tidak perlu belajar dua pola.
         */
        Schema::create('portal_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_settings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
