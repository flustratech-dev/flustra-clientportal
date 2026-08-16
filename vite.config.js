import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        /*
         * Port Vite dipatok, bukan dibiarkan mencari sendiri.
         *
         * Bawaan Vite adalah 5173, dan kalau port itu sudah dipakai ia diam-diam
         * pindah ke 5174, 5175, dan seterusnya. Di mesin ini ada banyak project
         * Laravel yang jalan bersamaan, jadi yang terjadi begini: portal start
         * lebih dulu dan menulis "5173" ke public/hot, portal dimatikan tanpa
         * sempat membersihkan berkas itu, lalu project lain mengambil 5173.
         * Halaman portal kemudian memuat CSS dan JS milik project itu.
         *
         * Gejalanya sangat menyesatkan: keduanya sama-sama memakai Tailwind,
         * jadi utilitas umum seperti `flex` tetap ada dan halamannya tampak
         * "hampir benar" — tapi kelas khas portal (`erp-card`, `min-h-[550px]`)
         * hilang, kartu login mengempis jadi 2 piksel, dan layarnya tampak
         * kosong tanpa satu pun pesan galat.
         *
         * `strictPort` membuat Vite GAGAL dengan jelas kalau portnya dipakai,
         * alih-alih mengembara ke port lain diam-diam.
         *
         * Konvensinya: 5100 + dua digit terakhir port aplikasi.
         * flustra-clientportal 8008 → 5108. (ERP 8006 → 5106, dst.)
         */
        port: 5108,
        strictPort: true,
        host: 'localhost',

        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
