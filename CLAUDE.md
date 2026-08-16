# CLAUDE.md — Flustra Client Portal

Panduan untuk siapa pun (manusia atau agen) yang mengerjakan project ini.
Baca sampai habis sebelum menulis baris pertama.

> **Status: seluruh fase selesai (16 Agustus 2026).** Fase 0 sampai 6 sudah
> dikerjakan dan diuji. Portal punya 20 kartu layanan yang berfungsi penuh —
> 9 pelanggan, 7 vendor, 4 umum — semuanya bersandar pada data nyata di ERP,
> ditambah verifikasi email, pemulihan kata sandi, pencarian Ctrl+K, notifikasi
> WhatsApp, dan halaman error sendiri.
>
> **56 uji otomatis lolos** (`php artisan test`): isolasi data antar-mitra,
> penjagaan webhook, ketahanan saat ERP mati, rekonsiliasi status, cache baca
> ERP, dan login Google.
>
> Yang tersisa hanya satu, dan itu pun bukan pekerjaan: **kredensial OAuth
> Google**. Kodenya sudah terpasang dan diuji; tanpa kredensial, fiturnya mati
> bersih. Tanda tangan digital bersertifikat sudah diputuskan **tidak dipakai**
> (§3 nomor 5), jadi ia tidak lagi menggantung di daftar mana pun.

**Dokumen induk:** [`../PRD_FLUSTRA_CLIENTPORTAL.md`](../PRD_FLUSTRA_CLIENTPORTAL.md)
— PRD lengkap 19 bagian. Kalau CLAUDE.md dan PRD bertentangan, **PRD yang menang**;
perbaiki CLAUDE.md-nya.

---

## 1. Apa ini, dalam satu paragraf

Portal untuk **pihak eksternal** perusahaan: pelanggan, vendor/supplier, dan
calon mitra/pelamar. Mereka masuk sendiri untuk mengonfirmasi pembayaran,
menyetujui penawaran, mengirim tagihan, melacak pengiriman, dan memperbarui data
perusahaannya — hal-hal yang selama ini diketik ulang oleh staf di dalam ERP
karena pihak luar tidak punya pintu masuk.

Sumber kebenaran seluruh data transaksi tetap **`flustra-erp`**. Portal ini
konsumen, bukan pemilik data.

---

## 2. Peta ekosistem — jangan tertukar

Ada beberapa project Flustra yang mudah dikelirukan. Pemisahannya begini:

| Project | Port | Isinya | Hubungan dengan portal |
| --- | --- | --- | --- |
| **flustra-clientportal** | 8008 | Portal pihak eksternal | ini |
| **flustra-erp** ("Flustra Office") | 8006 | Manajemen kantor internal — sumber data portal | **API server-ke-server** |
| **flustra-web** | 8002 | Project utama perusahaan, *financial tracking* | **tidak berhubungan** |
| **flustra-helpdesk** | 8003 | Tiket pelanggan produk SaaS | **tidak berhubungan** |
| **flustra-auth** ("Flustra ID") | 8090 | SSO OAuth2 untuk web publik | **sengaja TIDAK dipakai** |
| flustra-adminpanel | 8007 | Penyatu admin 5 web publik | tidak berhubungan |

Dua yang paling sering salah diasumsikan:

- **Portal ini bergantung pada ERP, bukan pada flustra-web.** flustra-web itu
  financial tracking perusahaan; tidak ada sangkut pautnya.
- **Helpdesk bukan bagian dari portal.** Jangan menautkan tiket portal ke
  flustra-helpdesk. Halaman Bantuan di portal berisi FAQ + kontak sendiri.

---

## 3. Keputusan yang sudah final — jangan diusulkan ulang

Enam hal ini sudah diputuskan pemilik produk. Kalau ada dorongan untuk
"memperbaikinya", jangan; alasannya ditulis di sini supaya tidak dibongkar ulang
tiap sesi baru.

1. **Tanpa SSO.** Portal punya `users`, register, login, dan reset password
   sendiri. **Tidak** tersambung ke `flustra-auth`. Alasannya: `flustra-auth`
   adalah identitas bersama lintas aplikasi — kalau portal ikut, setiap orang
   yang mendaftar di aplikasi Flustra mana pun muncul di daftar pengguna dan
   daftar tim jadi kotor. Pihak eksternal punya siklus hidup akun yang berbeda.

2. **Register langsung masuk.** Tidak ada halaman "menunggu persetujuan admin"
   seperti di ERP. Yang di-gate bukan akunnya, tapi **datanya** — lihat §4.

3. **Pelanggan DAN vendor, dua-duanya.** Bukan pelanggan dulu lalu vendor nanti.

4. **Lamaran kerja tetap di portal ini**, bukan dipindah ke flustra-web.

5. **Tanpa tanda tangan digital bersertifikat** (dibatalkan 16 Agustus 2026 —
   membalik keputusan sebelumnya). Persetujuan kontrak berhenti sebagai
   *acknowledgement* nama+waktu+IP, dan itulah bentuk finalnya; halamannya
   memang menyebut dirinya begitu, bukan mengaku tanda tangan. Tidak perlu
   memilih penyedia (Privy / VIDA / Digisign / Peruri) dan tidak perlu
   mengusulkannya lagi. Kolom `signature_*` di `contracts` milik ERP dibiarkan
   menganggur — bukan dihapus, supaya tidak ada migration turun-naik untuk
   sesuatu yang mungkin dihidupkan lagi suatu hari.

