const AUTHENTICATED_SHELL_SELECTOR = '[data-shell-header="authenticated"], [data-shell-sidebar="authenticated"]';
const AUTHENTICATED_APP_ROOT_SELECTOR = '#app';
const AUTHENTICATED_NOOP_LINK_SELECTOR = 'a[href="#"]:not([data-top="true"])';

const LEGACY_HASH_MENU_TARGETS = new Map([
    ['main', 0],
    ['dashboard', 0],
    ['escritorio', 0],
    ['ligas-pago', 1],
    ['transaccion', 1],
    ['respuestas-ligas', 2],
    ['respuesta', 2],
    ['user', 3],
    ['usuario', 3],
    ['usuarios', 3],
    ['rol', 4],
    ['role', 4],
    ['roles', 4],
    ['ayuda', 5],
    ['acerca', 6],
    ['estado', 7],
    ['estados', 7],
    ['ciudad', 8],
    ['ciudades', 8],
    ['cliente', 9],
    ['clientes', 9],
    ['cliente-consolidar', 10],
    ['consolidar', 10],
    ['cliente-depurar', 28],
    ['depurar', 28],
    ['domiciliacion', 11],
    ['liga-domiciliacion', 11],
    ['respuestas-domiciliacion', 12],
    ['cargos-recurrentes', 13],
    ['spei', 14],
    ['generar-referencia', 14],
    ['respuestas-spei', 15],
    ['reporte-ligas', 18],
    ['ingresos-ligas', 18],
    ['reporte-domiciliacion', 19],
    ['registro-domiciliacion', 19],
    ['reporte-spei', 20],
    ['ingresos-spei', 20],
    ['reporte-terminal', 21],
    ['ingresos-terminal', 21],
    ['consulta-spei', 22],
    ['consultaspei', 22],
    ['pago-spei', 23],
    ['pagospei', 23],
    ['cancela-spei', 24],
    ['cancelaspei', 24],
    ['reporte-cargos-recurrentes', 25],
    ['ingresos-cargos-recurrentes', 25],
    ['terminal', 26],
    ['terminal-respuestas', 27],
    ['integraciones-outgoing', 31],
    ['outgoing-api-requests', 31],
    ['integraciones-incoming', 32],
    ['incoming-api-requests', 32],
    ['user-activity-log', 33],
    ['integraciones-actividad', 33],
]);

let listenersBound = false;

function hasAuthenticatedShell() {
    return Boolean(document.querySelector(AUTHENTICATED_SHELL_SELECTOR));
}

function getAuthenticatedAppRoot() {
    if (!hasAuthenticatedShell()) {
        return null;
    }

    return document.querySelector(AUTHENTICATED_APP_ROOT_SELECTOR);
}

function normalizeLegacyHash(hash) {
    if (typeof hash !== 'string') {
        return '';
    }

    return hash
        .replace(/^#\/?/, '')
        .replace(/\?.*$/, '')
        .replace(/\/+$/, '')
        .trim()
        .toLowerCase();
}

function resolveMenuFromLegacyHash(hash) {
    const normalizedHash = normalizeLegacyHash(hash);

    if (!normalizedHash) {
        return null;
    }

    const directMatch = LEGACY_HASH_MENU_TARGETS.get(normalizedHash);

    if (Number.isFinite(directMatch)) {
        return directMatch;
    }

    const lastSegment = normalizedHash.split('/').filter(Boolean).pop();

    if (!lastSegment) {
        return null;
    }

    const segmentedMatch = LEGACY_HASH_MENU_TARGETS.get(lastSegment);

    return Number.isFinite(segmentedMatch) ? segmentedMatch : null;
}

function clearLegacyHash() {
    if (!window.location.hash) {
        return;
    }

    const cleanUrl = `${window.location.pathname}${window.location.search}`;

    window.history.replaceState(window.history.state, document.title, cleanUrl);
}

function syncMenuFromLegacyHash() {
    if (!hasAuthenticatedShell()) {
        return null;
    }

    const menuTarget = resolveMenuFromLegacyHash(window.location.hash);

    if (menuTarget === null) {
        return null;
    }

    if (window.CentroDeCobrosVueRoot && window.CentroDeCobrosVueRoot.menu !== menuTarget) {
        window.CentroDeCobrosVueRoot.menu = menuTarget;
    }

    clearLegacyHash();

    return menuTarget;
}

function handleDocumentClick(event) {
    const eventTarget = event.target;

    if (!(eventTarget instanceof Element)) {
        return;
    }

    const noopLink = eventTarget.closest(AUTHENTICATED_NOOP_LINK_SELECTOR);
    const appRoot = getAuthenticatedAppRoot();

    if (!noopLink || !appRoot || !appRoot.contains(noopLink)) {
        return;
    }

    event.preventDefault();
}

function bindListeners() {
    if (listenersBound) {
        return;
    }

    listenersBound = true;

    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('centrodecobros:app-mounted', () => {
        syncMenuFromLegacyHash();
    });
    document.addEventListener('centrodecobros:menu-changed', () => {
        clearLegacyHash();
    });
}

export function initAuthenticatedShellNavigation() {
    bindListeners();

    return {
        syncFromHash() {
            return syncMenuFromLegacyHash();
        },
    };
}
