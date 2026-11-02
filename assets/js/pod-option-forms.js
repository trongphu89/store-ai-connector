/**
 * POD Dynamic Option Forms & Virtual Variants — Storefront Interactive Engine (Pure Vanilla JS)
 * Zero dependency on jQuery so deferred/dequeued theme scripts never break variant selection.
 * Handles choice selection, conditional logic, live price updates, URL params, modals & AJAX logo uploads.
 */
(function () {
    'use strict';

    function getCurrencySymbol() {
        return (window.podOptionFormsConfig && window.podOptionFormsConfig.currencySymbol) || '$';
    }

    function formatMoney(amount, symbol) {
        var sym = symbol || getCurrencySymbol();
        return sym + Number(amount || 0).toFixed(2);
    }

    function initContainer(container) {
        if (!container || container.getAttribute('data-pod-of-ready') === '1') {
            return;
        }
        container.setAttribute('data-pod-of-ready', '1');

        var basePrice = parseFloat(container.getAttribute('data-base-price')) || 24.99;
        var showSummary = container.getAttribute('data-show-summary') === '1';
        var currencySymbol = getCurrencySymbol();

        function getFieldValues() {
            var vals = {};
            var fields = container.querySelectorAll('.pod-of-field');
            for (var i = 0; i < fields.length; i++) {
                var f = fields[i];
                var fid = f.getAttribute('data-field-id');
                var hidden = f.querySelector('.pod-of-hidden-val');
                vals[fid] = hidden ? hidden.value || '' : '';
            }
            return vals;
        }

        function evaluateConditions() {
            var vals = getFieldValues();
            var condFields = container.querySelectorAll('.pod-of-field[data-cond-field]');
            for (var i = 0; i < condFields.length; i++) {
                var f = condFields[i];
                var targetField = f.getAttribute('data-cond-field');
                var op = f.getAttribute('data-cond-op') || 'eq';
                var expected = String(f.getAttribute('data-cond-val') || '');
                var actual = String(vals[targetField] || '');
                var pass = op === 'neq' ? actual !== expected : actual === expected;

                if (pass) {
                    f.classList.remove('is-hidden-by-cond');
                    var inputsOn = f.querySelectorAll('input, select, textarea');
                    for (var j = 0; j < inputsOn.length; j++) {
                        inputsOn[j].disabled = false;
                    }
                } else {
                    f.classList.add('is-hidden-by-cond');
                    var inputsOff = f.querySelectorAll('.pod-of-text-input, .pod-of-textarea, .pod-of-select');
                    for (var k = 0; k < inputsOff.length; k++) {
                        inputsOff[k].disabled = true;
                    }
                }
            }
        }

        function recalculatePrice() {
            evaluateConditions();

            var effectiveBase = basePrice;
            var addTotal = 0;
            var payload = [];
            var fields = container.querySelectorAll('.pod-of-field');

            for (var i = 0; i < fields.length; i++) {
                var f = fields[i];
                if (f.classList.contains('is-hidden-by-cond')) {
                    continue;
                }
                var fid = f.getAttribute('data-field-id');
                var ftype = f.getAttribute('data-field-type');
                var flabel = f.getAttribute('data-field-label') || fid;

                if (ftype === 'button_group' || ftype === 'visual_card_group' || ftype === 'color_swatch_group') {
                    var sel = f.querySelector('.pod-of-choice.is-selected');
                    if (sel) {
                        var pmode = sel.getAttribute('data-price-mode') || 'none';
                        var pamt = parseFloat(sel.getAttribute('data-price')) || 0;
                        if (pmode === 'override_base' && pamt > 0) {
                            effectiveBase = pamt;
                        } else if (pmode === 'add_fixed') {
                            addTotal += pamt;
                        } else if (pmode === 'add_percent') {
                            addTotal += Math.round(effectiveBase * (pamt / 100) * 100) / 100;
                        }
                        payload.push({
                            id: fid,
                            label: flabel,
                            value: sel.getAttribute('data-opt-label') || '',
                            price: pamt
                        });
                    }
                } else if (ftype === 'dropdown') {
                    var selectEl = f.querySelector('.pod-of-select');
                    var opt = selectEl && selectEl.selectedIndex >= 0 ? selectEl.options[selectEl.selectedIndex] : null;
                    if (opt && opt.value) {
                        var dMode = opt.getAttribute('data-price-mode') || 'none';
                        var dAmt = parseFloat(opt.getAttribute('data-price')) || 0;
                        if (dMode === 'override_base' && dAmt > 0) {
                            effectiveBase = dAmt;
                        } else if (dMode === 'add_fixed') {
                            addTotal += dAmt;
                        } else if (dMode === 'add_percent') {
                            addTotal += Math.round(effectiveBase * (dAmt / 100) * 100) / 100;
                        }
                        payload.push({
                            id: fid,
                            label: flabel,
                            value: opt.getAttribute('data-opt-label') || opt.textContent.trim(),
                            price: dAmt
                        });
                    }
                } else {
                    var hiddenEl = f.querySelector('.pod-of-hidden-val');
                    var hiddenVal = hiddenEl ? hiddenEl.value || '' : '';
                    if (hiddenVal !== '') {
                        var inp = f.querySelector('.pod-of-text-input, .pod-of-textarea');
                        var tMode = inp ? inp.getAttribute('data-price-mode') || 'none' : 'none';
                        var tAmt = inp ? parseFloat(inp.getAttribute('data-price')) || 0 : 0;
                        if (tMode === 'add_fixed') {
                            addTotal += tAmt;
                        }
                        payload.push({
                            id: fid,
                            label: flabel,
                            value: hiddenVal,
                            price: tAmt
                        });
                    }
                }
            }

            var finalPrice = Math.max(0.01, Math.round((effectiveBase + addTotal) * 100) / 100);
            var formattedFinal = formatMoney(finalPrice, currencySymbol);

            var liveAmtEl = container.querySelector('.pod-of-live-price-amount');
            if (liveAmtEl) {
                liveAmtEl.textContent = formattedFinal;
            }

            var summaryPriceEl = document.querySelector(
                '.summary .price .woocommerce-Price-amount bdi, .entry-summary .price .woocommerce-Price-amount bdi, .product-page-price .woocommerce-Price-amount bdi'
            );
            if (summaryPriceEl) {
                summaryPriceEl.textContent = formattedFinal;
            }

            var summaryBox = container.querySelector('.pod-of-addon-summary');
            if (summaryBox) {
                if ((showSummary || addTotal > 0) && addTotal > 0) {
                    var addonAmtEl = summaryBox.querySelector('.pod-of-addon-amount');
                    if (addonAmtEl) {
                        addonAmtEl.textContent = formatMoney(addTotal, currencySymbol);
                    }
                    summaryBox.style.display = '';
                } else {
                    summaryBox.style.display = 'none';
                }
            }

            var payloadInput = container.querySelector('.pod-of-payload-json');
            if (payloadInput) {
                payloadInput.value = JSON.stringify(payload);
            }
        }

        function selectChoiceButton(btn) {
            var field = btn.closest('.pod-of-field');
            if (!field) return;

            var siblings = field.querySelectorAll('.pod-of-choice');
            for (var i = 0; i < siblings.length; i++) {
                siblings[i].classList.remove('is-selected');
            }
            btn.classList.add('is-selected');

            var optId = btn.getAttribute('data-opt-id') || '';
            var optLabel = btn.getAttribute('data-opt-label') || '';
            var optPrice = parseFloat(btn.getAttribute('data-price')) || 0;

            var hidden = field.querySelector('.pod-of-hidden-val');
            if (hidden) hidden.value = optId;

            var colorNameEl = field.querySelector('.pod-of-selected-color-name');
            if (colorNameEl) colorNameEl.textContent = optLabel.toUpperCase();

            var valNameEl = field.querySelector('.pod-of-selected-value-name');
            if (valNameEl) valNameEl.textContent = optLabel;

            var upchargeEl = field.querySelector('.pod-of-selected-upcharge');
            if (upchargeEl) {
                upchargeEl.textContent = optPrice > 0 ? '(+' + formatMoney(optPrice, currencySymbol) + ')' : '';
            }

            recalculatePrice();
        }

        // Delegated click handler inside container
        container.addEventListener('click', function (e) {
            var target = e.target;
            if (!target || !target.closest) return;

            var choiceBtn = target.closest('.pod-of-choice');
            if (choiceBtn && container.contains(choiceBtn)) {
                e.preventDefault();
                selectChoiceButton(choiceBtn);
                return;
            }

            var popupTrigger = target.closest('[data-popup-target]');
            if (popupTrigger && container.contains(popupTrigger)) {
                e.preventDefault();
                var targetId = popupTrigger.getAttribute('data-popup-target');
                var modal = targetId ? document.getElementById(targetId) : null;
                if (modal) {
                    modal.style.display = 'flex';
                }
                return;
            }

            var closeBtn = target.closest('.pod-of-modal-close');
            if (closeBtn) {
                e.preventDefault();
                var backdrop = closeBtn.closest('.pod-of-modal-backdrop');
                if (backdrop) backdrop.style.display = 'none';
                return;
            }

            if (target.classList && target.classList.contains('pod-of-modal-backdrop')) {
                target.style.display = 'none';
                return;
            }

            var clearBtn = target.closest('.pod-of-upload-clear');
            if (clearBtn && container.contains(clearBtn)) {
                e.preventDefault();
                var upField = clearBtn.closest('.pod-of-field');
                if (upField) {
                    var hid = upField.querySelector('.pod-of-hidden-val');
                    var fInp = upField.querySelector('.pod-of-file-input');
                    var prev = upField.querySelector('.pod-of-upload-preview');
                    if (hid) hid.value = '';
                    if (fInp) fInp.value = '';
                    if (prev) prev.style.display = 'none';
                    recalculatePrice();
                }
            }
        });

        // Change handler (dropdown + file upload + text change)
        container.addEventListener('change', function (e) {
            var target = e.target;
            if (!target) return;

            if (target.classList.contains('pod-of-select')) {
                var selField = target.closest('.pod-of-field');
                var selHidden = selField ? selField.querySelector('.pod-of-hidden-val') : null;
                if (selHidden) selHidden.value = target.value || '';
                recalculatePrice();
                return;
            }

            if (target.classList.contains('pod-of-text-input') || target.classList.contains('pod-of-textarea')) {
                var txtField = target.closest('.pod-of-field');
                var txtHidden = txtField ? txtField.querySelector('.pod-of-hidden-val') : null;
                if (txtHidden) txtHidden.value = target.value || '';
                recalculatePrice();
                return;
            }

            if (target.classList.contains('pod-of-file-input')) {
                if (!target.files || !target.files[0]) return;
                var file = target.files[0];
                var field = target.closest('.pod-of-field');
                if (!field) return;
                var btnSpan = field.querySelector('.pod-of-upload-btn span');
                var origText = btnSpan ? btnSpan.textContent : 'Upload Image';

                var formData = new FormData();
                formData.append('action', 'pod_of_upload_file');
                formData.append(
                    'nonce',
                    (window.podOptionFormsConfig && window.podOptionFormsConfig.uploadNonce) || ''
                );
                formData.append('file', file);

                if (btnSpan) btnSpan.textContent = 'Uploading...';
                var ajaxUrl =
                    (window.podOptionFormsConfig && window.podOptionFormsConfig.ajaxUrl) ||
                    '/wp-admin/admin-ajax.php';

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (res) {
                        if (btnSpan) btnSpan.textContent = origText;
                        if (res && res.success && res.data && res.data.url) {
                            var hid = field.querySelector('.pod-of-hidden-val');
                            var prev = field.querySelector('.pod-of-upload-preview');
                            var prevImg = prev ? prev.querySelector('img') : null;
                            var fnameEl = field.querySelector('.pod-of-upload-filename');
                            if (hid) hid.value = res.data.url;
                            if (prevImg) prevImg.setAttribute('src', res.data.url);
                            if (fnameEl) fnameEl.textContent = res.data.filename || file.name;
                            if (prev) prev.style.display = '';
                            recalculatePrice();
                        } else {
                            window.alert(
                                (res && res.data && res.data.message) ||
                                    'Upload failed. Please try another image.'
                            );
                        }
                    })
                    .catch(function () {
                        if (btnSpan) btnSpan.textContent = origText;
                        window.alert('Upload failed due to a network error.');
                    });
            }
        });

        // Live text input handler
        container.addEventListener('input', function (e) {
            var target = e.target;
            if (
                target &&
                (target.classList.contains('pod-of-text-input') || target.classList.contains('pod-of-textarea'))
            ) {
                var txtField = target.closest('.pod-of-field');
                var txtHidden = txtField ? txtField.querySelector('.pod-of-hidden-val') : null;
                if (txtHidden) txtHidden.value = target.value || '';
                recalculatePrice();
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
                var roleField = container.querySelector('.pod-of-field[data-semantic-role="' + role + '"]');
                if (roleField) {
                    var matchBtn = roleField.querySelector(
                        '.pod-of-choice[data-opt-id="' + qVal.toLowerCase() + '"]'
                    );
                    if (matchBtn) {
                        selectChoiceButton(matchBtn);
                    }
                }
            });
        } catch (_err) {
            // Ignore URL parse errors on older browsers
        }

        recalculatePrice();
    }

    function bootAllContainers() {
        var containers = document.querySelectorAll('.pod-of-container');
        for (var i = 0; i < containers.length; i++) {
            initContainer(containers[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootAllContainers);
    } else {
        bootAllContainers();
    }
})();
