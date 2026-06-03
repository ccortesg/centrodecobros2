const SHELL_HEADER_SELECTOR = '[data-shell-header="authenticated"]';
const SHELL_DROPDOWN_SELECTOR = '[data-shell-dropdown]';
const SHELL_DROPDOWN_TOGGLE_SELECTOR = '[data-shell-dropdown-toggle]';
const SHELL_DROPDOWN_MENU_SELECTOR = '[data-shell-dropdown-menu]';
const SHELL_NOOP_LINK_SELECTOR = '[data-shell-link="noop"]';

let listenersBound = false;

function getHeaderRoot() {
    return document.querySelector(SHELL_HEADER_SELECTOR);
}

function getHeaderDropdowns() {
    const headerRoot = getHeaderRoot();

    return headerRoot
        ? Array.from(headerRoot.querySelectorAll(SHELL_DROPDOWN_SELECTOR))
        : [];
}

function setDropdownState(dropdown, shouldOpen) {
    const toggle = dropdown.querySelector(SHELL_DROPDOWN_TOGGLE_SELECTOR);
    const menu = dropdown.querySelector(SHELL_DROPDOWN_MENU_SELECTOR);

    dropdown.classList.toggle('show', shouldOpen);
    toggle?.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    menu?.classList.toggle('show', shouldOpen);
}

function closeAllDropdowns({ except } = {}) {
    getHeaderDropdowns().forEach(dropdown => {
        if (except && dropdown === except) {
            return;
        }

        setDropdownState(dropdown, false);
    });
}

function toggleDropdown(dropdown) {
    const isOpen = dropdown.classList.contains('show');

    closeAllDropdowns({ except: dropdown });
    setDropdownState(dropdown, !isOpen);
}

function handleDocumentClick(event) {
    const eventTarget = event.target;

    if (!(eventTarget instanceof Element)) {
        return;
    }

    const headerRoot = getHeaderRoot();

    if (!headerRoot) {
        return;
    }

    const dropdownToggle = eventTarget.closest(SHELL_DROPDOWN_TOGGLE_SELECTOR);

    if (dropdownToggle && headerRoot.contains(dropdownToggle)) {
        event.preventDefault();
        toggleDropdown(dropdownToggle.closest(SHELL_DROPDOWN_SELECTOR));
        return;
    }

    const noopLink = eventTarget.closest(SHELL_NOOP_LINK_SELECTOR);

    if (noopLink && headerRoot.contains(noopLink)) {
        event.preventDefault();
    }

    if (!headerRoot.contains(eventTarget)) {
        closeAllDropdowns();
    }
}

function handleDocumentKeydown(event) {
    if (event.key !== 'Escape') {
        return;
    }

    closeAllDropdowns();
}

function bindListeners() {
    if (listenersBound) {
        return;
    }

    listenersBound = true;

    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleDocumentKeydown);
    document.addEventListener('centrodecobros:app-mounted', () => {
        closeAllDropdowns();
    });
    document.addEventListener('centrodecobros:menu-changed', () => {
        closeAllDropdowns();
    });
}

export function initAuthenticatedShellHeader() {
    bindListeners();

    return {
        closeAll() {
            closeAllDropdowns();
        },
    };
}
