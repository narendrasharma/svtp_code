import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Same-origin XSRF protection works out of the box: axios reads the
// XSRF-TOKEN cookie into the X-XSRF-TOKEN header automatically.