6. **Bahasa Indonesia saja.** Tidak perlu i18n, tidak perlu versi Inggris.

---

## 4. Model akses — bagian paling penting

Register terbuka dan instan, tapi tidak ada yang bisa melihat data mitra lain.
Keduanya dicapai dengan memisahkan "punya akun" dari "terbukti mitra siapa":

```
Register → LANGSUNG MASUK, account_type = 'umum'
             │
             ├── Layanan umum aktif seketika:
             │   ajukan kerja sama · minta penawaran (RFQ) · lamar kerja
             │
             └── Klaim mitra (bukti: no. invoice / no. PO / kode undangan / NPWP)
                      │  dikirim ke ERP lewat POST /api/portal/v1/claims
                      ▼
                  Staf verifikasi DI DALAM ERP (menu "Portal Mitra")
                      │
                      ├── Disetujui → webhook → account_type naik jadi
                      │                'pelanggan' / 'vendor', data ERP terbuka
                      └── Ditolak   → tetap 'umum', alasan ditampilkan,
                                      DAN BOLEH MENGAJUKAN KLAIM LAGI
```

Aturan yang tidak boleh dilanggar:

- **ID mitra tidak pernah diambil dari request.** Selalu dari
  `auth()->user()->activeLink->erp_partner_id`.
- ERP memvalidasi ulang setiap ID terhadap klaim terverifikasi
  (`PortalPartnerResolver`). Dua lapis, disengaja.
- Query `submissions` selalu dibatasi `user_id` lewat *global scope*.
- Akses yang ditolak balas **404**, bukan 403 — 403 memberitahu penebak bahwa
  nomornya benar.
- Satu akun boleh punya dua peran (pelanggan **dan** vendor) lewat beberapa
  baris `partner_links`.

---

## 5. Sisi ERP sudah siap

Fase 0 di PRD **sudah dikerjakan** di `../flustra-erp`. Jangan membangun ulang;
baca dulu apa yang sudah ada di sana.

**API** — `flustra-erp/routes/api.php`, prefix `/api/portal/v1`, middleware
`portal.token` + `throttle:120,1`. Autentikasi: header
`Authorization: Bearer <PORTAL_API_TOKEN>` (token statis, bandingkan dengan
`hash_equals`), plus IP allowlist opsional.

| Berkas di ERP | Isinya |
| --- | --- |
| `app/Http/Middleware/VerifyPortalToken.php` | Penjaga token. Token kosong ⇒ balas 503, bukan terbuka. Setiap panggilan masuk dicatat ke `portal_api_logs`, termasuk yang ditolak |
| `app/Services/Portal/PortalPartnerResolver.php` | Terjemah "portal bilang" → "ERP setuju". WAJIB dilewati setiap endpoint mitra |
| `app/Services/Portal/PortalNotifier.php` | Webhook ERP → portal, HMAC-SHA256 header `X-Erp-Signature`. Dipanggil dari `PortalPartnerController` (klaim & perubahan data), `PaymentConfirmationController`, `FinanceControlController`, dan `SalesReturnController` — **setiap layar staf yang bisa memutuskan sesuatu milik portal harus memanggilnya**, kalau tidak hasilnya bergantung pada layar mana yang kebetulan dipakai |
| `app/Http/Controllers/Api/Portal/` | ClaimController · CustomerPortalController · VendorPortalController · ChangeRequestController · PublicDataController |
| `app/Http/Controllers/PortalPartnerController.php` | Layar staf: verifikasi klaim, terapkan perubahan data, log aktivitas |
| `config/portal.php` | Seluruh setelan integrasi |

**Tabel baru di ERP:** `portal_partner_claims`, `portal_change_requests`,
`portal_api_logs`.

**Kolom baru di ERP** (semua sudah dimigrasi): `payment_confirmations.source`
+ `portal_submission_id` + `submitted_by_name` · `sales_returns.source` +
`portal_submission_id` + `requested_by_name` · `quotations.accepted_at` +
`accepted_via` + `accepted_by_name` + `accepted_ip` + `decision_note` ·
`purchase_bills.source` + `portal_submission_id` + `document_path` ·
`purchase_orders.vendor_confirmation_status` + `vendor_confirmed_at` +
`vendor_promised_date` + `vendor_notes` · `contracts.customer_ack_*` +
`signature_*` + `signed_at` · `candidates.portal_submission_id` ·
`customers.portal_invite_code` · `vendors.portal_invite_code` ·
`sales_deals.created_by` jadi nullable.

**Idempotensi:** setiap endpoint tulis menerima `portal_reference` dan akan
mengembalikan 200 dengan data lama bila referensi itu sudah pernah masuk. Jadi
aman mengirim ulang saat respons tidak sampai — jangan menambah pencegahan
duplikat sendiri di portal.

---

## 6. UI/UX — salin persis dari flustra-erp

Ketentuan pemilik produk: **samakan persis dengan `flustra-erp`.** Jangan
mendesain ulang, jangan "memodernkan", jangan meniru `flustra-adminpanel`
(project itu memakai token tema tweakcn yang berbeda — jangan tertukar).

