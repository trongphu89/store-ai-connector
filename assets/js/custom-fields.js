/**
 * POD Custom Fields - Frontend JavaScript
 * Handles file upload via AJAX
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle file input change
        $('.pod-file-input').on('change', function(e) {
            var $input = $(this);
            var $area = $input.closest('.pod-file-upload-area');
            var $dropzone = $area.find('.pod-file-dropzone');
            var $preview = $area.find('.pod-file-preview');
            var $uploading = $area.find('.pod-file-uploading');
            var $hiddenInput = $area.find('.pod-file-path');
            var $previewImg = $area.find('.pod-file-preview-img');
            var $fileName = $area.find('.pod-file-name');
            
            var file = e.target.files[0];
            if (!file) return;
            
            // Validate file size
            var maxSize = parseInt($input.data('max-size')) || 5;
            if (file.size > maxSize * 1024 * 1024) {
                alert('File too large. Maximum size: ' + maxSize + 'MB');
                $input.val('');
                return;
            }
            
            // Show uploading state
            $dropzone.hide();
            $preview.hide();
            $uploading.show();
            
            // Create form data
            var formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'pod_upload_custom_file');
            formData.append('nonce', $('#pod_custom_fields_nonce').val());
            
            // Upload via AJAX
            $.ajax({
                url: pod_custom_fields.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $uploading.hide();
                    
                    if (response.success) {
                        // Save path to hidden input
                        $hiddenInput.val(response.data.path);
                        
                        // Show preview
                        $fileName.text(response.data.filename);
                        
                        if (response.data.preview) {
                            $previewImg.attr('src', response.data.preview).show();
                        } else {
                            $previewImg.hide();
                        }
                        
                        $preview.show();
                    } else {
                        alert(response.data.message || 'Upload failed');
                        $dropzone.show();
                        $input.val('');
                    }
                },
                error: function() {
                    $uploading.hide();
                    $dropzone.show();
                    alert('Upload failed. Please try again.');
                    $input.val('');
                }
            });
        });

        
        // Handle file remove
        $('.pod-file-remove').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $area = $(this).closest('.pod-file-upload-area');
            var $dropzone = $area.find('.pod-file-dropzone');
            var $preview = $area.find('.pod-file-preview');
            var $hiddenInput = $area.find('.pod-file-path');
            var $fileInput = $area.find('.pod-file-input');
            
            // Clear values
            $hiddenInput.val('');
            $fileInput.val('');
            
            // Show dropzone
            $preview.hide();
            $dropzone.show();
        });
        
        // Drag and drop visual feedback
        $('.pod-file-dropzone').on('dragover', function(e) {
            e.preventDefault();
            $(this).addClass('dragover');
        }).on('dragleave drop', function(e) {
            $(this).removeClass('dragover');
        });
    });

})(jQuery);
