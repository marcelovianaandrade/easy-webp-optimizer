/* Easy WebP Optimizer — Admin JS */

(function ($) {
    'use strict';

    var state = {
        queue: [], total: 0, done: 0,
        converted: 0, skipped: 0, errors: 0,
        saved: 0, running: false
    };

    var $btn, $progress, $bar, $status, $log, $toggle, $msg;

    $(document).ready(function () {
        $btn      = $('#easy-webp-start');
        $progress = $('#easy-webp-progress');
        $bar      = $('.easy-webp-bar-fill');
        $status   = $('.easy-webp-status');
        $log      = $('.easy-webp-log');
        $toggle   = $('#easy-webp-delivery-toggle');
        $msg      = $('#easy-webp-delivery-msg');

        $btn.on('click', startProcess);
        $toggle.on('change', toggleDelivery);
    });

    /* ============================================================
     *  BULK CONVERSION
     * ============================================================ */

    function startProcess() {
        if (state.running) return;
        if (!confirm(easyWebP.i18n.confirmStart)) return;

        state = {
            queue: [], total: 0, done: 0,
            converted: 0, skipped: 0, errors: 0,
            saved: 0, running: true
        };

        $btn.prop('disabled', true);
        $progress.show();
        $bar.css('width', '0%');
        $log.empty();
        updateStats();
        setStatus(easyWebP.i18n.scanning);

        $.post(easyWebP.ajaxUrl, {
            action: 'easy_webp_get_queue',
            nonce: easyWebP.nonce
        })
            .done(function (response) {
                if (!response.success) {
                    setStatus(easyWebP.i18n.error + ' ' + (response.data && response.data.message ? response.data.message : ''));
                    state.running = false;
                    $btn.prop('disabled', false);
                    return;
                }

                state.queue = response.data.ids;
                state.total = response.data.total;

                if (state.total === 0) {
                    setStatus(easyWebP.i18n.noImages);
                    state.running = false;
                    $btn.prop('disabled', false);
                    return;
                }

                processNextBatch();
            })
            .fail(function () {
                setStatus(easyWebP.i18n.error);
                state.running = false;
                $btn.prop('disabled', false);
            });
    }

    function processNextBatch() {
        if (state.queue.length === 0) {
            finish();
            return;
        }

        var batch = state.queue.splice(0, easyWebP.batchSize);

        setStatus(easyWebP.i18n.processing + ' ' + (state.done + 1) + ' ' + easyWebP.i18n.of + ' ' + state.total);

        $.post(easyWebP.ajaxUrl, {
            action: 'easy_webp_process_batch',
            nonce: easyWebP.nonce,
            ids: batch
        })
            .done(function (response) {
                if (!response.success) {
                    state.errors += batch.length;
                    state.done += batch.length;
                    logEntry('error', 'Batch error: ' + (response.data && response.data.message ? response.data.message : 'unknown'));
                } else {
                    response.data.results.forEach(function (r) {
                        state.done++;
                        if (r.status === 'converted') {
                            state.converted++;
                            state.saved += r.saved || 0;
                            logEntry('converted', '\u2713 #' + r.id + ' ' + escapeHtml(r.title) + ' \u2014 ' + r.message);
                        } else if (r.status === 'skipped') {
                            state.skipped++;
                            logEntry('skipped', '\u2298 #' + r.id + ' ' + escapeHtml(r.title) + ' \u2014 ' + r.message);
                        } else {
                            state.errors++;
                            logEntry('error', '\u2717 #' + r.id + ' ' + escapeHtml(r.title) + ' \u2014 ' + r.message);
                        }
                    });
                }

                updateProgress();
                updateStats();
                processNextBatch();
            })
            .fail(function () {
                state.errors += batch.length;
                state.done += batch.length;
                logEntry('error', 'Server communication failure.');
                updateProgress();
                updateStats();
                processNextBatch();
            });
    }

    function finish() {
        setStatus(easyWebP.i18n.done);
        $bar.css('width', '100%');
        state.running = false;
        $btn.prop('disabled', false);
    }

    /* ============================================================
     *  DELIVERY TOGGLE
     * ============================================================ */

    function toggleDelivery() {
        var enabled = $toggle.is(':checked');

        // Strong confirmation when ENABLING (modifies .htaccess)
        if (enabled) {
            if (!confirm(easyWebP.i18n.confirmEnable)) {
                $toggle.prop('checked', false);
                return;
            }
        }

        $toggle.prop('disabled', true);

        $.post(easyWebP.ajaxUrl, {
            action: 'easy_webp_toggle_delivery',
            nonce: easyWebP.nonce,
            enable: enabled ? '1' : '0'
        })
            .done(function (response) {
                if (!response.success) {
                    showMsg('error', easyWebP.i18n.deliveryError);
                    $toggle.prop('checked', !enabled);
                } else {
                    var msg = enabled ? easyWebP.i18n.deliveryOn : easyWebP.i18n.deliveryOff;
                    if (response.data.htaccess) {
                        msg += ' (.htaccess: ' + response.data.htaccess + ')';
                    }
                    showMsg(enabled ? 'success' : 'warning', msg);
                    updateToggleLabel(enabled);
                }
            })
            .fail(function () {
                showMsg('error', easyWebP.i18n.deliveryError);
                $toggle.prop('checked', !enabled);
            })
            .always(function () {
                $toggle.prop('disabled', false);
            });
    }

    function updateToggleLabel(enabled) {
        var $label = $('.easy-webp-toggle-label');
        if (enabled) {
            $label.html('<strong style="color:#2e7d32">Delivery ENABLED</strong>');
        } else {
            $label.html('<strong style="color:#888">Delivery DISABLED</strong>');
        }
    }

    function showMsg(type, text) {
        $msg.removeClass('success warning error').addClass(type).text(text).fadeIn();
    }

    /* ============================================================
     *  HELPERS
     * ============================================================ */

    function updateProgress() {
        var pct = state.total > 0 ? Math.round((state.done / state.total) * 100) : 0;
        $bar.css('width', pct + '%');
    }

    function updateStats() {
        $('.easy-webp-done').text(state.done + ' / ' + state.total);
        $('.easy-webp-converted').text(state.converted);
        $('.easy-webp-skipped').text(state.skipped);
        $('.easy-webp-errors').text(state.errors);
        $('.easy-webp-saved').text(formatBytes(state.saved));
    }

    function setStatus(text) { $status.text(text); }

    function logEntry(type, message) {
        var $entry = $('<div class="log-' + type + '"></div>').text(message);
        $log.append($entry);
        $log.scrollTop($log[0].scrollHeight);
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 KB';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})(jQuery);