### Berkas yang disalin apa adanya

```
../flustra-erp/resources/css/app.css      → .erp-card .erp-input .erp-label
                                             .btn-primary .btn-secondary .btn-danger
                                             .spinner .floating-card
                                             @custom-variant dark (&:where(.dark, .dark *))
                                             override .dark { --color-slate-900: #000000; … }
../flustra-erp/resources/css/layout.css
../flustra-erp/resources/css/auth.css      → animasi slide panel login/register
../flustra-erp/public/vendor/sweetalert2/  → dilokalkan, JANGAN dari CDN
```

**Nama kelas tidak diganti.** `.erp-card` tetap `.erp-card` walaupun ini bukan
ERP — supaya markup bisa disalin bolak-balik antar-project tanpa penyesuaian.

### Token desain

| Aspek | Nilai |
| --- | --- |
| Warna dasar | skala `slate`; di dark `slate-900`→`#000000`, `slate-800`→`#09090b`, `slate-700`→`#1c1c1e` |
| Aksen | `blue-600` (light) / `blue-500` (dark) |
| Status | emerald sukses · amber menunggu · red galat · blue info · slate netral |
| Font isi | Instrument Sans |
| Font auth & welcome | Plus Jakarta Sans |
| Sudut | `rounded-xl` kontrol, `rounded-2xl` kartu |
| Ukuran teks | `text-xs` kontrol, `text-[10px] uppercase tracking-wider` label |
| Latar aplikasi | `bg-slate-50 dark:bg-slate-900` |
| Latar auth/welcome | `#f0f4f9` / `#090d16` + tiga ambient glow beranimasi |

### Mode gelap — implementasi identik

```html
<html x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }">
```

- `$watch('darkMode')` menulis `localStorage` dan menambah/menghapus kelas
  `.dark` pada `documentElement`.
- Skrip anti-kedip di dalam `<head>` **sebelum** `@vite` — contoh persis ada di
  `flustra-erp/resources/views/layouts/app.blade.php` baris 221-227.
- Tombol toggle matahari/bulan `p-2 rounded-2xl`, ada di header aplikasi
  **dan** di halaman welcome/auth.

### Kerangka tata letak

| Elemen | Spesifikasi |
| --- | --- |
| Sidebar desktop | `fixed inset-y-0 left-0 w-52 hidden md:flex`, posisi gulir disimpan di `sessionStorage` |
| Bottom nav mobile | `fixed bottom-0 h-16 flex md:hidden` — Beranda · Layanan · Riwayat · Notifikasi · Profil |
| Panel mobile | sheet `rounded-t-3xl max-h-[85vh]` naik dari bawah |
| Body | `min-h-screen flex flex-col md:flex-row pb-16 md:pb-0` |
| Notifikasi | polling 30 detik, dijeda lewat Page Visibility API saat tab tidak aktif |
| Meta wajib | `<meta name="google" content="notranslate">` — auto-translate Chrome merusak atribut `x-data` |

**Tidak** dibawa dari ERP: pencarian global Ctrl+K (tunda ke Fase 5), modul chat,
banner maintenance internal.

### Jebakan Tailwind yang nyata

Jangan pernah merangkai kelas saat runtime:

```blade
{{-- SALAH — Tailwind memindai berkas sebagai teks, kelas ini tidak pernah ada --}}
<span class="bg-{{ $status_color }}-50 text-{{ $status_color }}-600">

{{-- BENAR — accessor mengembalikan kelas utuh --}}
<span class="{{ $status_color }}">
```

Contoh yang benar: `app/Models/Submission.php` dan
`flustra-erp/app/Models/PortalPartnerClaim.php` (`getStatusColorAttribute`).

Di `flustra-erp` ada beberapa view lama yang masih merangkai kelas saat runtime
(quotations, sales-orders, invoices, inventory, tasks, pipeline CRM). Di sana
masalahnya ditambal dengan blok `@source inline(...)` di `resources/css/app.css`.
Portal ini **tidak** memakai tambalan itu — pakai accessor sejak awal.

### Jebakan Blade yang nyata

**Jangan mencampur dua bentuk `@php` dalam satu berkas.** Bentuk satu-baris
`@php(...)` dan bentuk blok `@php … @endphp` tidak boleh berdampingan: Blade
akan berhenti mengompilasi diam-diam, seluruh directive di antara keduanya
tertinggal mentah, dan halaman meledak saat dirender dengan pesan menyesatkan
seperti `syntax error, unexpected token "endforeach"`.

Yang membuatnya berbahaya: **tidak ada peringatan apa pun saat build.**
`php artisan view:cache` tetap melapor sukses, dan halamannya baru rusak saat
benar-benar dibuka.

Cara aman:

1. Siapkan variabel di **controller**, bukan di blade — ini yang dipakai
   `DashboardController`.
2. Kalau memang perlu di blade, konsisten pakai **satu bentuk saja** per berkas.

Pemeriksaan cepat sebelum menyerahkan pekerjaan:

```bash
for f in $(find resources/views -name "*.blade.php"); do
  i=$(grep -c '@php(' "$f"); b=$(grep -c '^\s*@php\s*$' "$f")
  if [ "$i" -gt 0 ] && [ "$b" -gt 0 ]; then echo "CAMPUR: $f"; fi
done
```

