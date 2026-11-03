/**
 * POD Custom Fields - Frontend JavaScript (Pure Vanilla JS)
 * Zero dependency on jQuery so deferred/dequeued theme scripts never break file uploads or optional toggles.
 */
(function () {
    'use strict';

    function notifyPersonalizationChanged() {
        try {
            document.dispatchEvent(new CustomEvent('pod_custom_fields_updated'));
        } catch (_e) {
            // Ignore older browser CustomEvent constructor fallback
        }

        // Standalone live price update when no Virtual Option Form (.pod-of-container) is mounted
        if (document.querySelector('.pod-of-container')) return;
        var cfBox = document.querySelector('.pod-custom-fields-container[data-enable-fee="true"]');
        if (!cfBox) return;
        var extraFee = parseFloat(cfBox.getAttribute('data-extra-fee')) || 0;
        if (extraFee <= 0) return;

        var priceEls = document.querySelectorAll('.summary .price .woocommerce-Price-amount bdi, .product .summary p.price .woocommerce-Price-amount bdi, .wp-block-woocommerce-product-price .woocommerce-Price-amount bdi');
        if (!priceEls.length) return;

        var isOptional = cfBox.getAttribute('data-optional') === 'true';
        var isActive = false;
        if (isOptional) {
            var chk = cfBox.querySelector('#pod_enable_personalization');
            isActive = !!(chk && chk.checked);
        } else {
            var fields = cfBox.querySelectorAll('.pod-custom-fields-form input:not([type="file"]):not([type="hidden"]), .pod-custom-fields-form textarea, .pod-custom-fields-form select, .pod-custom-fields-form .pod-file-path');
            for (var f = 0; f < fields.length; f++) {
                if (fields[f].value && String(fields[f].value).trim() !== '') {
                    isActive = true;
                    break;
                }
            }
        }

        for (var p = 0; p < priceEls.length; p++) {
            var el = priceEls[p];
            if (el.closest('del')) continue;
            var baseAttr = el.getAttribute('data-pod-base-price');
            var symAttr = el.getAttribute('data-pod-currency-sym');
            if (!baseAttr) {
                var rawTxt = (el.textContent || '').trim();
                var m = rawTxt.match(/^([^\d]*)([\d,.]+)/);
                if (!m) continue;
                symAttr = m[1] || '$';
                baseAttr = String(parseFloat(m[2].replace(/,/g, '')) || 0);
                el.setAttribute('data-pod-base-price', baseAttr);
                el.setAttribute('data-pod-currency-sym', symAttr);
            }
            var baseNum = parseFloat(baseAttr) || 0;
            if (baseNum <= 0) continue;
            var nextPrice = baseNum + (isActive ? extraFee : 0);
            el.textContent = (symAttr || '$') + nextPrice.toFixed(2);
        }
    }

    function initCustomFields() {
        // Optional personalization checkbox toggle
        var optContainers = document.querySelectorAll('.pod-custom-fields-container[data-optional="true"]');
        for (var c = 0; c < optContainers.length; c++) {
            (function (container) {
                if (container.getAttribute('data-pod-cf-ready') === '1') return;
                container.setAttribute('data-pod-cf-ready', '1');

                var checkbox = container.querySelector('#pod_enable_personalization');
                var wrapper = container.querySelector('.pod-custom-fields-wrapper');
                if (!checkbox || !wrapper) return;

                var setEnabled = function (enabled) {
                    wrapper.style.display = enabled ? 'block' : 'none';
                    var inputs = wrapper.querySelectorAll('input, select, textarea');
                    for (var i = 0; i < inputs.length; i++) {
                        inputs[i].disabled = !enabled;
                        if (!enabled && inputs[i].type !== 'file') {
                            inputs[i].value = '';
                        }
                    }
                    if (!enabled) {
                        var previews = wrapper.querySelectorAll('.pod-file-preview');
                        var dropzones = wrapper.querySelectorAll('.pod-file-dropzone');
                        var paths = wrapper.querySelectorAll('.pod-file-path, .pod-file-orig');
                        for (var p = 0; p < previews.length; p++) previews[p].style.display = 'none';
                        for (var d = 0; d < dropzones.length; d++) dropzones[d].style.display = '';
                        for (var h = 0; h < paths.length; h++) paths[h].value = '';
                    }
                };

                setEnabled(checkbox.checked);
                checkbox.addEventListener('change', function () {
                    setEnabled(checkbox.checked);
                    notifyPersonalizationChanged();
                });
            })(optContainers[c]);
        }

        // Bind live input/change events on all personalization fields for live fee updates
        var allCfContainers = document.querySelectorAll('.pod-custom-fields-container');
        for (var k = 0; k < allCfContainers.length; k++) {
            (function (cfEl) {
                if (cfEl.getAttribute('data-pod-cf-live-bound') === '1') return;
                cfEl.setAttribute('data-pod-cf-live-bound', '1');
                cfEl.addEventListener('input', notifyPersonalizationChanged);
                cfEl.addEventListener('change', notifyPersonalizationChanged);
            })(allCfContainers[k]);
        }

        // Upload areas
        var areas = document.querySelectorAll('.pod-file-upload-area');
        for (var i = 0; i < areas.length; i++) {
            (function (area) {
                if (area.getAttribute('data-pod-upload-ready') === '1') return;
                area.setAttribute('data-pod-upload-ready', '1');

                var input = area.querySelector('.pod-file-input');
                var dropzone = area.querySelector('.pod-file-dropzone');
                var preview = area.querySelector('.pod-file-preview');
                var uploading = area.querySelector('.pod-file-uploading');
                var hiddenInput = area.querySelector('.pod-file-path');
                var origInput = area.querySelector('.pod-file-orig');
                var previewImg = area.querySelector('.pod-file-preview-img');
                var fileName = area.querySelector('.pod-file-name');
                var removeBtn = area.querySelector('.pod-file-remove');

                function uploadFile(file) {
                    if (!file) return;
                    var maxSize = parseInt((input && input.getAttribute('data-max-size')) || '5', 10) || 5;
                    if (file.size > maxSize * 1024 * 1024) {
                        window.alert('File too large. Maximum size: ' + maxSize + 'MB');
                        if (input) input.value = '';
                        return;
                    }

                    if (dropzone) dropzone.style.display = 'none';
                    if (preview) preview.style.display = 'none';
                    if (uploading) uploading.style.display = 'flex';

                    var nonceEl = document.getElementById('pod_custom_fields_nonce');
                    var formData = new FormData();
                    formData.append('file', file);
                    formData.append('action', 'pod_upload_custom_file');
                    formData.append('nonce', nonceEl ? nonceEl.value : '');

                    var ajaxUrl =
                        (window.pod_custom_fields && window.pod_custom_fields.ajax_url) ||
                        '/wp-admin/admin-ajax.php';

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                        .then(function (r) {
                            return r.json();
                        })
                        .then(function (response) {
                            if (uploading) uploading.style.display = 'none';
                            if (response && response.success && response.data) {
                                if (hiddenInput) hiddenInput.value = response.data.path || '';
                                if (origInput) origInput.value = response.data.filename || file.name || '';
                                if (fileName) fileName.textContent = response.data.filename || file.name;
                                if (previewImg) {
                                    if (response.data.preview) {
                                        previewImg.setAttribute('src', response.data.preview);
                                        previewImg.style.display = '';
                                    } else {
                                        previewImg.style.display = 'none';
                                    }
                                }
                                if (preview) preview.style.display = 'flex';
                                notifyPersonalizationChanged();
                            } else {
                                window.alert((response && response.data && response.data.message) || 'Upload failed');
                                if (dropzone) dropzone.style.display = '';
                                if (input) input.value = '';
                            }
                        })
                        .catch(function () {
                            if (uploading) uploading.style.display = 'none';
                            if (dropzone) dropzone.style.display = '';
                            window.alert('Upload failed. Please try again.');
                            if (input) input.value = '';
                        });
                }

                if (input) {
                    input.addEventListener('change', function (e) {
                        var file = e.target.files && e.target.files[0];
                        if (file) uploadFile(file);
                    });
                }

                if (dropzone) {
                    dropzone.addEventListener('click', function () {
                        if (input) input.click();
                    });
                    dropzone.addEventListener('dragover', function (e) {
                        e.preventDefault();
                        dropzone.classList.add('dragover');
                    });
                    dropzone.addEventListener('dragleave', function () {
                        dropzone.classList.remove('dragover');
                    });
                    dropzone.addEventListener('drop', function (e) {
                        e.preventDefault();
                        dropzone.classList.remove('dragover');
                        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                            uploadFile(e.dataTransfer.files[0]);
                        }
                    });
                }

                if (removeBtn) {
                    removeBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (hiddenInput) hiddenInput.value = '';
                        if (origInput) origInput.value = '';
                        if (input) input.value = '';
                        if (preview) preview.style.display = 'none';
                        if (dropzone) dropzone.style.display = '';
                        notifyPersonalizationChanged();
                    });
                }
            })(areas[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCustomFields);
    } else {
        initCustomFields();
    }
})();
