/**
 * POD Dynamic Option Forms & Virtual Variants — Storefront Interactive Engine
 * Handles choice selection, conditional logic, live price updates, URL params, modals & AJAX logo uploads.
 */
(function ($) {
    'use strict';

    function formatMoney(amount, symbol) {
        var sym = symbol || (window.podOptionFormsConfig && window.podOptionFormsConfig.currencySymbol) || '$';
        return sym + Number(amount || 0).toFixed(2);
    }

    function initContainer($container) {
        var basePrice = parseFloat($container.attr('data-base-price')) || 24.99;
        var showSummary = $container.attr('data-show-summary') === '1';
        var currencySymbol = (window.podOptionFormsConfig && window.podOptionFormsConfig.currencySymbol) || '$';

        function getFieldValues() {
            var vals = {};
            $container.find('.pod-of-field').each(function () {
                var $f = $(this);
                var fid = $f.attr('data-field-id');
                var val = $f.find('.pod-of-hidden-val').val() || '';
                vals[fid] = val;
            });
            return vals;
        }

        function evaluateConditions() {
            var vals = getFieldValues();
            $container.find('.pod-of-field[data-cond-field]').each(function () {
                var $f = $(this);
                var targetField = $f.attr('data-cond-field');
                var op = $f.attr('data-cond-op') || 'eq';
                var expected = String($f.attr('data-cond-val') || '');
                var actual = String(vals[targetField] || '');

                var pass = op === 'neq' ? actual !== expected : actual === expected;
                if (pass) {
                    $f.removeClass('is-hidden-by-cond');
                    $f.find('input, select, textarea').prop('disabled', false);
                } else {
                    $f.addClass('is-hidden-by-cond');
                    $f.find('.pod-of-text-input, .pod-of-textarea, .pod-of-select').prop('disabled', true);
                }
            });
        }

        function recalculatePrice() {
            evaluateConditions();

            var effectiveBase = basePrice;
            var addTotal = 0;
            var payload = [];

            $container.find('.pod-of-field').each(function () {
                var $f = $(this);
                if ($f.hasClass('is-hidden-by-cond')) {
                    return;
                }
                var fid = $f.attr('data-field-id');
                var ftype = $f.attr('data-field-type');
                var flabel = $f.attr('data-field-label') || fid;

                if (ftype === 'button_group' || ftype === 'visual_card_group' || ftype === 'color_swatch_group') {
                    var $sel = $f.find('.pod-of-choice.is-selected').first();
                    if ($sel.length) {
                        var pmode = $sel.attr('data-price-mode') || 'none';
                        var pamt = parseFloat($sel.attr('data-price')) || 0;
                        if (pmode === 'override_base' && pamt > 0) {
                            effectiveBase = pamt;
                        } else if (pmode === 'add_fixed') {
                            addTotal += pamt;
                        } else if (pmode === 'add_percent') {
                            addTotal += Math.round(effectiveBase * (pamt / 100) * 100) / 100;
                        }
                        payload.push({ id: fid, label: flabel, value: $sel.attr('data-opt-label'), price: pamt });
                    }
                } else if (ftype === 'dropdown') {
                    var $opt = $f.find('.pod-of-select option:selected');
                    if ($opt.length && $opt.val()) {
                        var dMode = $opt.attr('data-price-mode') || 'none';
                        var dAmt = parseFloat($opt.attr('data-price')) || 0;
                        if (dMode === 'override_base' && dAmt > 0) {
                            effectiveBase = dAmt;
                        } else if (dMode === 'add_fixed') {
                            addTotal += dAmt;
                        } else if (dMode === 'add_percent') {
                            addTotal += Math.round(effectiveBase * (dAmt / 100) * 100) / 100;
                        }
                        payload.push({ id: fid, label: flabel, value: $opt.attr('data-opt-label') || $opt.text(), price: dAmt });
                    }
                } else {
                    var hiddenVal = $f.find('.pod-of-hidden-val').val() || '';
                    if (hiddenVal !== '') {
                        var $inp = $f.find('.pod-of-text-input, .pod-of-textarea').first();
                        var tMode = $inp.attr('data-price-mode') || 'none';
                        var tAmt = parseFloat($inp.attr('data-price')) || 0;
                        if (tMode === 'add_fixed') {
                            addTotal += tAmt;
                        }
                        payload.push({ id: fid, label: flabel, value: hiddenVal, price: tAmt });
                    }
                }
            });

            var finalPrice = Math.max(0.01, Math.round((effectiveBase + addTotal) * 100) / 100);
            var formattedFinal = formatMoney(finalPrice, currencySymbol);

            // Update option form price bar
            $container.find('.pod-of-live-price-amount').text(formattedFinal);

            // Also sync WooCommerce product summary price element on page so both always match
            $('.summary .price .woocommerce-Price-amount bdi, .entry-summary .price .woocommerce-Price-amount bdi, .product-page-price .woocommerce-Price-amount bdi')
                .first()
                .text(formattedFinal);

            // Update "Selections will add $X.XX to the base price" box
            var $summaryBox = $container.find('.pod-of-addon-summary');
            if ((showSummary || addTotal > 0) && addTotal > 0) {
                $summaryBox.find('.pod-of-addon-amount').text(formatMoney(addTotal, currencySymbol));
                $summaryBox.show();
            } else {
                $summaryBox.hide();
            }

            $container.find('.pod-of-payload-json').val(JSON.stringify(payload));
        }

        // Choice button clicks (Button Group, Visual Card Group, Color Swatch Group)
        $container.on('click', '.pod-of-choice', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var $field = $btn.closest('.pod-of-field');
            $field.find('.pod-of-choice').removeClass('is-selected');
            $btn.addClass('is-selected');

            var optId = $btn.attr('data-opt-id') || '';
            var optLabel = $btn.attr('data-opt-label') || '';
            var optPrice = parseFloat($btn.attr('data-price')) || 0;

            $field.find('.pod-of-hidden-val').val(optId);
            $field.find('.pod-of-selected-color-name').text(optLabel.toUpperCase());
            $field.find('.pod-of-selected-value-name').text(optLabel);
            $field.find('.pod-of-selected-upcharge').text(optPrice > 0 ? '(+' + formatMoney(optPrice, currencySymbol) + ')' : '');

            recalculatePrice();
        });

        // Dropdown change
        $container.on('change', '.pod-of-select', function () {
            var $sel = $(this);
            var $field = $sel.closest('.pod-of-field');
            $field.find('.pod-of-hidden-val').val($sel.val() || '');
            recalculatePrice();
        });

        // Text / Number / Textarea input
        $container.on('input change', '.pod-of-text-input, .pod-of-textarea', function () {
            var $inp = $(this);
            var $field = $inp.closest('.pod-of-field');
            $field.find('.pod-of-hidden-val').val($inp.val() || '');
            recalculatePrice();
        });

        // AJAX File Upload for Custom Logo (`image_upload`)
        $container.on('change', '.pod-of-file-input', function () {
            var fileInput = this;
            if (!fileInput.files || !fileInput.files[0]) {
                return;
            }
            var file = fileInput.files[0];
            var $field = $(fileInput).closest('.pod-of-field');
            var $btnSpan = $field.find('.pod-of-upload-btn span');
            var origText = $btnSpan.text();

            var formData = new FormData();
            formData.append('action', 'pod_of_upload_file');
            formData.append('nonce', (window.podOptionFormsConfig && window.podOptionFormsConfig.uploadNonce) || '');
            formData.append('file', file);

            $btnSpan.text('Uploading...');
            $.ajax({
                url: (window.podOptionFormsConfig && window.podOptionFormsConfig.ajaxUrl) || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    $btnSpan.text(origText);
                    if (res && res.success && res.data && res.data.url) {
                        $field.find('.pod-of-hidden-val').val(res.data.url);
                        $field.find('.pod-of-upload-preview img').attr('src', res.data.url);
                        $field.find('.pod-of-upload-filename').text(res.data.filename || file.name);
                        $field.find('.pod-of-upload-preview').show();
                        recalculatePrice();
                    } else {
                        alert((res && res.data && res.data.message) || 'Upload failed. Please try another image.');
                    }
                },
                error: function () {
                    $btnSpan.text(origText);
                    alert('Upload failed due to a network error.');
                }
            });
        });

        $container.on('click', '.pod-of-upload-clear', function (e) {
            e.preventDefault();
            var $field = $(this).closest('.pod-of-field');
            $field.find('.pod-of-hidden-val').val('');
            $field.find('.pod-of-file-input').val('');
            $field.find('.pod-of-upload-preview').hide();
            recalculatePrice();
        });

        // Helper Modal Popups (Size Guide / Comparing Methods)
        $container.on('click', '[data-popup-target]', function (e) {
            e.preventDefault();
            var targetId = $(this).attr('data-popup-target');
            var $modal = $('#' + targetId);
            if ($modal.length) {
                $modal.fadeIn(140);
            }
        });

        $container.on('click', '.pod-of-modal-close', function (e) {
            e.preventDefault();
            $(this).closest('.pod-of-modal-backdrop').fadeOut(120);
        });

        $container.on('click', '.pod-of-modal-backdrop', function (e) {
            if ($(e.target).hasClass('pod-of-modal-backdrop')) {
                $(this).fadeOut(120);
            }
        });

        // Pre-select options from URL query parameters (?pod-style=unisex-hoodie&pod-size=2xl&pod-color=forest-green)
        try {
            var params = new URLSearchParams(window.location.search);
            var roleMap = {
                'pod-fit': 'fit',
                'pod-style': 'style',
                'pod-size': 'size',
                'pod-color': 'color'
            };
            Object.keys(roleMap).forEach(function (qKey) {
                var qVal = params.get(qKey);
                if (!qVal) return;
                var role = roleMap[qKey];
                var $roleField = $container.find('.pod-of-field[data-semantic-role="' + role + '"]');
                if ($roleField.length) {
                    var $matchBtn = $roleField.find('.pod-of-choice[data-opt-id="' + qVal.toLowerCase() + '"]');
                    if ($matchBtn.length) {
                        $matchBtn.trigger('click');
                    }
                }
            });
        } catch (_err) {
            // Ignore URL parse errors on older browsers
        }

        recalculatePrice();
    }

    $(function () {
        $('.pod-of-container').each(function () {
            initContainer($(this));
        });
    });
})(jQuery);
