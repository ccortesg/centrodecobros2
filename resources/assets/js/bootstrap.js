
import axios from 'axios';
import EchoModule from 'laravel-echo';
import lodash from 'lodash';
import PusherModule from 'pusher-js';

const Echo = EchoModule.default ?? EchoModule;
const Pusher = PusherModule.default ?? PusherModule;

window._ = lodash;

/**
 * We'll load jQuery and the Bootstrap jQuery plugin which provides support
 * for JavaScript based Bootstrap features such as modals and tabs. This
 * code may be modified to fit the specific needs of your application.
 */

try {
    // The authenticated shell already loads Bootstrap JS from `plantilla.js`.
    // Keeping a second Bootstrap runtime here creates drift with the vendor asset lane.
} catch (e) {}

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Next we will register the CSRF Token as a common header with Axios so that
 * all outgoing HTTP requests automatically have it attached. This is just
 * a simple convenience so we don't have to attach every token manually.
 */

let token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

window.Pusher = Pusher;

const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY || '';
const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';

if (pusherKey) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: pusherCluster,
        encrypted: true
    });
} else {
    window.Echo = null;
}