Pemeriksaan yang lebih menyeluruh — memastikan tidak ada directive yang
tertinggal mentah di seluruh view:

```bash
php artisan tinker --execute='
use Illuminate\Support\Facades\Blade;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("resources/views")) as $f) {
    if (!str_ends_with($f->getFilename(), ".blade.php")) continue;
    $out = Blade::compileString(file_get_contents($f->getPathname()));
    if (preg_match_all("/@(foreach|endforeach|if|endif|php|endphp|section)\b/", $out) > 0) {
        echo "RAW: ".$f->getPathname()."\n";
    }
}'
```

---

## 7. Stack & konvensi

Sama persis dengan `flustra-erp` supaya tim tidak belajar dua pola.

| Bagian | Pilihan |
| --- | --- |
| Backend | Laravel 12, PHP 8.2 |
| Frontend | Blade + Alpine.js 3 + Tailwind CSS 4 (`@tailwindcss/vite`) |
| Bundler | Vite 7, `laravel-vite-plugin` 2 |
| Basis data | MySQL 8, `db_flustra-clientportal` |
| Sesi & antrean | driver `database` |
| Unggahan | disk **`private`** (`storage/app/private`) — **bukan** `public`, akses lewat signed URL 5 menit |
| Dialog | SweetAlert2 dilokalkan di `public/vendor/` |
| Domain produksi | `portal.flustra.id` |
| Deploy | Coolify, ikuti `../PANDUAN_DEPLOYMENT_PRODUCTION_FLUSTRA.md` |

### Struktur folder yang dituju

```
app/Http/Controllers/
  Public/      WelcomeController, AuthController
  Portal/      DashboardController, SubmissionController, HistoryController,
               ProfileController, NotificationController, PartnerClaimController
  Api/         WebhookController (penerima webhook dari ERP)
app/Services/Erp/   ErpClient, ErpCustomerApi, ErpVendorApi, ErpRecruitmentApi
app/Jobs/           SyncSubmissionToErp, RetryFailedSubmission
resources/views/
  welcome.blade.php
  auth/login.blade.php          ← satu berkas, login + register (pola ERP)
  layouts/app.blade.php
  portal/{dashboard,history,profile,notifications}
  layanan/{…satu folder per layanan}
```

### Tabel portal

`users` · `partner_links` · `submissions` · `submission_attachments` ·
`submission_status_histories` · `notifications` · `activity_logs` ·
`api_sync_logs` — skema lengkap ada di PRD §8.

`submissions` menyimpan salinan setiap pengajuan **sebelum** dikirim ke ERP.
Ini yang membuat halaman Riwayat tetap hidup saat ERP mati, dan yang membuat
pengajuan bisa diantre ulang alih-alih hilang.

### `.env` penting

```
APP_NAME="Flustra Client Portal"
APP_URL=http://localhost:8008
DB_DATABASE=db_flustra-clientportal

ERP_API_URL=http://localhost:8006/api/portal/v1
ERP_API_TOKEN=            # harus sama dengan PORTAL_API_TOKEN di flustra-erp/.env
ERP_WEBHOOK_SECRET=       # harus sama dengan PORTAL_WEBHOOK_SECRET di flustra-erp/.env
ERP_TIMEOUT=15
ERP_WEBHOOK_MAX_AGE=300   # umur maksimum webhook masuk (detik)
ERP_EVIDENCE_URL_DAYS=7   # umur tautan bukti klaim yang dikirim ke ERP (hari)
```

Di sisi `flustra-erp/.env` harus ada pasangannya: `PORTAL_API_TOKEN`,
`PORTAL_WEBHOOK_SECRET`, dan `PORTAL_WEBHOOK_URL=http://localhost:8008/api/webhooks/erp`.
Rahasia kosong berarti **tutup**, bukan terbuka: API portal di ERP balas 503,
dan penerima webhook di portal menolak semua kiriman.

### Menjalankan di lokal

```bash
npm run all
```

Menyalakan empat proses sekaligus lewat `composer dev`: server (**8008**),
`queue:listen`, `schedule:work`, dan Vite. Antrean dan penjadwal bukan
pelengkap — pengiriman ke ERP lewat antrean dan rekonsiliasi lewat penjadwal,
jadi tanpa keduanya klaim mitra tidak akan pernah sampai ke ERP.

`pail` sengaja **tidak** ikut (beda dari bawaan Laravel 12): ia butuh ekstensi
`pcntl` yang tidak ada di Windows, dan karena `--kill-others` aktif, satu proses
yang mati akan menjatuhkan semuanya. Baca log lewat `storage/logs/laravel.log`.

---

## 8. Aturan kerja

1. **Bahasa antarmuka Indonesia**, sapaan formal ("Anda"). Tanpa istilah
   internal: tulis "Penawaran" bukan "Quotation ERP", "Tagihan" bukan "AR Invoice".
2. **Setiap tampilan baru dicek di light mode, dark mode, dan lebar 375px.**
3. **Panggilan ke ERP selalu lewat queued job** dengan percobaan ulang mundur
   bertahap (1m, 5m, 15m, 1j, 6j). Gagal semua ⇒ `sync_state = 'failed'`,
   pengguna diberi pesan jujur, admin diberi tahu.
