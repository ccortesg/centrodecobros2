
import { createApp } from 'vue';
import importedJQuery from 'jquery';

import './bootstrap';
import { initAuthenticatedShellHeader, initAuthenticatedShellNavigation, initAuthenticatedShellSidebar } from './shell';
import './styles/ux-ui.css';

import Rol from './components/Rol.vue';
import Role from './components/Role.vue';
import User from './components/User.vue';
import Estado from './components/Estado.vue';
import Ciudad from './components/Ciudad.vue';
import Cliente from './components/Cliente.vue';
import ClienteConsolidar from './components/ClienteConsolidar.vue';
import ClienteDepurar from './components/ClienteDepurar.vue';
import Dashboard from './components/Dashboard.vue';
import Notification from './components/Notification.vue';
import Transaccion from './components/Transaccion.vue';
import Respuesta from './components/Respuesta.vue';
import TransaccionDom from './components/TransaccionDom.vue';
import ReporteLigas from './components/ReporteLigas.vue';
import ReporteLigasDom from './components/ReporteLigasDom.vue';
import ReporteCargosRecurrentes from './components/ReporteCargosRecurrentes.vue';
import ConsultaSpei from './components/ConsultaSpei.vue';
import PagoSpei from './components/PagoSpei.vue';
import CancelaSpei from './components/CancelaSpei.vue';
import ReporteSpei from './components/ReporteSpei.vue';

const jQuery = window.jQuery || window.$ || importedJQuery;
const $ = jQuery;

if (!window.$) {
    window.$ = jQuery;
}

if (!window.jQuery) {
    window.jQuery = jQuery;
}

const components = {
    rol: Rol,
    role: Role,
    user: User,
    estado: Estado,
    ciudad: Ciudad,
    cliente: Cliente,
    clienteconsolidar: ClienteConsolidar,
    clientedepurar: ClienteDepurar,
    dashboard: Dashboard,
    notification: Notification,
    transaccion: Transaccion,
    respuesta: Respuesta,
    transacciondom: TransaccionDom,
    reporteligas: ReporteLigas,
    reporteligasdom: ReporteLigasDom,
    reportecargosrecurrentes: ReporteCargosRecurrentes,
    consultaspei: ConsultaSpei,
    pagospei: PagoSpei,
    cancelaspei: CancelaSpei,
    reportespei: ReporteSpei
};

const shellHeader = initAuthenticatedShellHeader();
const shellNavigation = initAuthenticatedShellNavigation();
const shellSidebar = initAuthenticatedShellSidebar();

function formatCurrency(value) {
    const amount = Number.parseFloat(value);
    const normalizedAmount = Number.isFinite(amount) ? amount : 0;

    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(normalizedAmount);
}

function padDatePart(value) {
    return String(value).padStart(2, '0');
}

function parseDateValue(value) {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    const rawValue = String(value).trim();

    if (!rawValue) {
        return null;
    }

    const dateParts = rawValue.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2}))?/);

    if (dateParts) {
        return new Date(
            Number(dateParts[1]),
            Number(dateParts[2]) - 1,
            Number(dateParts[3]),
            Number(dateParts[4] || 0),
            Number(dateParts[5] || 0),
            Number(dateParts[6] || 0)
        );
    }

    const parsed = new Date(rawValue);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function formatDateMx(value) {
    const date = parseDateValue(value);

    if (!date) {
        return '';
    }

    return [
        padDatePart(date.getDate()),
        padDatePart(date.getMonth() + 1),
        date.getFullYear()
    ].join('-');
}

function formatTimeMx(value) {
    const date = parseDateValue(value);

    if (!date) {
        return '';
    }

    return [
        padDatePart(date.getHours()),
        padDatePart(date.getMinutes()),
        padDatePart(date.getSeconds())
    ].join(':');
}

function formatDateTimeMx(value) {
    return {
        date: formatDateMx(value),
        time: formatTimeMx(value)
    };
}

function compactPagination(pagination, radius = 2) {
    if (!pagination || !pagination.to || !pagination.current_page || !pagination.last_page) {
        return [];
    }

    const currentPage = Number(pagination.current_page);
    const lastPage = Number(pagination.last_page);
    const windowRadius = Math.max(1, Number(radius) || 2);
    const from = Math.max(1, currentPage - windowRadius);
    const to = Math.min(lastPage, currentPage + windowRadius);
    const pages = [];

    for (let page = from; page <= to; page += 1) {
        pages.push(page);
    }

    return pages;
}

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */
const app = createApp({
    data() {
        return {
            menu: 0,
            notifications: []
        };
    },
    watch: {
        menu(newMenu) {
            document.dispatchEvent(new CustomEvent('centrodecobros:menu-changed', {
                detail: {
                    menu: Number(newMenu),
                },
            }));
        }
    },
    created() {
        const me = this;

        axios.post('notification/get').then(function(response) {
            me.notifications = response.data;
        }).catch(function(error) {
            console.log(error);
        });

        const userId = $('meta[name="userId"]').attr('content');

        if (userId && window.Echo && typeof window.Echo.private === 'function') {
            window.Echo.private('App.User.' + userId).notification((notification) => {
                me.notifications.unshift(notification);
            });
        }
    }
});

app.config.globalProperties.$formatCurrency = formatCurrency;
app.config.globalProperties.$formatDateMx = formatDateMx;
app.config.globalProperties.$formatTimeMx = formatTimeMx;
app.config.globalProperties.$formatDateTimeMx = formatDateTimeMx;
app.config.globalProperties.$paginationPages = compactPagination;

Object.entries(components).forEach(([name, component]) => {
    app.component(name, component);
});

const appRoot = document.getElementById('app');

if (appRoot) {
    const rootInstance = app.mount(appRoot);

    window.CentroDeCobrosVueApp = app;
    window.CentroDeCobrosVueRoot = rootInstance;
    shellNavigation.syncFromHash();
    shellHeader.closeAll();
    shellSidebar.sync(rootInstance.menu);
    document.dispatchEvent(new CustomEvent('centrodecobros:app-mounted', {
        detail: {
            menu: rootInstance.menu,
        },
    }));
}
