const SHELL_SIDEBAR_SELECTOR = '[data-shell-sidebar="authenticated"]';
const MENU_ITEM_SELECTOR = '[data-menu-target]';
const DROPDOWN_SELECTOR = '.nav-dropdown';
const DROPDOWN_TOGGLE_SELECTOR = '.nav-dropdown-toggle';

let listenersBound = false;

function getSidebarRoot() {
    return document.querySelector(SHELL_SIDEBAR_SELECTOR);
}

function normalizeMenuTarget(value) {
    const parsedValue = Number.parseInt(value, 10);

    return Number.isFinite(parsedValue) ? parsedValue : null;
}

function broadcastResize() {
    let timesRun = 0;
    const interval = window.setInterval(() => {
        timesRun += 1;

        if (timesRun === 5) {
            window.clearInterval(interval);
        }

        window.dispatchEvent(new Event('resize'));
    }, 62.5);
}

function openAncestorDropdowns(menuItem) {
    let currentParent = menuItem.parentElement;

    while (currentParent) {
        const dropdown = currentParent.closest(DROPDOWN_SELECTOR);

        if (!dropdown) {
            break;
        }

        dropdown.classList.add('open');
        currentParent = dropdown.parentElement;
    }
}

function syncActiveMenu(menuTarget) {
    const sidebarRoot = getSidebarRoot();
    const normalizedTarget = normalizeMenuTarget(menuTarget);

    if (!sidebarRoot || normalizedTarget === null) {
        return;
    }

    sidebarRoot.querySelectorAll(`${MENU_ITEM_SELECTOR} > .nav-link.active`).forEach(link => {
        link.classList.remove('active');
    });

    const activeMenuItem = sidebarRoot.querySelector(`${MENU_ITEM_SELECTOR}[data-menu-target="${normalizedTarget}"]`);

    if (!activeMenuItem) {
        return;
    }

    const activeLink = activeMenuItem.querySelector(':scope > .nav-link');

    if (activeLink) {
        activeLink.classList.add('active');
    }

    openAncestorDropdowns(activeMenuItem);
}

function syncVueMenu(menuTarget) {
    const normalizedTarget = normalizeMenuTarget(menuTarget);

    if (normalizedTarget === null) {
        return;
    }

    if (window.CentroDeCobrosVueRoot && window.CentroDeCobrosVueRoot.menu !== normalizedTarget) {
        window.CentroDeCobrosVueRoot.menu = normalizedTarget;
    }

    syncActiveMenu(normalizedTarget);
}

function handleBodyClassToggles(eventTarget) {
    const body = document.body;

    if (!body) {
        return false;
    }

    let didToggle = false;

    if (eventTarget.closest('.sidebar-toggler')) {
        body.classList.toggle('sidebar-hidden');
        didToggle = true;
    }

    if (eventTarget.closest('.sidebar-minimizer')) {
        body.classList.toggle('sidebar-minimized');
        didToggle = true;
    }

    if (eventTarget.closest('.brand-minimizer')) {
        body.classList.toggle('brand-minimized');
    }

    if (eventTarget.closest('.aside-menu-toggler')) {
        body.classList.toggle('aside-menu-hidden');
        didToggle = true;
    }

    if (eventTarget.closest('.mobile-sidebar-toggler')) {
        body.classList.toggle('sidebar-mobile-show');
        didToggle = true;
    }

    if (eventTarget.closest('.sidebar-close')) {
        body.classList.toggle('sidebar-opened');
        document.documentElement.classList.toggle('sidebar-opened');
    }

    if (didToggle) {
        broadcastResize();
    }

    return didToggle;
}

function handleDocumentClick(event) {
    const eventTarget = event.target;

    if (!(eventTarget instanceof Element)) {
        return;
    }

    if (handleBodyClassToggles(eventTarget)) {
        event.preventDefault();
        return;
    }

    const sidebarRoot = getSidebarRoot();

    if (!sidebarRoot || !sidebarRoot.contains(eventTarget)) {
        return;
    }

    const dropdownToggle = eventTarget.closest(DROPDOWN_TOGGLE_SELECTOR);

    if (dropdownToggle && sidebarRoot.contains(dropdownToggle)) {
        event.preventDefault();
        dropdownToggle.closest(DROPDOWN_SELECTOR)?.classList.toggle('open');
        broadcastResize();
        return;
    }

    const menuItem = eventTarget.closest(MENU_ITEM_SELECTOR);

    if (!menuItem || !sidebarRoot.contains(menuItem)) {
        return;
    }

    const menuTarget = normalizeMenuTarget(menuItem.getAttribute('data-menu-target'));

    if (menuTarget === null) {
        return;
    }

    event.preventDefault();
    syncVueMenu(menuTarget);
}

function bindListeners() {
    if (listenersBound) {
        return;
    }

    listenersBound = true;

    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('centrodecobros:app-mounted', () => {
        syncVueMenu(window.CentroDeCobrosVueRoot?.menu);
    });
    document.addEventListener('centrodecobros:menu-changed', event => {
        syncActiveMenu(event.detail?.menu);
    });
}

export function initAuthenticatedShellSidebar() {
    bindListeners();

    return {
        sync(menuTarget) {
            syncVueMenu(menuTarget);
        },
    };
}
