(function initLegacyTemplateBootstrap(window, document, $) {
  'use strict';

  if (!$) {
    return;
  }

  var templateState = window.CentroDeCobrosLegacyTemplate || {};

  templateState.selectors = Object.assign({}, templateState.selectors, {
    authenticatedShell: '[data-shell-header="authenticated"], [data-shell-sidebar="authenticated"]',
    noopAnchor: 'a[href="#"][data-top!=true]:not([data-shell-modern])'
  });

  templateState.hasAuthenticatedModernShell = function hasAuthenticatedModernShell() {
    return document.querySelector(templateState.selectors.authenticatedShell) !== null;
  };

  templateState.shouldEnableLegacyAjaxHash = function shouldEnableLegacyAjaxHash() {
    return document.querySelector('[data-template-legacy-ajax="enabled"]') !== null &&
      !templateState.hasAuthenticatedModernShell();
  };

  templateState.refreshReferences = function refreshReferences() {
    $.mainContent = $('#ui-view');
    $.navigation = $('nav > ul.nav');
  };

  templateState.state = Object.assign({}, templateState.state, {
    ajaxHashMode: templateState.shouldEnableLegacyAjaxHash() ? 'enabled' : 'disabled'
  });

  $.ajaxLoad = templateState.shouldEnableLegacyAjaxHash();
  templateState.refreshReferences();

  $.panelIconOpened = 'icon-arrow-up';
  $.panelIconClosed = 'icon-arrow-down';

  $.brandPrimary = '#20a8d8';
  $.brandSuccess = '#4dbd74';
  $.brandInfo = '#63c2de';
  $.brandWarning = '#f8cb00';
  $.brandDanger = '#f86c6b';

  $.grayDark = '#2a2c36';
  $.gray = '#55595c';
  $.grayLight = '#818a91';
  $.grayLighter = '#d1d4d7';
  $.grayLightest = '#f8f9fa';

  window.CentroDeCobrosLegacyTemplate = templateState;
})(window, document, window.jQuery || window.$);