4. **Kegagalan ERP tidak boleh menggagalkan aksi pengguna.** Simpan dulu, kirim
   belakangan.
5. **Data internal tidak pernah bocor ke portal:** harga pokok, margin, catatan
   internal, nama staf penilai, invoice berstatus `draft`/`pending_approval`,
   PO berstatus `draft`, quotation `draft`, lowongan `is_internal_only`.
6. **Perubahan rekening bank vendor selalu butuh persetujuan staf.** Tanpa
   pengecualian — ini jalur penipuan pembayaran paling umum.
7. Uji wajib sebelum rilis: pengguna A membuka pengajuan pengguna B ⇒ 404.

---

## 9. Urutan pengerjaan

Fase lengkap ada di PRD §15. Ringkasnya:

| Fase | Isi | Status |
| --- | --- | --- |
| 0 | Lapisan API + skema di `flustra-erp` | **selesai** |
| 1 | Fondasi: welcome, login/register instan, layout + tema, profil, notifikasi, riwayat, form klaim | **selesai** |
| 2 | Kirim klaim ke ERP (`ErpClient`) + penerima webhook + naik-kelas otomatis | **selesai** |
| 3 | Modul pelanggan (9 layanan) | **selesai** |
| 4 | Modul vendor (7 layanan) | **selesai** |
| 5 | Umum: lamaran kerja, RFQ, WhatsApp, Ctrl+K, verifikasi email, reset sandi | **selesai** |
| 6 | Pengerasan, uji isolasi data, halaman error, uji otomatis | **selesai** |

### Yang SUDAH ada setelah Fase 1

- **Auth**: `Public\AuthController` — register langsung masuk (tanpa antrean),
  login dengan throttle, logout. Kata sandi dicek terhadap daftar bocor.
- **Beranda**: `Portal\DashboardController` + `App\Services\ServiceCatalog`
  (16 kartu; terbuka/terkunci/segera-hadir mengikuti `account_type`).
- **Riwayat & Status**: `Portal\HistoryController`, lengkap dengan timeline
  dari `submission_status_histories`.
- **Profil adaptif**: `Portal\ProfileController` — tab Akun / Data Mitra /
  Dokumen, ganti sandi, keluarkan perangkat lain, pemilih peran.
- **Klaim mitra**: `Portal\PartnerClaimController` — tersimpan lokal dengan
  `sync_state = 'pending'`, **belum** dikirim ke ERP.
- **Notifikasi**: lonceng + polling 30 detik, dijeda saat tab tidak aktif.
- **Isolasi data**: global scope `milik_sendiri` di `Submission`; akses lintas
  pengguna menghasilkan 404, sudah diuji.

### Yang SUDAH ada setelah Fase 2

Sambungan dua arah portal ↔ ERP. `ERP_API_TOKEN` dan `ERP_WEBHOOK_SECRET`
sudah terisi dan cocok dengan `PORTAL_API_TOKEN` / `PORTAL_WEBHOOK_SECRET` di
`flustra-erp/.env`.

| Berkas | Isinya |
| --- | --- |
| `app/Services/Erp/ErpClient.php` | Satu-satunya pintu keluar ke ERP. Bearer token, timeout dari config, `X-Portal-Request-Id`, dan setiap panggilan tercatat di `api_sync_logs` |
| `app/Services/Erp/ErpException.php` | Membedakan gagal yang boleh diulang (5xx, koneksi putus) dari yang tidak (4xx) |
| `app/Services/Erp/ErpEventApplier.php` | Menerapkan keputusan ERP ke portal. **Dipakai bersama** oleh webhook dan `portal:sync-status` — sengaja satu tempat supaya kedua jalur tidak pelan-pelan berbeda |
| `app/Jobs/SyncSubmissionToErp.php` | Antrean pengiriman. Backoff 1m · 5m · 15m · 1j · 6j, `ShouldBeUnique` per submission |
| `app/Http/Controllers/Api/WebhookController.php` | Penerima webhook ERP. HMAC atas raw body, tolak kiriman >5 menit, tolak `X-Erp-Request-Id` berulang |
| `app/Console/Commands/SyncSubmissionStatus.php` | `portal:sync-status`, dijadwalkan tiap 15 menit di `routes/console.php` |
| `app/Http/Controllers/Portal/EvidenceController.php` | Bukti klaim untuk staf ERP lewat signed URL — berkasnya tetap di disk privat |

Aturan yang lahir dari Fase 2 dan tidak boleh dibongkar:

- **Pengguna tidak pernah ikut gagal saat ERP mati.** Sudah diuji: ERP
  dimatikan, klaim tetap tersimpan dan tampil di Riwayat, job mengulang, dan
  begitu ERP hidup lagi statusnya menyusul sendiri.
- **Peran aktif tidak digeser diam-diam.** Klaim kedua yang disetujui (mis.
  vendor pada akun yang sudah pelanggan) menambah `partner_links`, tapi
  `account_type` dan `active_link_id` tetap seperti semula.
- **Akses yang dicabut tidak dibuka lagi oleh polling.** Hanya webhook
  `claim.verified` — yang berarti ada staf menekan tombol — yang boleh
  memulihkan link ber-status `revoked`.

### Yang SUDAH ada setelah Fase 3

