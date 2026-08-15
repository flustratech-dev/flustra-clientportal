# Flustra Client Portal

Portal untuk **pihak eksternal** Flustra — pelanggan, vendor/supplier, dan calon
mitra/pelamar — agar mereka bisa mengurus sendiri hal-hal yang selama ini
diketik ulang oleh staf di dalam `flustra-erp`.

Sumber kebenaran seluruh data transaksi tetap `flustra-erp`. Portal ini
konsumen, bukan pemilik data.

- **Dokumen induk:** [`../PRD_FLUSTRA_CLIENTPORTAL.md`](../PRD_FLUSTRA_CLIENTPORTAL.md)
- **Panduan kerja:** [`CLAUDE.md`](CLAUDE.md) — baca ini sebelum menulis kode.

## Status

| Fase | Isi | Status |
| --- | --- | --- |
| 0 | Lapisan API + skema di `flustra-erp` | selesai |
| 1 | Fondasi portal | **selesai** |
| 2 | Kirim klaim ke ERP + penerima webhook | berikutnya |
| 3–6 | Modul pelanggan, vendor, umum, pengerasan | belum |

## Stack

Laravel 12 · PHP 8.2 · Blade + Alpine.js 3 · Tailwind CSS 4 · Vite 7 · MySQL 8

Tampilan dan temanya **menyalin `flustra-erp`** apa adanya — kelas `.erp-card`,
`.erp-input`, `.btn-primary`, mode gelap lewat `localStorage.darkMode`, sidebar
`w-52` di desktop, bottom nav `h-16` di mobile.

## Setup

```bash
cp .env.example .env
php artisan key:generate
```

Buat database `db_flustra-clientportal`, lalu:

```bash
php artisan migrate
php artisan storage:link
npm install
npm run build
```

Isi dua nilai ini di `.env` dengan angka yang **sama persis** seperti di
`flustra-erp/.env` (`PORTAL_API_TOKEN` dan `PORTAL_WEBHOOK_SECRET`):

```
ERP_API_TOKEN=
ERP_WEBHOOK_SECRET=
```

Buat nilainya sekali dengan:

```bash
php artisan tinker --execute="echo bin2hex(random_bytes(32));"
```

## Menjalankan

Satu perintah untuk semuanya — server, antrean, penjadwal, log, dan Vite:

```bash
npm run all
```

Antrean dan penjadwal ikut dijalankan karena bukan pelengkap: pengiriman ke ERP
lewat antrean, dan rekonsiliasi `portal:sync-status` lewat penjadwal. Tanpa
keduanya, klaim mitra tidak akan pernah sampai ke ERP.

Kalau hanya perlu servernya:

```bash
npm run serve
```

Port 8008 dipakai konsisten di seluruh ekosistem Flustra (8006 = ERP, 8003 =
helpdesk, 8090 = auth).
