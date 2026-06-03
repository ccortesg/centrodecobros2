(function initLegacyTemplateAjaxHash(window, document, $) {
  'use strict';

  var templateState = window.CentroDeCobrosLegacyTemplate;

  if (!templateState || !$) {
    return;
  }

  var legacyAjaxHash = window.CentroDeCobrosLegacyAjaxHash || {};

  function loadJS(jsFiles, pageScript) {
    var body = document.getElementsByTagName('body')[0];

    jsFiles.forEach(function(jsFile) {
      var script = document.createElement('script');
      script.type = 'text/javascript';
      script.async = false;
      script.src = jsFile;
      body.appendChild(script);
    });

    if (pageScript) {
      var pageScriptTag = document.createElement('script');
      pageScriptTag.type = 'text/javascript';
      pageScriptTag.async = false;
      pageScriptTag.src = pageScript;
      body.appendChild(pageScriptTag);
    }

    if (typeof window.init === 'function') {
      window.init();
    }
  }

  function loadCSS(cssFile, end, callback) {
    var head = document.getElementsByTagName('head')[0];
    var style = document.getElementById('main-style');
    var stylesheet = document.createElement('link');

    stylesheet.setAttribute('rel', 'stylesheet');
    stylesheet.setAttribute('type', 'text/css');
    stylesheet.setAttribute('href', cssFile);
    stylesheet.onload = callback;

    if (end === 1 || !style || !style.parentNode) {
      head.appendChild(stylesheet);
      return;
    }

    head.insertBefore(stylesheet, style);
  }

  function setUpUrl(url) {
    if (!url) {
      return false;
    }

    $('nav .nav li .nav-link').removeClass('active');
    $('nav .nav li.nav-dropdown').removeClass('open');

    return loadPage(url);
  }

  function loadPage(url) {
    if (!url || !$.subPagesDirectory) {
      return false;
    }

    templateState.refreshReferences();

    $.ajax({
      type: 'GET',
      url: $.subPagesDirectory + url,
      dataType: 'html',
      cache: false,
      async: false,
      beforeSend: function() {
        $.mainContent.css({ opacity: 0 });
      },
      success: function() {
        if (window.Pace && typeof window.Pace.restart === 'function') {
          window.Pace.restart();
        }

        $('html, body').animate({ scrollTop: 0 }, 0);
      },
      error: function() {
        if ($.page404) {
          window.location.href = $.page404;
        }
      }
    });

    return true;
  }

  function bindLegacyAjaxClicks() {
    $(document).off('click.templateLegacyAjaxHash', '.nav a[href!="#"]');
    $(document).off('click.templateLegacyAjaxHashNoop', 'a[href="#"]');

    $(document).on('click.templateLegacyAjaxHash', '.nav a[href!="#"]', function(event) {
      if ($(this).parent().parent().hasClass('nav-tabs') || $(this).parent().parent().hasClass('nav-pills')) {
        event.preventDefault();
        return;
      }

      if ($(this).attr('target') === '_top') {
        event.preventDefault();
        window.location = $(event.currentTarget).attr('href');
        return;
      }

      if ($(this).attr('target') === '_blank') {
        event.preventDefault();
        window.open($(event.currentTarget).attr('href'));
        return;
      }

      event.preventDefault();
      setUpUrl($(event.currentTarget).attr('href'));
    });

    $(document).on('click.templateLegacyAjaxHashNoop', 'a[href="#"]', function(event) {
      event.preventDefault();
    });
  }

  function start() {
    if (!templateState.shouldEnableLegacyAjaxHash()) {
      legacyAjaxHash.active = false;
      return false;
    }

    legacyAjaxHash.active = true;
    legacyAjaxHash.paceOptions = {
      elements: false,
      restartOnRequestAfter: false
    };

    bindLegacyAjaxClicks();

    var url = window.location.hash.replace(/^#/, '');
    if (url !== '') {
      setUpUrl(url);
      return true;
    }

    if ($.defaultPage) {
      setUpUrl($.defaultPage);
      return true;
    }

    return false;
  }

  legacyAjaxHash.active = templateState.shouldEnableLegacyAjaxHash();
  legacyAjaxHash.loadJS = loadJS;
  legacyAjaxHash.loadCSS = loadCSS;
  legacyAjaxHash.setUpUrl = setUpUrl;
  legacyAjaxHash.loadPage = loadPage;
  legacyAjaxHash.start = start;
  legacyAjaxHash.configure = function configure(options) {
    if (!options) {
      return;
    }

    if (Object.prototype.hasOwnProperty.call(options, 'defaultPage')) {
      $.defaultPage = options.defaultPage;
    }

    if (Object.prototype.hasOwnProperty.call(options, 'subPagesDirectory')) {
      $.subPagesDirectory = options.subPagesDirectory;
    }

    if (Object.prototype.hasOwnProperty.call(options, 'page404')) {
      $.page404 = options.page404;
    }
  };

  window.CentroDeCobrosLegacyAjaxHash = legacyAjaxHash;
  window.loadJS = loadJS;
  window.loadCSS = loadCSS;
  window.setUpUrl = setUpUrl;
  window.loadPage = loadPage;

  if (legacyAjaxHash.active) {
    legacyAjaxHash.start();
  }
})(window, document, window.jQuery || window.$);
