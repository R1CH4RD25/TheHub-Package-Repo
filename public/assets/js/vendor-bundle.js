/**
 * Vendor Bundle Entry Point
 * Bundles all third-party libraries for production use
 */

// Bootstrap (JS + CSS)
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap';

// Bootstrap Icons (CSS only - icons are font-based)
import 'bootstrap-icons/font/bootstrap-icons.css';

// Alpine.js - Lightweight reactive framework
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

// HTMX - HTML-over-the-wire for dynamic updates
import htmx from 'htmx.org';
window.htmx = htmx;

// SweetAlert2 - Beautiful modals and alerts
import Swal from 'sweetalert2';
window.Swal = Swal;

// AOS - Animate On Scroll library
import AOS from 'aos';
import 'aos/dist/aos.css';
window.AOS = AOS;

// Chart.js - Powerful charting library
import Chart from 'chart.js/auto';
window.Chart = Chart;

// ApexCharts - Modern interactive charts
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;

// Flatpickr - Datetime picker
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
window.flatpickr = flatpickr;

// Tom Select - Advanced select/autocomplete
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.bootstrap5.css';
window.TomSelect = TomSelect;

// DataTables - Advanced table features
import 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';

// Quill - Rich text editor
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
window.Quill = Quill;

// Day.js - Lightweight date library
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import customParseFormat from 'dayjs/plugin/customParseFormat';
dayjs.extend(relativeTime);
dayjs.extend(customParseFormat);
window.dayjs = dayjs;

// Lodash - Utility library
import _ from 'lodash';
window._ = _;

// Axios - HTTP client
import axios from 'axios';
window.axios = axios;

// Tippy.js - Tooltips and popovers
import tippy from 'tippy.js';
import 'tippy.js/dist/tippy.css';
window.tippy = tippy;

// Notyf - Toast notifications
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
window.Notyf = Notyf;

console.log('✅ The Hub vendor bundle loaded successfully');
