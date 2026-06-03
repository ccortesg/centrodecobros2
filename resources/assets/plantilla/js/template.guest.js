(function initLegacyTemplateGuest(window, document, $) {
  'use strict';

  var templateState = window.CentroDeCobrosLegacyTemplate;

  if (!templateState || !$) {
    return;
  }

  var guestContextSelector = '[data-template-context="guest"]';
  var guestSurfaceSelector = '[data-template-surface="guest"]';

  function getGuestContext() {
    return document.querySelector(guestContextSelector);
  }

  function getGuestSurface() {
    return document.querySelector(guestSurfaceSelector) || getGuestContext() || document.body;
  }

  function getGuestScreen() {
    var guestSurface = getGuestSurface();
    var guestScreen = guestSurface ? guestSurface.querySelector('[data-template-screen]') : null;

    return guestScreen ? guestScreen.getAttribute('data-template-screen') : '';
  }

  function initGuestLane() {
    var guestContext = getGuestContext();

    if (!guestContext) {
      return false;
    }

    templateState.bindNoopAnchors();
    guestContext.setAttribute('data-template-guest-ready', 'true');
    guestContext.setAttribute('data-template-guest-screen-active', getGuestScreen() || 'unknown');

    return true;
  }

  templateState.guest = Object.assign({}, templateState.guest, {
    init: initGuestLane,
    getContext: getGuestContext,
    getScreen: getGuestScreen
  });

  $(document).ready(function() {
    templateState.guest.init();
  });
})(window, document, window.jQuery || window.$);
