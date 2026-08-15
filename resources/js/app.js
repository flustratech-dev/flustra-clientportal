import './bootstrap';

import Alpine from 'alpinejs';

// Global confirm() -> SweetAlert2, sama seperti di flustra-erp.
import './confirm-override';

window.Alpine = Alpine;
Alpine.start();
