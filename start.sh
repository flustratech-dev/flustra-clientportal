#!/bin/bash

# Titik masuk container produksi. Mengikuti pola flustra-erp/start.sh.
#
# SATU resource Coolify menjalankan TIGA proses: web, worker antrean, dan
# penjadwal. Alternatifnya membuat tiga resource terpisah, dan itu ditolak
# dengan sengaja — bukan karena proses latarnya berat (worker yang menganggur
# hanya puluhan MB), melainkan karena **setiap resource Coolify membangun
# ulang aplikasinya sendiri**: tiga resource berarti tiga kali
# `composer install` + `npm run build` setiap deploy, tiga salinan image, dan
# tiga lonjakan CPU berbarengan di VPS yang juga menampung tujuh aplikasi lain.
#
# Konsekuensi yang harus disadari: kalau proses web mati, seluruh container
# ikut restart — termasuk worker. Untuk portal ini itu justru diinginkan;
# semuanya bangun lagi bersama dan tidak ada worker yatim yang menulis ke
# antrean sementara aplikasinya sudah tidak ada.

set -u

mkdir -p storage/logs

# --- Worker antrean -------------------------------------------------------
#
# WAJIB ADA. QUEUE_CONNECTION=database bukan pilihan gaya: seluruh janji portal
# bersandar padanya. Tanpa worker, klaim mitra tersimpan di portal, tampil di
# Riwayat, tapi TIDAK PERNAH berangkat ke ERP.
#
# Dibungkus loop supaya bangun sendiri kalau mati. `--max-time=3600` membuatnya
# berhenti terjadwal tiap jam lalu dijalankan ulang oleh loop — cara paling
# murah menahan kebocoran memori proses yang hidup berhari-hari.
#
# `--tries=6` cocok dengan backoff SyncSubmissionToErp (1m·5m·15m·1j·6j).
while true; do
    php artisan queue:work --tries=6 --timeout=120 --sleep=3 --max-time=3600
    echo "[start.sh] queue:work berhenti, dijalankan ulang dalam 2 detik."
    sleep 2
done &

# --- Penjadwal ------------------------------------------------------------
#
# Menjalankan portal:sync-status tiap 15 menit (routes/console.php) — jaring
# pengaman untuk pengajuan yang webhook-nya hilang. Tanpa ini, satu webhook
# yang tidak sampai berarti pengajuannya mandek permanen.
while true; do
    php artisan schedule:work
    echo "[start.sh] schedule:work berhenti, dijalankan ulang dalam 2 detik."
    sleep 2
done &

# --- Web ------------------------------------------------------------------
#
# `exec` supaya proses ini menggantikan shell dan menjadi PID 1: sinyal stop
# dari Docker sampai langsung ke Laravel, bukan tertahan di shell induk.
echo "[start.sh] Menyalakan web, worker, dan penjadwal dalam satu container."
exec php artisan serve --host=0.0.0.0 --port=80
