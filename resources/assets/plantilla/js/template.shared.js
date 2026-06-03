(function initLegacyTemplateShared(window, document, $) {
  'use strict';

  var templateState = window.CentroDeCobrosLegacyTemplate;

  if (!templateState || !$) {
    return;
  }

  function markActiveNavigationLinks() {
    if (templateState.hasAuthenticatedModernShell()) {
      return;
    }

    templateState.refreshReferences();

    if (!$.navigation.length) {
      return;
    }

    $.navigation.find('a').each(function() {
      var currentUrl = String(window.location).split('?')[0];

      if (currentUrl.substr(currentUrl.length - 1) === '#') {
        currentUrl = currentUrl.slice(0, -1);
      }

      if (this.href === currentUrl) {
        $(this).addClass('active');

        $(this).parents('ul').add(this).each(function() {
          $(this).parent().addClass('open');
        });
      }
    });
  }

  function bindNoopAnchors() {
    $(document).off('click.templatePreventTop', templateState.selectors.noopAnchor);

    if (templateState.hasAuthenticatedModernShell()) {
      return;
    }

    $(document).on('click.templatePreventTop', templateState.selectors.noopAnchor, function(event) {
      event.preventDefault();
    });
  }

  function initLegacySurface() {
    bindNoopAnchors();
    markActiveNavigationLinks();
    return true;
  }

  templateState.markActiveNavigationLinks = markActiveNavigationLinks;
  templateState.bindNoopAnchors = bindNoopAnchors;
  templateState.init = initLegacySurface;

  window.init = function init() {
    return templateState.init();
  };

  $(document).ready(function() {
    templateState.init();
  });

  $(document).on('centrodecobros:app-mounted', function() {
    templateState.init();
  });
})(window, document, window.jQuery || window.$);