Sembilan layanan pelanggan, semuanya di balik middleware `pelanggan`.

| Berkas | Isinya |
| --- | --- |
| `app/Services/Erp/ErpCustomerApi.php` | Seluruh endpoint `/customers/{id}/*`. **Menerima `User`, bukan `customerId`** — id-nya digali sendiri dari `activeLink`, jadi controller tidak punya jalan untuk mengoper id dari request sekalipun mau |
| `app/Http/Middleware/EnsurePelanggan.php` | Alias `pelanggan`. Memeriksa `partner_links` terverifikasi, bukan sekadar `account_type` — cerminan bisa tertinggal, ikatannya tidak |
| `app/Http/Controllers/Layanan/LayananController.php` | Induk semua controller layanan. `tarik()` untuk daftar (gagal ⇒ halaman tetap terbuka dengan pesan jujur), `tarikSatu()` untuk detail (404 dari ERP diteruskan apa adanya) |
| `app/Http/Controllers/Layanan/*.php` | Tagihan · KonfirmasiBayar · Penawaran · Pesanan · Pengiriman · Retur · Kontrak · Kredit · DataPerusahaan |
| `resources/views/layanan/` | Satu folder per layanan, plus `partials/status-badge` dan `partials/pagination` |

Dua strategi ketahanan yang berbeda, disengaja:

- **Sisi baca** menembak ERP langsung setiap kali halaman dibuka — tidak ada
  salinan di portal, karena saldo tagihan yang basi lebih berbahaya daripada
  tidak ada saldo sama sekali. Kalau ERP mati, halamannya tetap terbuka dengan
  daftar kosong dan satu kalimat jujur.
- **Sisi tulis** menyimpan `Submission` dulu lalu mengantre. Sudah diuji: ERP
  dimatikan, pelanggan tetap bisa mengirim bukti transfer, dan kirimannya
  menyusul sendiri setelah ERP hidup.

### Yang SUDAH ada setelah Fase 4

Enam layanan vendor, di balik middleware `mitra:vendor`.

| Berkas | Isinya |
| --- | --- |
| `app/Services/Erp/ErpVendorApi.php` | Kembaran `ErpCustomerApi` untuk `/vendors/{id}/*`. Juga memegang `FIELD_LABELS` dan `FIELD_REKENING` |
| `app/Http/Middleware/EnsureMitra.php` | Satu penjaga untuk dua tipe: `mitra:customer` dan `mitra:vendor`. Menggantikan `EnsurePelanggan` |
| `app/Http/Controllers/Layanan/LayananPelangganController.php` · `LayananVendorController.php` | Dua induk tipis; aturan ketahanan tetap di `LayananController` |
| `app/Http/Controllers/Layanan/Vendor/*.php` | PurchaseOrder · TagihanVendor · PembayaranVendor · SuratJalan · ReturVendor · DataVendor |
| `resources/views/layanan/vendor/` | Satu folder per layanan |

Aturan yang lahir dari Fase 4:

- **Rekening vendor tidak pernah berubah tanpa manusia.** Bagian rekening di
  form terpisah dan tertutup secara bawaan, alasan wajib diisi, throttle-nya
  `5,60` (bukan `10,60` seperti sisi pelanggan), dan ERP menandainya
  `touches_bank_account`. Nomor rekening yang tersimpan **tidak ditampilkan**
  di halaman itu — kalau akun dibajak, tidak ada yang bisa dibaca.
- **Hanya PO yang sudah disanggupi yang bisa ditagihkan.** Menagih PO yang
  belum dikonfirmasi hampir selalu salah pilih dan berakhir jadi pekerjaan
  pembatalan di finance.
- **Selisih tagihan diperingatkan dua kali**: di form (Alpine, sebelum kirim)
  dan di ERP (`has_discrepancy`, yang menentukan). Yang pertama hanya membantu
  vendor memperbaiki sendiri; yang kedua yang dipercaya.

### Perubahan di sisi ERP selama Fase 3–4

Semuanya aditif — melengkapi kontrak Fase 0, bukan membangun ulang:

| Perubahan | Sebabnya |
| --- | --- |
| `product_id` + `unit` pada rincian invoice | Form retur mustahil dibuat tanpanya |
| `GET /customers/{id}/invoices/{id}/pdf` + `resources/views/invoices/pdf.blade.php` | Ada di PRD §10.1 tapi rutenya belum ada — dan ternyata view-nya pun tidak pernah dibuat, jadi tombol unduh PDF **staf** juga selalu meledak |
| `sales_returns.evidence_file_url` | Foto barang retur tidak punya tempat di kontraknya |
| `VerifyPortalToken` mencatat ke `portal_api_logs` | Layar "Aktivitas Portal" hanya menampilkan webhook keluar; lalu lintas masuk tidak terlihat sama sekali |
| `PortalNotifier` dipanggil dari `PaymentConfirmationController`, `FinanceControlController`, `SalesReturnController` | Tanpa itu pelanggan tidak pernah tahu hasil verifikasi pembayarannya |
| Tabel `portal_shipping_documents` + endpoint + layar staf | Kartu "Surat Jalan" tidak punya muara sama sekali di ERP |

### Yang SUDAH ada setelah Fase 5 & 6

**Fase 5 — layanan umum**, terbuka untuk semua akun termasuk `umum`:

