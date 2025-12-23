/* global jQuery, tpwGalleryElementor */
(function ($) {
  'use strict';

  function isTPWGalleryWidget(model) {
    try {
      return !!(model && typeof model.get === 'function' && model.get('widgetType') === 'tpw_gallery');
    } catch (e) {
      return false;
    }
  }

  function findGallerySelect($scope) {
    if (!$scope || !$scope.length) return $();

    // Most common: select element carries data-setting.
    var $select = $scope.find('select[data-setting="gallery_id"]');
    if ($select.length) return $select.first();

    // Fallback: control wrapper class.
    $select = $scope.find('.elementor-control-gallery_id select');
    if ($select.length) return $select.first();

    return $();
  }

  function ensureOption($select, id, text) {
    if (!id) return;
    var value = String(id);
    if ($select.find('option[value="' + value.replace(/"/g, '\\"') + '"]').length) {
      return;
    }
    var opt = new Option(text || value, value, true, true);
    $select.append(opt).trigger('change');
  }

  function fetchSelectedLabel($select, id) {
    if (!id) return;
    $.ajax({
      url: tpwGalleryElementor.ajaxurl,
      method: 'GET',
      dataType: 'json',
      data: {
        action: tpwGalleryElementor.action,
        nonce: tpwGalleryElementor.nonce,
        id: id,
      },
    }).done(function (resp) {
      if (!resp || !resp.results || !resp.results.length) return;
      ensureOption($select, resp.results[0].id, resp.results[0].text);
    });
  }

  function initSelect2ForWidget($scope, initialId) {
    if (!tpwGalleryElementor || !tpwGalleryElementor.ajaxurl || !tpwGalleryElementor.nonce) return false;

    var $select = findGallerySelect($scope);
    if (!$select.length) return false;

    // Elementor normally loads Select2 in the editor; bail if missing (allow retries).
    if (typeof $select.select2 !== 'function') return false;

    // Avoid double-initialization.
    if ($select.data('tpwSelect2Init')) return true;

    // If Elementor already initialized Select2 for this control, rebuild with our AJAX.
    if ($select.data('select2')) {
      try {
        $select.select2('destroy');
      } catch (e) {
        // ignore
      }
    }

    $select.select2({
      allowClear: true,
      placeholder: (tpwGalleryElementor.i18n && tpwGalleryElementor.i18n.searchPlaceholder) || '',
      ajax: {
        url: tpwGalleryElementor.ajaxurl,
        dataType: 'json',
        delay: 250,
        data: function (params) {
          return {
            action: tpwGalleryElementor.action,
            nonce: tpwGalleryElementor.nonce,
            term: params.term || '',
            page: params.page || 1,
          };
        },
        processResults: function (data, params) {
          params.page = params.page || 1;

          // If wp_send_json_error is returned, normalize to empty results.
          if (data && data.success === false) {
            return { results: [], pagination: { more: false } };
          }

          return {
            results: data && data.results ? data.results : [],
            pagination: data && data.pagination ? data.pagination : { more: false },
          };
        },
        cache: true,
      },
      minimumInputLength: 1,
      width: '100%',
    });

    // Ensure current selection shows a label.
    var current = $select.val() || (initialId ? String(initialId) : '');
    if (current) {
      fetchSelectedLabel($select, current);
    }

    $select.data('tpwSelect2Init', true);
    return true;
  }

  function bindElementorHooks() {
    if (!(window.elementor && elementor.hooks && typeof elementor.hooks.addAction === 'function')) {
      return;
    }

    // Fires when any widget is opened in the editor panel.
    function scheduleInit(panel, model) {
      if (!isTPWGalleryWidget(model)) return;
      var $root = panel && panel.$el ? panel.$el : $(document);

      var savedId = '';
      try {
        var settings = model && typeof model.get === 'function' ? model.get('settings') : null;
        if (settings && typeof settings.get === 'function') {
          savedId = settings.get('gallery_id') || '';
        }
      } catch (e) {
        savedId = '';
      }

      var tries = 0;
      (function retry() {
        tries++;
        var ok = initSelect2ForWidget($root, savedId);
        if (ok) return;
        if (tries < 15) {
          setTimeout(retry, 100);
        }
      })();
    }

    elementor.hooks.addAction('panel/open_editor/widget', scheduleInit);

    // Some Elementor versions also expose widget-specific hooks.
    elementor.hooks.addAction('panel/open_editor/widget/tpw_gallery', scheduleInit);
  }

  // Bind immediately if Elementor is already available, otherwise wait.
  if (window.elementor && elementor.hooks) {
    bindElementorHooks();
  }
  $(window).on('elementor:init', bindElementorHooks);
})(jQuery);
