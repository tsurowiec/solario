import flatpickr from 'flatpickr';
window.flatpickr = flatpickr;

import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;
window.dispatchEvent(new CustomEvent('apexcharts-ready'));
