{{--
    Satu-satunya tempat mode gelap dipasang ke halaman.

    Disertakan oleh SEMUA layout yang punya <html> sendiri — app, public,
    welcome, auth/login, dan errors/layout. Sebelumnya tiap layout menyimpan
    salinan skripnya sendiri, dan salinan yang berserak selalu berakhir sama:
    pelan-pelan berbeda, lalu hasilnya bergantung pada halaman mana yang
    kebetulan dibuka.

    Tiga hal yang dikerjakan di sini, dan yang ketiga adalah alasan berkas ini
    lahir:

    1. Pasang kelas .dark sebelum apa pun tergambar — cegah kedip putih saat
       halaman dimuat ulang dalam mode gelap. Karena itu ia harus berada di
       dalam <head>, sebelum @vite.

    2. Ikuti perubahan dari TAB LAIN (event `storage`). Tanpa ini, membuka
       portal di dua tab lalu mengganti tema di salah satunya meninggalkan tab
       satunya di tema lama — dan tab lama itulah yang biasanya sedang dilihat.
       Ini bukan kasus pinggiran: alurnya persis seperti orang memakai portal,
       yaitu beranda di satu tab dan halaman layanan di tab sebelah.

    3. Ikuti pemulihan dari bfcache (event `pageshow` dengan `persisted`).
       Saat pengguna menekan tombol Kembali, peramban boleh mengembalikan
       halaman apa adanya dari memori — <head> tidak dijalankan ulang, jadi
       tanpa penanganan ini halaman yang muncul membawa tema dari sebelum
       pilihannya diganti.

    Alpine tidak dipanggil dari sini. Halaman error sengaja berdiri tanpa
    Alpine, jadi skrip ini harus tetap bekerja tanpanya. Untuk layout yang
    memakai Alpine, perubahan dari luar disiarkan lewat event `tema-luar` dan
    layout-nya yang menyambung ke state-nya sendiri — lihat atribut
    `@tema-luar.window` di tag <html> masing-masing. Tanpa itu kelasnya memang
    ikut berubah, tapi ikon matahari/bulan tertinggal di posisi lama dan klik
    berikutnya tampak tidak berfungsi karena membalik dari nilai yang basi.
--}}
<script>
    (function () {
        function pilihan() {
            // localStorage melempar galat di mode privat sebagian peramban.
            // Gagal baca berarti terang, bukan halaman yang meledak.
            try {
                return localStorage.getItem('darkMode') === 'true';
            } catch (e) {
                return false;
            }
        }

        function terapkan(gelap) {
            document.documentElement.classList.toggle('dark', gelap);
        }

        function ikutiLuar() {
            var gelap = pilihan();
            terapkan(gelap);
            window.dispatchEvent(new CustomEvent('tema-luar', { detail: { gelap: gelap } }));
        }

        terapkan(pilihan());

        // `storage` hanya menyala di tab LAIN, bukan di tab yang menulis —
        // jadi tidak ada risiko berputar dengan $watch yang menulisnya.
        window.addEventListener('storage', function (e) {
            if (e.key !== null && e.key !== 'darkMode') return;
            ikutiLuar();
        });

        window.addEventListener('pageshow', function (e) {
            if (e.persisted) ikutiLuar();
        });
    })();
</script>
