<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $judul }}</title>
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
    </style>
</head>
<body style="background-color: #f1f5f9; margin: 0; padding: 0;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f1f5f9; padding: 30px 15px;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01); border: 1px solid #e2e8f0;">
                    
                    <!-- Header Banner (Flustra Navy Gradient) -->
                    <tr>
                        <td align="left" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #172554 100%); padding: 32px 36px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td>
                                        <table border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="background-color: rgba(255, 255, 255, 0.12); padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.15);">
                                                    <span style="color: #60a5fa; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;">Flustra Client Portal</span>
                                                </td>
                                            </tr>
                                        </table>
                                        <h1 style="color: #ffffff; font-size: 20px; font-weight: 700; margin: 12px 0 0 0; letter-spacing: -0.5px;">
                                            Pemberitahuan Layanan
                                        </h1>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 36px;">
                            <!-- Status Pill Badge -->
                            @php
                                $badgeBg = match($tipe) {
                                    'success' => '#dcfce7',
                                    'error', 'danger' => '#ffe4e6',
                                    'warning' => '#fef3c7',
                                    default => '#e0f2fe',
                                };
                                $badgeColor = match($tipe) {
                                    'success' => '#15803d',
                                    'error', 'danger' => '#be123c',
                                    'warning' => '#b45309',
                                    default => '#0369a1',
                                };
                                $badgeText = match($tipe) {
                                    'success' => '✓ BERHASIL / DISETUJUI',
                                    'error', 'danger' => '✕ PERLU PERHATIAN',
                                    'warning' => '⚠ PEMBERITAHUAN',
                                    default => 'ℹ INFORMASI',
                                };
                            @endphp
                            
                            <table border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                                <tr>
                                    <td style="background-color: {{ $badgeBg }}; color: {{ $badgeColor }}; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px;">
                                        {{ $badgeText }}
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #334155; font-size: 14px; margin: 0 0 16px 0; line-height: 22px;">
                                Halo <strong>{{ $namaPenerima }}</strong>,
                            </p>

                            <h2 style="color: #0f172a; font-size: 17px; font-weight: 700; margin: 0 0 14px 0; line-height: 24px;">
                                {{ $judul }}
                            </h2>

                            <div style="color: #475569; font-size: 14px; line-height: 22px; margin-bottom: 24px;">
                                {!! nl2br(e($isi)) !!}
                            </div>

                            @if($nomorReferensi || $namaPerusahaan)
                            <!-- Details Box -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        @if($nomorReferensi)
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 6px;">
                                            <tr>
                                                <td style="color: #64748b; font-size: 12px; width: 140px;">No. Referensi / Pengajuan:</td>
                                                <td style="color: #0f172a; font-size: 13px; font-weight: 700; font-family: monospace;">{{ $nomorReferensi }}</td>
                                            </tr>
                                        </table>
                                        @endif

                                        @if($namaPerusahaan)
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="color: #64748b; font-size: 12px; width: 140px;">Nama Perusahaan:</td>
                                                <td style="color: #0f172a; font-size: 13px; font-weight: 600;">{{ $namaPerusahaan }}</td>
                                            </tr>
                                        </table>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            @endif

                            @if($actionUrl)
                            <!-- CTA Button -->
                            <table border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 28px;">
                                <tr>
                                    <td align="center" style="border-radius: 10px; background-color: #2563eb;">
                                        <a href="{{ $actionUrl }}" target="_blank" style="font-size: 13px; font-weight: 700; font-family: sans-serif; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 10px; border: 1px solid #2563eb; display: inline-block;">
                                            {{ $actionText ?: 'Buka di Portal' }} &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            <p style="color: #64748b; font-size: 12px; line-height: 18px; margin: 0; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                                Anda menerima email ini karena akun Anda terdaftar di Flustra Client Portal. Jika ada pertanyaan, hubungi tim kami melalui helpdesk atau WhatsApp resmi.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color: #f8fafc; padding: 24px 36px; border-top: 1px solid #e2e8f0;">
                            <p style="color: #64748b; font-size: 11px; margin: 0 0 6px 0; font-weight: 600;">
                                PT Flustra Tech Nusantara &middot; B2B Enterprise Integration
                            </p>
                            <p style="color: #94a3b8; font-size: 10px; margin: 0;">
                                Email otomatis ini dikirim oleh sistem terintegrasi Flustra Office & Client Portal. Mohon tidak membalas email ini secara langsung.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
