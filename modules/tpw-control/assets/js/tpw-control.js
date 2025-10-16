(function($){
    $(function(){
        // Basic JS bootstrap for TPW Control
        $(document).on('click', '.tpw-control__menu a', function(){
            // allow default navigation; this is a hook for later SPA behavior
        });

        // Inline file delete (AJAX)
        $(document).on('click', '.tpw-upl-file-delete', function(e){
            e.preventDefault();
            var $btn = $(this);
            var fileId = $btn.data('file-id');
            var pageId = $btn.data('page-id');
            if (!fileId || !pageId) return;
            if (!confirm('Delete this file?')) return;
            $btn.prop('disabled', true).text('Deleting…');
            var $row = $btn.closest('tr');
            var nonce = $('#tpw-upl-form-files input[name="_wpnonce"]').val();
            var ajaxUrl = window.ajaxurl || (window.TPW_CONTROL && window.TPW_CONTROL.ajax_url) || '/wp-admin/admin-ajax.php';
            $.post({
                url: ajaxUrl,
                data: {
                    action: 'tpw_control_delete_file',
                    file_id: fileId,
                    page_id: pageId,
                    _wpnonce: nonce
                },
                success: function(resp){
                    if (resp && resp.success) {
                        $row.fadeOut(200, function(){ 
                            $row.remove(); 
                            // Refresh Trash panel so it becomes visible without reload
                            try {
                                var ajaxUrl2 = ajaxUrl;
                                $.post({
                                    url: ajaxUrl2,
                                    data: { action: 'tpw_control_get_trash', page_id: pageId, _wpnonce: nonce },
                                    success: function(r){
                                        if (r && r.success && r.data && typeof r.data.html === 'string') {
                                            var $wrap = $('#tpw-upl-trash-section');
                                            if ($wrap.length) {
                                                $wrap.html(r.data.html);
                                            } else if (r.data.html) {
                                                // Fallback: append after files form
                                                var $after = $('#tpw-upl-form-files').closest('fieldset');
                                                var html = '<div id="tpw-upl-trash-section" class="tpw-section" style="margin-top:16px">'+r.data.html+'</div>';
                                                if ($after.length) $after.append(html); else $('body').append(html);
                                            }
                                        }
                                    }
                                });
                            } catch(e) {}
                        });
                    } else {
                        $btn.prop('disabled', false).text('Delete');
                        alert((resp && resp.data && resp.data.message) ? resp.data.message : 'Delete failed.');
                    }
                },
                error: function(jqXHR){
                    $btn.prop('disabled', false).text('Delete');
                    var msg = 'Delete failed.';
                    try {
                        if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                            msg = jqXHR.responseJSON.data.message;
                        } else if (jqXHR && jqXHR.responseText) {
                            // Try to extract any plain text message
                            var m = jqXHR.responseText.match(/\{"success":false,[\s\S]*?"message"\s*:\s*"([^"]+)"/);
                            if (m && m[1]) msg = m[1];
                        }
                    } catch(e) {}
                    alert(msg);
                }
            });
        });

        // Simple modal toggles
        $(document).on('click', '[data-tpw-open]', function(e){
            e.preventDefault();
            var sel = $(this).attr('data-tpw-open');
            if ( sel ) $(sel).show();
        });
        $(document).on('click', '[data-tpw-close]', function(e){
            e.preventDefault();
            var sel = $(this).attr('data-tpw-close');
            if ( sel ) $(sel).hide();
        });
        // Close when clicking overlay outside dialog
        $(document).on('click', '.tpw-modal', function(e){
            if ( e.target === this ) $(this).hide();
        });

        // Generic navigation buttons: elements with data-href should navigate
        $(document).on('click', '.tpw-nav-btn', function(e){
            var href = $(this).attr('data-href');
            if ( href ) {
                window.location.href = href;
            }
        });

        // Add File modal: AJAX upload with progress
        $(document).on('submit', '#tpw-upl-form-add', function(e){
            var $form = $(this);
            // If browser doesn't support FormData/XHR2, let it submit normally
            if (typeof FormData === 'undefined' || !('upload' in $.ajaxSettings.xhr())) return;

            e.preventDefault();
            var $modal = $('#tpw-upl-addfile-modal');
            var $submitBtn = $form.find('button[type="submit"]');
            var $cancelBtn = $form.find('[data-tpw-close="#tpw-upl-addfile-modal"]');
            var $feedback = $('#tpw-upl-upload-feedback');
            var $progress = $('#tpw-upl-progress');
            var $progressText = $('#tpw-upl-progress-text');
            var $error = $('#tpw-upl-upload-error');

            $submitBtn.prop('disabled', true);
            $cancelBtn.prop('disabled', true);
            $error.hide().text('');
            $feedback.show();
            $progress.val(0);
            $progressText.text('Uploading… 0%');

            var formData = new FormData($form[0]);
            formData.set('tpw_ajax', '1');

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function(){
                    var xhr = $.ajaxSettings.xhr();
                    if (xhr.upload) {
                        xhr.upload.addEventListener('progress', function(evt){
                            if (evt.lengthComputable) {
                                var pct = Math.round((evt.loaded / evt.total) * 100);
                                $progress.val(pct);
                                $progressText.text('Uploading… ' + pct + '%');
                            }
                        });
                    }
                    return xhr;
                }
            }).done(function(){
                // On success, reload to show the new files
                window.location.reload();
            }).fail(function(jqXHR){
                var msg = 'Upload failed. Please try again.';
                if (jqXHR && jqXHR.responseText) {
                    try {
                        var m = jqXHR.responseText.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
                        if (m && m[1]) msg = $(m[1]).text().trim().slice(0, 300);
                    } catch (e) {}
                }
                $error.text(msg).show();
            }).always(function(){
                $submitBtn.prop('disabled', false);
                $cancelBtn.prop('disabled', false);
            });
        });

        // Initialize sortable for files list
        var initSortable = function(){
            var $tbody = $('#tpw-upl-files-tbody');
            if (!$tbody.length || !$tbody.sortable) return;
            $tbody.sortable({
                handle: '.tpw-upl-handle',
                axis: 'y',
                update: function(){
                    var ids = [];
                    $tbody.find('tr').each(function(){
                        var id = $(this).data('file-id');
                        if (id) ids.push(id);
                    });
                    var nonce = $('#tpw-upl-form-files input[name="_wpnonce"]').val();
                    var pageId = $('#tpw-upl-form-files input[name="upload_page_id"]').val();
                    var ajaxUrl = window.ajaxurl || (window.TPW_CONTROL && window.TPW_CONTROL.ajax_url) || '/wp-admin/admin-ajax.php';
                    $.post(ajaxUrl, {
                        action: 'tpw_control_sort_files',
                        order: ids,
                        page_id: pageId,
                        _wpnonce: nonce
                    });
                }
            });
        };
        initSortable();

        // Initialize sortable for categories list
        var initCatSortable = function(){
            var $tbody = $('#tpw-upl-cats-tbody');
            if (!$tbody.length || !$tbody.sortable) return;
            $tbody.sortable({
                handle: '.tpw-upl-handle',
                axis: 'y',
                update: function(){
                    var ids = [];
                    $tbody.find('tr').each(function(){
                        var id = $(this).data('cat-id');
                        if (id) ids.push(id);
                    });
                    var nonce = $('#tpw-upl-form-categories input[name="_wpnonce"]').val();
                    var pageId = $('#tpw-upl-form-categories input[name="upload_page_id"]').val();
                    var ajaxUrl = window.ajaxurl || (window.TPW_CONTROL && window.TPW_CONTROL.ajax_url) || '/wp-admin/admin-ajax.php';
                    $.post(ajaxUrl, {
                        action: 'tpw_control_sort_categories',
                        order: ids,
                        page_id: pageId,
                        _wpnonce: nonce
                    });
                }
            });
        };
        initCatSortable();

        // Inline Add Category (from Add File modal)
        $(document).on('submit', '#tpw-upl-form-addcat', function(e){
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            var $err = $('#tpw-upl-addcat-error');
            $err.hide().text('');
            var pageId = $form.find('input[name="upload_page_id"]').val();
            var name = ($form.find('input[name="category_name"]').val() || '').trim();
            if (!name) { $err.text('Please enter a category name.').show(); return; }
            var nonce = $form.find('input[name="_wpnonce"]').val();
            var ajaxUrl = window.ajaxurl || (window.TPW_CONTROL && window.TPW_CONTROL.ajax_url) || '/wp-admin/admin-ajax.php';
            $btn.prop('disabled', true).text('Creating…');
            $.post({
                url: ajaxUrl,
                data: {
                    action: 'tpw_control_add_category',
                    upload_page_id: pageId,
                    category_name: name,
                    _wpnonce: nonce
                }
            }).done(function(resp){
                if (!resp || !resp.success) {
                    var m = (resp && resp.data && resp.data.message) ? resp.data.message : 'Could not create category.';
                    $err.text(m).show();
                    return;
                }
                var cats = resp.data.categories || [];
                // Rebuild options for all category selects on the page
                var buildOptions = function(selectedId){
                    var html = '<option value="">— None —</option>';
                    for (var i=0;i<cats.length;i++){
                        var c = cats[i];
                        var sel = (selectedId && parseInt(selectedId,10) === parseInt(c.id,10)) ? ' selected' : '';
                        html += '<option value="'+c.id+'"'+sel+'>'+c.name+'</option>';
                    }
                    return html;
                };
                // Update Add File modal select and select the newly added one
                var newId = resp.data.new_id;
                $('select[name="upload_category_id"].tpw-upl-cat-select').each(function(){
                    $(this).html(buildOptions(newId));
                });
                // Update per-file selects (preserve each current selection)
                $('select[name^="file_category["]').each(function(){
                    var current = $(this).val();
                    $(this).html(buildOptions(current));
                });
                // Clear and close
                $form.get(0).reset();
                $('[data-tpw-close="#tpw-upl-addcat-modal"]').first().trigger('click');
            }).fail(function(jqXHR){
                var msg = 'Could not create category.';
                if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    msg = jqXHR.responseJSON.data.message;
                }
                $err.text(msg).show();
            }).always(function(){
                $btn.prop('disabled', false).text('Create');
            });
        });

        // If the Add Media button/editor is present on Upload Pages, tag uploads to route into our subfolder
        (function tagMediaUploads(){
            if (!window.tpwUplEditor) return;
            // Media Grid (wp.media) sets window.ajaxurl / plupload defaults; ensure our flag is added for upload requests
            var addFlag = function(url){
                if (!url) return url;
                try {
                    var u = new URL(url, window.location.origin);
                    var isAsync = u.pathname.indexOf('async-upload.php') !== -1;
                    var isAdminAjaxUpload = (u.pathname.indexOf('admin-ajax.php') !== -1) && (u.searchParams.get('action') === 'upload-attachment');
                    if (isAsync || isAdminAjaxUpload) {
                        if (!u.searchParams.has('tpw_upl_editor')) {
                            u.searchParams.set('tpw_upl_editor', '1');
                        }
                        return u.toString();
                    }
                    return url;
                } catch(e){ return url + (url.indexOf('?')===-1?'?':'&') + 'tpw_upl_editor=1'; }
            };
            // Hook jQuery AJAX to append the flag on upload endpoints
            var _ajax = $.ajax;
            $.ajax = function(opts){
                if (opts && typeof opts === 'object' && opts.url) {
                    var url = opts.url.toString();
                    opts.url = addFlag(url);
                }
                return _ajax.apply($, arguments);
            };
        })();

        // Lightweight lightbox/modal for previews (admin and public)
        var ensureLightbox = function(){
            if ($('#tpw-lightbox').length) return;
            var html = '<div id="tpw-lightbox" class="tpw-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.86);z-index:10000;">'
                + '<div class="tpw-modal__dialog" role="dialog" aria-modal="true" style="background:transparent;max-width:90vw;margin:5vh auto;padding:0;">'
                + '<div class="tpw-lightbox-content" style="position:relative;text-align:center">'
                + '<div class="tpw-lightbox-toolbar" style="position:absolute;top:0;left:0;right:0;display:flex;justify-content:flex-end;gap:6px;padding:6px;">'
                + '  <button class="tpw-lightbox-zoom-in tpw-btn tpw-btn-light" title="Zoom in">+</button>'
                + '  <button class="tpw-lightbox-zoom-out tpw-btn tpw-btn-light" title="Zoom out">−</button>'
                + '  <a class="tpw-lightbox-download tpw-btn tpw-btn-primary" href="#" target="_blank" rel="noopener">Download</a>'
                + '  <button class="tpw-lightbox-close tpw-btn tpw-btn-light">Close</button>'
                + '</div>'
                + '<div class="tpw-lightbox-body" style="max-height:80vh;overflow:auto;margin-top:36px"></div>'
                + '<div class="tpw-lightbox-caption" style="color:#fff;margin-top:8px"></div>'
                + '<button class="tpw-lightbox-prev tpw-btn tpw-btn-light" style="position:absolute;left:0;top:50%;transform:translateY(-50%);">‹</button>'
                + '<button class="tpw-lightbox-next tpw-btn tpw-btn-light" style="position:absolute;right:0;top:50%;transform:translateY(-50%);">›</button>'
                + '</div></div></div>';
            $('body').append(html);
        };
        ensureLightbox();

        var currentIndex = -1;
        var currentHref = '';
        var currentScale = 1;
        function showItem(index){
            var $items = $('.tpw-upl-preview');
            if (index < 0 || index >= $items.length) return;
            currentIndex = index;
            var $a = $($items[index]);
            var href = $a.attr('href');
            currentHref = href;
            var type = ($a.data('type') || '').toString();
            var label = ($a.data('label') || '').toString();
            var $body = $('#tpw-lightbox .tpw-lightbox-body').empty();
            $('#tpw-lightbox .tpw-lightbox-caption').text(label);

            if (type.indexOf('image/') === 0) {
                $body.append('<img class="tpw-lightbox-media" src="'+href+'" style="max-width:90vw;max-height:80vh;transform-origin:center center;transform:scale(1)" />');
            } else if (type === 'application/pdf') {
                // Inline PDF (if supported by browser)
                $body.append('<iframe class="tpw-lightbox-media" src="'+href+'" style="width:90vw;height:80vh;border:0;background:#fff;transform-origin:0 0;transform:scale(1)"></iframe>');
            } else if (type.indexOf('video/') === 0 || href.toLowerCase().endsWith('.mp4')) {
                $body.append('<video class="tpw-lightbox-media" src="'+href+'" style="max-width:90vw;max-height:80vh;transform-origin:center center;transform:scale(1)" controls></video>');
            } else {
                // Info modal for Word/Excel and others
                var info = '<div style="background:#fff;color:#000;padding:16px;border-radius:6px;max-width:80vw;display:inline-block">'
                    + '<div style="font-weight:600;margin-bottom:6px">'+label+'</div>'
                    + '<div style="margin-bottom:10px">This file cannot be previewed. You can download it instead.</div>'
                    + '<a href="'+href+'" target="_blank" rel="noopener" class="tpw-btn tpw-btn-primary tpw-lightbox-inline-download">Download</a>'
                    + '</div>';
                $body.append(info);
            }
            // Set download link to signed URL with dl=1
            var dl = href;
            try { var u = new URL(href, window.location.origin); u.searchParams.set('dl','1'); dl = u.toString(); } catch(e) { if (href.indexOf('?') === -1) dl = href + '?dl=1'; else dl = href + '&dl=1'; }
            $('#tpw-lightbox .tpw-lightbox-download').attr('href', dl);
            // Also update inline download link if present
            $body.find('a.tpw-lightbox-inline-download').attr('href', dl);
            currentScale = 1;
            $('#tpw-lightbox').show();
        }

        $(document).on('click', '.tpw-upl-preview', function(e){
            e.preventDefault();
            var idx = parseInt($(this).data('index'), 10);
            if (!isNaN(idx)) showItem(idx);
        });
        $(document).on('click', '.tpw-lightbox-close', function(e){ $('#tpw-lightbox').hide(); });
        $(document).on('click', '.tpw-lightbox-prev', function(e){ showItem(currentIndex - 1); });
        $(document).on('click', '.tpw-lightbox-next', function(e){ showItem(currentIndex + 1); });
        $(document).on('keydown', function(e){ if ($('#tpw-lightbox').is(':visible')) { if (e.key === 'ArrowLeft') showItem(currentIndex-1); else if (e.key === 'ArrowRight') showItem(currentIndex+1); else if (e.key === 'Escape') $('#tpw-lightbox').hide(); }});

        // Zoom controls (applies to media element if present)
        function applyZoom(){
            var $m = $('#tpw-lightbox .tpw-lightbox-media');
            if (!$m.length) return;
            $m.css('transform', 'scale(' + currentScale + ')');
        }
        $(document).on('click', '.tpw-lightbox-zoom-in', function(){ currentScale = Math.min(3, currentScale + 0.1); applyZoom(); });
        $(document).on('click', '.tpw-lightbox-zoom-out', function(){ currentScale = Math.max(0.5, currentScale - 0.1); applyZoom(); });

        // Public: AJAX category filter for Upload Pages
        $(document).on('click', '.tpw-upload-page .tpw-cat-filter', function(e){
            var $a = $(this);
            var $wrap = $a.closest('.tpw-upload-page');
            var pageId = $a.data('page') || $wrap.data('page');
            if (!pageId) return; // no page context; allow default navigation
            e.preventDefault();
            var href = $a.attr('href') || '#';
            var cat = ($a.data('cat') || '').toString();
            var yearVal = ($wrap.attr('data-year') || '').toString();
            var ajaxUrl = window.ajaxurl || (window.TPW_CONTROL && window.TPW_CONTROL.ajax_url) || '/wp-admin/admin-ajax.php';
            var $list = $('#tpw-upload-list-' + pageId);
            $wrap.addClass('tpw-is-loading');
            $wrap.find('.tpw-cat-filter').attr('aria-disabled', 'true');
            $list.css('opacity', 0.6);
            $.post({
                url: ajaxUrl,
                data: {
                    action: 'tpw_control_filter_files',
                    page_id: pageId,
                    cat: cat,
                    tpw_year: yearVal
                }
            }).done(function(resp){
                if (resp && resp.success && resp.data && typeof resp.data.html === 'string') {
                    $list.html(resp.data.html);
                    // Update active styles within this instance only
                    $wrap.find('.tpw-cat-filter').removeClass('tpw-btn-primary').addClass('tpw-btn-secondary');
                    $a.removeClass('tpw-btn-secondary').addClass('tpw-btn-primary');
                } else {
                    // Fallback to hard navigation
                    window.location.href = href;
                }
            }).fail(function(){
                window.location.href = href;
            }).always(function(){
                $wrap.removeClass('tpw-is-loading');
                $wrap.find('.tpw-cat-filter').removeAttr('aria-disabled');
                $list.css('opacity', '');
            });
        });

        // Instance-local year filter: AJAX, no URL changes
        $(document).on('change', '.tpw-upload-page .tpw-filter-year select[name="tpw_year"]', function(){
            var $sel = $(this);
            var $wrap = $sel.closest('.tpw-upload-page');
            var pageId = $wrap.data('page');
            if (!pageId) return;
            var yearVal = ($sel.val() || '').toString();
            // Persist selection on wrapper state (per instance)
            $wrap.attr('data-year', yearVal);
            var ajaxUrl = window.ajaxurl || (window.TPW_CONTROL && window.TPW_CONTROL.ajax_url) || '/wp-admin/admin-ajax.php';
            var $list = $('#tpw-upload-list-' + pageId);
            $wrap.addClass('tpw-is-loading');
            $list.css('opacity', 0.6);
            var cat = '';
            // Preserve current active category button selection (if any)
            var $activeCat = $wrap.find('.tpw-cat-filter.tpw-btn-primary');
            if ($activeCat.length) cat = ($activeCat.data('cat') || '').toString();
            $.post({
                url: ajaxUrl,
                data: {
                    action: 'tpw_control_filter_files',
                    page_id: pageId,
                    cat: cat,
                    tpw_year: yearVal
                }
            }).done(function(resp){
                if (resp && resp.success && resp.data && typeof resp.data.html === 'string') {
                    $list.html(resp.data.html);
                }
            }).always(function(){
                $wrap.removeClass('tpw-is-loading');
                $list.css('opacity', '');
            });
        });

        // Style TinyMCE/Quicktags UI within TPW admin-like screens without new CSS: reuse TPW button classes
        (function styleEditorUI(){
            function forceStyles($el, base) {
                // Apply inline styles with !important to beat global theme rules
                var node = $el.get(0);
                if (!node || !node.style || !node.style.setProperty) return;
                function imp(prop, val){ node.style.setProperty(prop, val, 'important'); }
                imp('text-transform', 'var(--tpw-text-transform, none)');
                imp('font-family', 'var(--tpw-btn-font-family, var(--tpw-font-family, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial))');
                imp('border-radius', 'var(--tpw-btn-radius, 7px)');
                imp('padding', 'var(--tpw-btn-padding, 4px 8px)');
                // Background, color, border — allow per-variant fallbacks
                if (base === 'quicktags' || base === 'tab' || base === 'media') {
                    // Requested: force white background
                    imp('background-color', '#ffffff');
                    imp('color', '#111827');
                    imp('border', '1px solid rgba(0,0,0,0.1)');
                } else if (base === 'secondary') {
                    imp('background-color', 'var(--tpw-btn-secondary-bg, #e5e7eb)');
                    imp('color', 'var(--tpw-btn-secondary-fg, #111827)');
                    imp('border', '1px solid var(--tpw-btn-secondary-border, #cbd5e1)');
                }
            }

            function apply() {
                var $wraps = $('.tpw-admin-ui .wp-editor-wrap');
                if (!$wraps.length) return;
                $wraps.each(function(){
                    var $w = $(this);
                    // Quicktags toolbar buttons (e.g., B, I, link)
                    $w.find('.quicktags-toolbar input.ed_button').each(function(){
                        var $btn = $(this).addClass('tpw-btn tpw-btn-light tpw-btn-sm');
                        forceStyles($btn, 'quicktags');
                    });

                    // Visual/Text tabs
                    $w.find('.wp-editor-tabs .wp-switch-editor').each(function(){
                        var $btn = $(this).addClass('tpw-btn tpw-btn-light tpw-btn-sm');
                        forceStyles($btn, 'tab');
                    });

                    // Add Media button (outside .wp-editor-wrap but nearby)
                    $w.closest('.tpw-admin-ui').find('.wp-media-buttons .button').each(function(){
                        var $btn = $(this).addClass('tpw-btn tpw-btn-secondary tpw-btn-sm');
                        forceStyles($btn, 'media');
                    });
                });
            }
            // Apply now and shortly after to catch late renders
            apply();
            setTimeout(apply, 250);
            // Re-apply when editors initialize or tab is toggled
            $(document).on('tinymce-editor-init quicktags-init', apply);
            $(document).on('click', '.wp-switch-editor', function(){ setTimeout(apply, 0); });
        })();
    });
})(jQuery);
