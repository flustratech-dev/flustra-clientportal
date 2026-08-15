<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel inti portal.
 *
 * Catatan penting soal `submissions`: portal menyimpan salinan setiap pengajuan
 * SEBELUM dikirim ke ERP. Ini yang membuat halaman Riwayat tetap hidup saat ERP
 * mati, dan yang membuat pengajuan bisa diantre ulang alih-alih hilang. ERP
 * tetap sumber kebenaran — `status` di sini selalu mengikuti status di sana
 * begitu tersinkron.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Jembatan akun portal ↔ mitra di ERP.
         *
         * Tidak ada foreign key ke ERP: itu basis data terpisah. erp_partner_id
         * diisi ERP saat klaim disetujui, dan selama masih null tidak ada data
         * mitra apa pun yang bisa dibuka.
         */
        Schema::create('partner_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('partner_type', 20); // customer | vendor

            $table->string('company_name');
            $table->string('npwp', 50)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('billing_email')->nullable();
            $table->text('address')->nullable();

            $table->string('evidence_type', 30); // invoice_number|po_number|invite_code|npwp
            $table->string('evidence_value')->nullable();
            $table->string('evidence_file_path')->nullable();

            $table->string('status', 20)->default('pending'); // pending|verified|rejected|revoked
            $table->unsignedBigInteger('erp_partner_id')->nullable();
            $table->string('erp_partner_code')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by_name')->nullable();
            $table->text('rejected_reason')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['partner_type', 'erp_partner_id']);
        });

        /*
         * Setiap pengajuan yang dibuat pengguna. Sumber halaman Riwayat & Status.
         */
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_link_id')->nullable()->constrained('partner_links')->nullOnDelete();

            // payment_confirmation, quotation_decision, sales_return, vendor_bill,
            // po_confirmation, profile_change, rfq, job_application,
            // partner_claim, contract_ack, shipping_doc, inquiry
            $table->string('type', 40);

            $table->string('reference_number')->unique(); // PRT-YYYYMM-0001
            $table->string('title');
            $table->string('summary')->nullable();

            $table->json('payload')->nullable();
            $table->decimal('amount', 15, 2)->nullable();

            // Jejak ke sisi ERP, diisi setelah tersinkron.
            $table->string('erp_module', 50)->nullable();
            $table->unsignedBigInteger('erp_record_id')->nullable();
            $table->string('erp_reference')->nullable();

            $table->string('status', 20)->default('draft');
            $table->text('status_reason')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('last_status_at')->nullable();

            $table->string('sync_state', 20)->default('pending'); // pending|synced|failed
            $table->unsignedTinyInteger('sync_attempts')->default(0);
            $table->text('sync_error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('sync_state');
        });

        Schema::create('submission_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();

            // Bawaannya disk privat: berkas mitra tidak pernah boleh bisa
            // diambil lewat URL langsung, hanya lewat signed route.
            $table->string('disk', 20)->default('private');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->timestamps();
        });

        /*
         * Sumber timeline di halaman detail pengajuan. Satu baris per perubahan
         * status, supaya pengguna bisa melihat sendiri prosesnya sampai mana
         * tanpa perlu bertanya lewat WhatsApp.
         */
        Schema::create('submission_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();

            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();

            $table->string('actor_type', 20)->default('portal'); // portal | erp | system
            $table->string('actor_name')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['submission_id', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('body')->nullable();
            $table->string('type', 20)->default('info'); // info|success|warning|error
            $table->string('url')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action', 60);
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
        });

        /*
         * Jejak lalu lintas portal ↔ ERP. Tanpa ini, menelusuri "pengajuan saya
         * tidak muncul di ERP" berarti menebak-nebak.
         */
        Schema::create('api_sync_logs', function (Blueprint $table) {
            $table->id();

            $table->string('direction', 10); // outbound | inbound
            $table->string('endpoint');
            $table->string('method', 10);
            $table->unsignedSmallInteger('status_code')->nullable();

            $table->foreignId('submission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_id')->nullable();

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['direction', 'created_at']);
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_sync_logs');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('submission_status_histories');
        Schema::dropIfExists('submission_attachments');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('partner_links');
    }
};