| Berkas | Isinya |
| --- | --- |
| `app/Services/Erp/ErpPublicApi.php` | `/vacancies`, `/applications`, `/leads`, `/inquiries`. Tidak menyentuh `activeLink` — tidak ada data mitra di sini |
| `app/Http/Controllers/Layanan/Umum/` | Lowongan (+lamar) · RFQ · Pertanyaan |
| `app/Http/Controllers/Public/AkunController.php` | Verifikasi email dan pemulihan kata sandi, keduanya berbahasa Indonesia |
| `app/Http/Controllers/Portal/SearchController.php` | Pencarian Ctrl+K. Sengaja tidak menembak ERP — pencarian harus seketika |
| `app/Services/WhatsAppGateway.php` + `NotifikasiMitra.php` | Klien flustra-wa (disalin apa adanya dari repo aslinya) + pemilih kapan WhatsApp layak dikirim |

**Fase 6 — pengerasan:**

- `tests/Feature/` — **26 uji**: `IsolasiDataTest` (7), `WebhookErpTest` (13),
  `KetahananSinkronisasiTest` (6). Jalankan dengan `php artisan test`.
- `resources/views/errors/` — 403, 404, 419, 429, 500, 503. Berdiri sendiri
  tanpa `@vite` dan tanpa Alpine: halaman 500 muncul justru ketika ada yang
  rusak, jadi ia tidak boleh bergantung pada aset hasil build.
- `phpunit.xml` memakai SQLite in-memory dan token ERP palsu, supaya tidak ada
  uji yang tanpa sengaja menembak ERP sungguhan.

Aturan yang lahir dari Fase 5–6:

- **Verifikasi email tidak memblokir login.** Satu-satunya yang diminta
  verifikasi adalah pengajuan klaim mitra — di situlah identitas mulai berarti.
- **Lupa sandi tidak pernah membocorkan apakah email itu terdaftar.**
  Jawabannya sama persis, terdaftar atau tidak.
- **WhatsApp hanya untuk kabar yang perlu segera dibaca** (klaim disetujui /
  ditolak). Mengirimnya untuk setiap perubahan status akan membuat mitra
  mematikan notifikasinya, dan setelah itu tidak ada yang sampai.

### Admin portal & pengumuman (16 Agustus 2026)

**Akun admin** mengikuti pola seeder `flustra-erp` persis — variabel `.env`-nya
pun bernama sama (`SUPER_ADMIN_NAME` / `EMAIL` / `PASSWORD` / `PHONE`) supaya
bloknya bisa disalin bolak-balik antar-project, seperti nama kelas CSS di §6.

```bash
php artisan db:seed
```

Seeder **tidak menimpa akun yang sudah ada** — menjalankannya ulang saat deploy
tidak boleh mengembalikan sandi yang sudah diganti admin ke nilai `.env`. Untuk
memaksa memperbarui (atau membuat dengan sandi acak), pakai:

```bash
php artisan portal:admin
```

Kolomnya `users.role` (`mitra` | `admin`), **bukan** nilai baru di
`account_type` — admin bukan jenis mitra, ia tidak punya `partner_links` dan
tidak boleh ikut terhitung di daftar pelanggan mana pun. Yang bukan admin
membuka `/admin` mendapat **404**, bukan 403.

Admin portal **tidak bisa menyetujui apa pun**. Keputusan atas data mitra ada
di ERP. Yang bisa dia lakukan: melihat kondisi portal (pengajuan gagal
sinkron, lalu lintas API, antrean job), mengantre ulang kiriman yang gagal,
memasang pengumuman, dan melihat portal sebagai mitra mana pun.

#### "Lihat Sebagai" — akses penuh tanpa kartu terkunci

Admin tidak punya `partner_links` sendiri, jadi tanpa ini seluruh kartu mitra
terkunci untuknya. Halaman `/admin/lihat-sebagai` memberi konteks: pilih satu
mitra terverifikasi, lalu seluruh layanan pelanggan/vendor terbuka dan berisi
data mitra itu — persis yang dilihat mitranya sendiri saat menelepon mengeluh.

Empat batas yang menyertainya, dan semuanya sengaja tidak bisa dimatikan dari
layar admin:

| Batas | Ditegakkan di |
| --- | --- |
| **Hanya baca** — seluruh aksi kirim ditolak selama konteks aktif | `TolakTulisSaatLihatSebagai`, dipasang global di grup `web` |
| **Tercatat** — setiap perpindahan masuk `activity_logs` | `LihatSebagaiController` |
| **Terlihat** — bilah menyala di setiap halaman | `partials/lihat-sebagai-bar.blade.php` |
| **Ikut dicabut** — link ber-status `revoked` langsung hilang dari konteks | `KonteksMitra::pilihanAdmin()` |

**Pertukaran keamanan yang harus dipahami sebelum menyentuh `KonteksMitra`:**
agar ERP mau melayani permintaannya, portal mengirim `portal_user_id` **milik
pemilik link**, bukan milik admin. Artinya lapisan kedua di ERP
(`PortalPartnerResolver`) ditembus khusus untuk admin. Itu disadari — admin
portal adalah staf tepercaya, dan tanpa ini "akses penuh" hanya berarti halaman
terbuka yang isinya kosong. Yang menjaga agar tidak disalahgunakan adalah
keempat batas di atas, bukan lapisan ERP-nya.

