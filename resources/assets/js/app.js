
import { createApp } from 'vue';
import importedJQuery from 'jquery';

import './bootstrap';
import { initAuthenticatedShellHeader, initAuthenticatedShellNavigation, initAuthenticatedShellSidebar } from './shell';

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
