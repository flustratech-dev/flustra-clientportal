/**
 * SweetAlert2 Confirm Override
 * Intercepts forms with inline confirm() and upgrades them to SweetAlert2 modals.
 */

let confirmObserver = null;

function initConfirmOverride() {
    document.querySelectorAll('form').forEach(form => {
        const onsubmitAttr = form.getAttribute('onsubmit');
        if (onsubmitAttr && onsubmitAttr.includes('confirm(')) {
            const match = onsubmitAttr.match(/confirm\(['"](.+?)['"]\)/);
            if (match && match[1]) {
                const message = match[1];

                form.removeAttribute('onsubmit');

                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const isDark = document.documentElement.classList.contains('dark') || localStorage.getItem('darkMode') === 'true';
                    const lowerMsg = message.toLowerCase();
                    const lowerAction = (form.getAttribute('action') || '').toLowerCase();
                    const isDelete = lowerMsg.includes('hapus') || lowerMsg.includes('delete') || lowerAction.includes('delete') || lowerAction.includes('destroy');

                    if (isDelete) {
                        Swal.fire({
                            title: 'Apakah Anda yakin?',
                            text: 'Tindakan ini tidak dapat dibatalkan!',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Ya, hapus!',
                            cancelButtonText: 'Batal',
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            background: isDark ? '#1e293b' : '#ffffff',
                            color: isDark ? '#f8fafc' : '#0f172a',
                        }).then((result) => {
                            if (result.isConfirmed) {
                                Swal.fire({
                                    title: 'Terhapus!',
                                    text: 'Data Anda telah berhasil dihapus.',
                                    icon: 'success',
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#3085d6',
                                    background: isDark ? '#1e293b' : '#ffffff',
                                    color: isDark ? '#f8fafc' : '#0f172a',
                                }).then(() => {
                                    form.submit();
                                });
                            } else if (result.dismiss === Swal.DismissReason.cancel) {
                                Swal.fire({
                                    title: 'Dibatalkan',
                                    text: 'Data Anda tetap aman :)',
                                    icon: 'error',
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#3085d6',
                                    background: isDark ? '#1e293b' : '#ffffff',
                                    color: isDark ? '#f8fafc' : '#0f172a',
                                });
                            }
                        });
                    } else {
                        let title = 'Apakah Anda yakin?';
                        let text = message;

                        const cleanPattern = /^(apakah\s+anda\s+yakin\s+ingin\s+|apakah\s+anda\s+yakin\s+|apakah\s+yakin\s+)/i;
                        if (cleanPattern.test(text)) {
                            let cleanedText = text.replace(cleanPattern, '').trim();
                            text = cleanedText.charAt(0).toUpperCase() + cleanedText.slice(1);
                        }

                        let confirmBtnText = 'Ya, Lanjutkan';
                        if (lowerMsg.includes('setujui') || lowerMsg.includes('approve') || lowerAction.includes('approve')) {
                            confirmBtnText = 'Ya, Setujui!';
                        }

                        Swal.fire({
                            title: title,
                            text: text,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: confirmBtnText,
                            cancelButtonText: 'Batal',
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            background: isDark ? '#1e293b' : '#ffffff',
                            color: isDark ? '#f8fafc' : '#0f172a',
                        }).then((result) => {
                            if (result.isConfirmed) {
                                Swal.fire({
                                    title: 'Berhasil!',
                                    text: 'Tindakan Anda telah diproses.',
                                    icon: 'success',
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#3085d6',
                                    background: isDark ? '#1e293b' : '#ffffff',
                                    color: isDark ? '#f8fafc' : '#0f172a',
                                }).then(() => {
                                    form.submit();
                                });
                            } else if (result.dismiss === Swal.DismissReason.cancel) {
                                Swal.fire({
                                    title: 'Dibatalkan',
                                    text: 'Tindakan telah dibatalkan.',
                                    icon: 'error',
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#3085d6',
                                    background: isDark ? '#1e293b' : '#ffffff',
                                    color: isDark ? '#f8fafc' : '#0f172a',
                                });
                            }
                        });
                    }
                });
            }
        }
    });
}

function setupConfirmObserver() {
    if (confirmObserver) {
        confirmObserver.disconnect();
    }

    confirmObserver = new MutationObserver((mutations) => {
        let shouldInit = false;
        mutations.forEach(mutation => {
            if (mutation.addedNodes.length) {
                shouldInit = true;
            }
        });
        if (shouldInit) {
            initConfirmOverride();
        }
    });

    confirmObserver.observe(document.body, {
        childList: true,
        subtree: true
    });
}

function startConfirmOverride() {
    initConfirmOverride();
    setupConfirmObserver();
}

// Register on load/ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startConfirmOverride);
} else {
    startConfirmOverride();
}

// Register on Hotwire Turbo page transition load
document.addEventListener('turbo:load', startConfirmOverride);