Diuji di `tests/Feature/AdminAksesPenuhTest.php` (10 uji) — separuhnya justru
memastikan pagar mitra biasa **tidak** ikut longgar.

**Pengumuman punya dua sumber** dan keduanya disimpan terpisah:

| Sumber | Dinyalakan dari | Untuk |
| --- | --- | --- |
| `erp_maintenance_*` | flustra-erp, terdorong lewat webhook `maintenance.changed` | Pemeliharaan ERP — separuh layanan portal ikut berhenti, mitra perlu tahu |
| `maintenance_*` | Halaman `/admin/pengumuman` di portal | Hal yang tidak ada hubungannya dengan ERP: migrasi portal, pengumuman libur |

Terpisah supaya tidak saling menimpa: ERP mematikan bannernya sendiri tidak
boleh ikut menghapus pengumuman admin portal. Bila dua-duanya menyala, yang
tampil yang dari portal — pesan itu lebih dekat dengan penggunanya. Bannernya
muncul di layout aplikasi, layout publik, **dan** halaman masuk (layoutnya
berdiri sendiri, jadi harus disisipkan bertiga).

### Berkas env

Empat berkas, mengikuti pola `flustra-erp`: `.env` (lokal, port 8008),
`.env.development`, `.env.staging`, `.env.production`. SMTP (Brevo) dan
WA gateway disalin dari ERP.

Satu perbedaan penting dari ERP: **`QUEUE_CONNECTION=database`, bukan `sync`.**
Seluruh janji portal bergantung pada itu — dengan `sync`, panggilan ke ERP
terjadi di dalam request pengguna, dan ERP yang mati membuat pengiriman
pelanggan ikut gagal. Konsekuensinya di Coolify wajib ada dua proses tambahan:

```bash
php artisan queue:work --tries=6 --timeout=120
```

```bash
php artisan schedule:work
```

### Yang BELUM ada

Satu hal, dan itu pun hanya menunggu kredensial:

1. **Kredensial OAuth Google.** Kodenya **sudah terpasang** —
   `Public\GoogleAuthController`, rute `/masuk/google`, tombol di halaman masuk
   dan daftar, dan 8 uji di `tests/Feature/LoginGoogleTest.php`. Yang belum ada
   hanya `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` di `.env`.

   Selama keduanya kosong, **fiturnya mati bersih, bukan rusak**: rutenya
   membalas 404 dan tombolnya tidak dirender sama sekali. Itu disengaja —
   tombol yang sudah pasti gagal lebih buruk daripada tidak ada tombol.

   Redirect URI yang harus didaftarkan di Google Cloud Console sudah tertulis
   sebagai `GOOGLE_REDIRECT_URI` di masing-masing berkas `.env`, dan harus
   didaftarkan **persis** seperti itu (skema, host, tanpa trailing slash).

Tanda tangan digital bersertifikat **tidak lagi ada di daftar ini** — sudah
diputuskan tidak dipakai; lihat §3 nomor 5.

### Rekonsiliasi status — sudah lengkap (16 Agustus 2026)

ERP kini punya `GET /submissions/{ref}/status` yang umum, jadi
`portal:sync-status` merekonsiliasi **semua** jenis pengajuan, bukan hanya
`partner_claim`. Sebelumnya satu webhook yang tidak sampai berarti pengajuannya
mandek permanen tanpa jaring pengaman.

Dua hal yang tidak boleh dibongkar:

- **Terjemahan status ada di satu kelas**, `flustra-erp/app/Services/Portal/PortalSubmissionStatus.php`.
  Dipakai bersama oleh jalur webhook dan jalur polling — persis alasan yang
  sama dengan `ErpEventApplier` di sisi portal. Dua salinan akan pelan-pelan
  berbeda, dan hasil akhirnya jadi bergantung pada jalur mana yang kebetulan
  menang.
- **404 dari endpoint itu berarti "kirim ulang", bukan "diamkan".** Pengajuan
  bisa ditandai `synced` di portal padahal responsnya hilang di tengah jalan;
  ERP idempoten terhadap `portal_reference`, jadi kiriman kedua aman.

Klaim mitra tetap lewat jalurnya sendiri (`GET /claims/{reference}`): yang
berubah di sana bukan hanya status pengajuan, tapi juga `partner_links` dan
tipe akun penggunanya.

---

## 10. Kalau butuh contoh kode

| Butuh contoh | Lihat di `../flustra-erp` |
| --- | --- |
| Layout, dark mode, notifikasi, bottom nav | `resources/views/layouts/app.blade.php` |
| Halaman auth: animasi, ambient glow, toggle tema | `resources/views/auth/login.blade.php` |
| Pola form | `resources/views/payment_confirmations/create.blade.php` |
| Pola daftar + filter | `resources/views/portal/claims/index.blade.php` |
| Lencana status yang benar | `app/Models/PortalPartnerClaim.php` |
| Halaman detail + aksi | `resources/views/portal/claims/show.blade.php` |
| Klien HTTP + HMAC | `app/Services/Portal/PortalNotifier.php` |
| Pola isolasi data | `app/Services/Portal/PortalPartnerResolver.php` |
