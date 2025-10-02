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
                        $row.fadeOut(200, function(){ $row.remove(); });
                    } else {
                        $btn.prop('disabled', false).text('Delete');
                        alert((resp && resp.data && resp.data.message) ? resp.data.message : 'Delete failed.');
                    }
                },
                error: function(){
                    $btn.prop('disabled', false).text('Delete');
                    alert('Delete failed.');
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

        // Lightweight lightbox/modal for previews
        var ensureLightbox = function(){
            if ($('#tpw-lightbox').length) return;
            var html = '<div id="tpw-lightbox" class="tpw-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.86);z-index:10000;">'
                + '<div class="tpw-modal__dialog" role="dialog" aria-modal="true" style="background:transparent;max-width:90vw;margin:5vh auto;padding:0;">'
                + '<div class="tpw-lightbox-content" style="position:relative;text-align:center">'
                + '<button class="tpw-lightbox-close tpw-btn tpw-btn-light" style="position:absolute;top:0;right:0;">Close</button>'
                + '<div class="tpw-lightbox-body" style="max-height:80vh;overflow:auto"></div>'
                + '<div class="tpw-lightbox-caption" style="color:#fff;margin-top:8px"></div>'
                + '<button class="tpw-lightbox-prev tpw-btn tpw-btn-light" style="position:absolute;left:0;top:50%;transform:translateY(-50%);">‹</button>'
                + '<button class="tpw-lightbox-next tpw-btn tpw-btn-light" style="position:absolute;right:0;top:50%;transform:translateY(-50%);">›</button>'
                + '</div></div></div>';
            $('body').append(html);
        };
        ensureLightbox();

        var currentIndex = -1;
        function showItem(index){
            var $items = $('.tpw-upl-preview');
            if (index < 0 || index >= $items.length) return;
            currentIndex = index;
            var $a = $($items[index]);
            var href = $a.attr('href');
            var type = ($a.data('type') || '').toString();
            var label = ($a.data('label') || '').toString();
            var $body = $('#tpw-lightbox .tpw-lightbox-body').empty();
            $('#tpw-lightbox .tpw-lightbox-caption').text(label);

            if (type.indexOf('image/') === 0) {
                $body.append('<img src="'+href+'" style="max-width:90vw;max-height:80vh" />');
            } else if (type === 'application/pdf') {
                $body.append('<iframe src="'+href+'" style="width:90vw;height:80vh;border:0;background:#fff"></iframe>');
            } else if (type.indexOf('video/') === 0 || href.toLowerCase().endsWith('.mp4')) {
                $body.append('<video src="'+href+'" style="max-width:90vw;max-height:80vh" controls></video>');
            } else {
                // Info modal for Word/Excel and others
                var info = '<div style="background:#fff;color:#000;padding:16px;border-radius:6px;max-width:80vw;display:inline-block">'
                    + '<div style="font-weight:600;margin-bottom:6px">'+label+'</div>'
                    + '<div style="margin-bottom:10px">This file cannot be previewed. You can download it instead.</div>'
                    + '<a href="'+href+'" target="_blank" rel="noopener" class="tpw-btn tpw-btn-primary">Download</a>'
                    + '</div>';
                $body.append(info);
            }
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
    });
})(jQuery);
