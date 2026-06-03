(function initGuestPublicRuntime(window, document) {
    'use strict';

    var guestRuntime = window.CentroDeCobrosGuestPublic || {};

    function getGuestContext() {
        return document.querySelector('[data-template-context="guest"]');
    }

    function getGuestSurface() {
        return document.querySelector('[data-template-surface="guest"]') || getGuestContext() || document.body;
    }

    function getGuestView() {
        var guestContext = getGuestContext();

        return guestContext ? guestContext.getAttribute('data-template-view') || '' : '';
    }

    function getGuestScreen() {
        var guestSurface = getGuestSurface();
        var guestScreen = guestSurface ? guestSurface.querySelector('[data-template-screen]') : null;

        return guestScreen ? guestScreen.getAttribute('data-template-screen') || '' : '';
    }

    function init() {
        var guestContext = getGuestContext();

        if (!guestContext) {
            guestRuntime.ready = false;
            window.CentroDeCobrosGuestPublic = guestRuntime;
            return false;
        }

        var guestView = getGuestView();
        var guestScreen = getGuestScreen() || 'unknown';

        guestContext.setAttribute('data-template-guest-ready', 'true');
        guestContext.setAttribute('data-template-guest-screen-active', guestScreen);
        guestContext.setAttribute('data-template-runtime', 'guest-public');

        guestRuntime.ready = true;
        guestRuntime.surface = 'guest';
        guestRuntime.view = guestView;
        guestRuntime.screen = guestScreen;

        window.CentroDeCobrosGuestPublic = guestRuntime;
        document.dispatchEvent(new CustomEvent('centrodecobros:guest-public-ready', {
            detail: {
                view: guestView,
                screen: guestScreen,
            },
        }));

        return true;
    }

    guestRuntime.getContext = getGuestContext;
    guestRuntime.getSurface = getGuestSurface;
    guestRuntime.getView = getGuestView;
    guestRuntime.getScreen = getGuestScreen;
    guestRuntime.init = init;

    window.CentroDeCobrosGuestPublic = guestRuntime;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function onReady() {
            document.removeEventListener('DOMContentLoaded', onReady);
            guestRuntime.init();
        });
        return;
    }

    guestRuntime.init();
})(window, document);
