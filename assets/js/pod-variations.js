/**
 * POD Variations - Frontend JavaScript
 * Handle swatch selection and price calculation
 * Supports both shared and per-style sizes/colors
 * GMC Compliance: URL params for variant deep-linking
 */

jQuery(function($) {
    'use strict';
    
    // Hide original WooCommerce price immediately
    if ($('body').hasClass('pod-variations-active')) {
        // Target the price element right after product title or in summary
        $('.summary > .price, .summary > p.price, .entry-summary > .price, .entry-summary > p.price').not('.pod-var-price-display .price').hide();
        
        // Also hide any standalone price that's not in our custom display
        $('.woocommerce-Price-amount').each(function() {
            if (!$(this).closest('.pod-var-price-display').length && 
                !$(this).closest('.pod-var-price-amount').length &&
                $(this).closest('.summary, .entry-summary').length) {
                $(this).closest('p.price, .price').hide();
            }
        });
    }
    
    /**
     * Get URL parameters
     */
    function getUrlParams() {
        var params = {};
        var search = window.location.search.substring(1);
        if (search) {
            search.split('&').forEach(function(part) {
                var pair = part.split('=');
                params[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || '');
            });
        }
        return params;
    }
    
    /**
     * Update URL with current selection (without page reload)
     */
    function updateUrl($form) {
        var params = new URLSearchParams(window.location.search);
        
        // Get selected style
        var $selectedStyle = $form.find('.pod-var-style.selected');
        if ($selectedStyle.length > 0) {
            var styleName = $selectedStyle.data('name');
            var styleSlug = slugify(styleName);
            params.set('pod-style', styleSlug);
        } else {
            params.delete('pod-style');
        }
        
        // Get selected size
        var $selectedSize = $form.find('.pod-var-size.selected');
        if ($selectedSize.length > 0) {
            var sizeName = $selectedSize.data('name');
            var sizeSlug = slugify(sizeName);
            params.set('pod-size', sizeSlug);
        } else {
            params.delete('pod-size');
        }
        
        // Update URL without reload
        var newUrl = window.location.pathname;
        if (params.toString()) {
            newUrl += '?' + params.toString();
        }
        window.history.replaceState({}, '', newUrl);
    }
    
    /**
     * Convert string to URL-friendly slug
     */
    function slugify(str) {
        return str.toString().toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }
    
    /**
     * Normalize size string for comparison (remove quotes, spaces, special chars)
     */
    function normalizeSize(str) {
        return str.toString().toLowerCase()
            .replace(/["'″″'']/g, '')  // Remove quotes
            .replace(/\s+/g, '')        // Remove spaces
            .replace(/inch(es)?/gi, '') // Remove "inch" or "inches"
            .replace(/[^\w\d]/g, '');   // Keep only alphanumeric
    }
    
    /**
     * Auto-select variants from URL params
     */
    function autoSelectFromUrl($form) {
        var params = getUrlParams();
        var urlStyle = params['pod-style'] || '';
        var urlSize = params['pod-size'] || '';
        var changed = false;
        
        // Auto-select style
        if (urlStyle) {
            $form.find('.pod-var-style').each(function() {
                var $style = $(this);
                var styleName = $style.data('name');
                var styleSlug = slugify(styleName);
                var styleId = $style.data('id');
                
                if (styleSlug === urlStyle || styleId === urlStyle) {
                    if (!$style.hasClass('selected')) {
                        $style.click();
                        changed = true;
                    }
                    return false; // break
                }
            });
        }
        
        // Auto-select size - use normalized comparison
        if (urlSize) {
            var urlSizeNormalized = normalizeSize(urlSize);
            
            $form.find('.pod-var-size').each(function() {
                var $size = $(this);
                var sizeName = $size.data('name');
                var sizeSlug = slugify(sizeName);
                var sizeNormalized = normalizeSize(sizeName);
                var sizeId = $size.data('id');
                
                // Match by slug, normalized, or ID
                if (sizeSlug === urlSize || sizeNormalized === urlSizeNormalized || sizeId === urlSize) {
                    if (!$size.hasClass('selected')) {
                        $size.click();
                        changed = true;
                    }
                    return false; // break
                }
            });
        }
        
        return changed;
    }
    
    $('.pod-variations-form').each(function() {
        var $form = $(this);
        var useSharedAttr = $form.attr('data-use-shared');
        var useShared = useSharedAttr === 'true' || useSharedAttr === true;
        var perStyleDataAttr = $form.attr('data-per-style');
        var perStyleData = {};
        
        // Parse per-style data from JSON string
        if (perStyleDataAttr) {
            try {
                perStyleData = JSON.parse(perStyleDataAttr);
            } catch (e) {
                // Silent fail
            }
        }
        
        // Style selection - BIND FIRST before auto-select
        $form.on('click', '.pod-var-style', function() {
            var $this = $(this);
            $this.addClass('selected').siblings().removeClass('selected');
            
            var styleId = $this.attr('data-id');
            
            // Update hidden inputs
            $form.find('input[name="pod_var_style_id"]').val(styleId);
            $form.find('input[name="pod_var_style_name"]').val($this.attr('data-name'));
            $form.find('input[name="pod_var_style_price"]').val($this.attr('data-price'));
            
            // Update style name display
            $form.find('.pod-var-style-name-display').text($this.attr('data-name'));
            
            // If per-style mode, update sizes and colors
            if (!useShared && perStyleData[styleId]) {
                updateSizesForStyle($form, perStyleData[styleId].sizes || []);
                updateColorsForStyle($form, perStyleData[styleId].colors || []);
            }
            
            updatePrice($form);
            updateUrl($form);
        });
        
        // Size selection - BIND FIRST before auto-select
        $form.on('click', '.pod-var-size', function() {
            var $this = $(this);
            $this.addClass('selected').siblings().removeClass('selected');
            
            // Update hidden inputs
            $form.find('input[name="pod_var_size_id"]').val($this.data('id'));
            $form.find('input[name="pod_var_size_name"]').val($this.data('name'));
            $form.find('input[name="pod_var_size_upcharge"]').val($this.data('upcharge'));
            
            updatePrice($form);
            updateUrl($form);
        });
        
        // Color selection
        $form.on('click', '.pod-var-color', function() {
            var $this = $(this);
            $this.addClass('selected').siblings().removeClass('selected');
            
            // Update hidden inputs
            $form.find('input[name="pod_var_color_id"]').val($this.data('id'));
            $form.find('input[name="pod_var_color_name"]').val($this.data('name'));
            $form.find('input[name="pod_var_color_hex"]').val($this.data('hex'));
            
            // Update color name display
            $form.find('.pod-var-color-name-display').text($this.data('name'));
        });
        
        // Auto-select from URL params AFTER event handlers are bound
        // Use setTimeout to ensure DOM is fully ready
        setTimeout(function() {
            autoSelectFromUrl($form);
            
            // IMPORTANT: Calculate price after auto-select
            updatePrice($form);
        }, 100);
    });
    
    /**
     * Update sizes section for a specific style (per-style mode)
     */
    function updateSizesForStyle($form, sizes) {
        var $sizesSection = $form.find('.pod-var-sizes');
        var $swatches = $sizesSection.find('.pod-var-swatches');
        
        if (sizes.length === 0) {
            $sizesSection.hide();
            $form.find('input[name="pod_var_size_id"]').val('');
            $form.find('input[name="pod_var_size_name"]').val('');
            $form.find('input[name="pod_var_size_upcharge"]').val('0');
            return;
        }
        
        $sizesSection.show();
        $swatches.empty();
        
        sizes.forEach(function(size, index) {
            var isSelected = index === 0;
            var $swatch = $('<div class="pod-var-swatch pod-var-size' + (isSelected ? ' selected' : '') + '" ' +
                'data-id="' + size.id + '" ' +
                'data-name="' + size.name + '" ' +
                'data-upcharge="' + size.upcharge + '" ' +
                'title="' + size.name + '" tabindex="0">' +
                size.name + '</div>');
            $swatches.append($swatch);
            
            if (isSelected) {
                $form.find('input[name="pod_var_size_id"]').val(size.id);
                $form.find('input[name="pod_var_size_name"]').val(size.name);
                $form.find('input[name="pod_var_size_upcharge"]').val(size.upcharge);
            }
        });
        
        // Recalculate price after updating sizes
        updatePrice($form);
    }
    
    /**
     * Update colors section for a specific style (per-style mode)
     */
    function updateColorsForStyle($form, colors) {
        var $colorsSection = $form.find('.pod-var-colors');
        var $swatches = $colorsSection.find('.pod-var-swatches');
        
        if (colors.length === 0) {
            $colorsSection.hide();
            $form.find('input[name="pod_var_color_id"]').val('');
            $form.find('input[name="pod_var_color_name"]').val('');
            $form.find('input[name="pod_var_color_hex"]').val('');
            $form.find('.pod-var-color-name-display').text('');
            return;
        }
        
        $colorsSection.show();
        $swatches.empty();
        
        colors.forEach(function(color, index) {
            var isSelected = index === 0;
            var $swatch = $('<div class="pod-var-swatch pod-var-color' + (isSelected ? ' selected' : '') + '" ' +
                'data-id="' + color.id + '" ' +
                'data-name="' + color.name + '" ' +
                'data-hex="' + color.hex + '" ' +
                'style="background-color: ' + color.hex + ';" ' +
                'title="' + color.name + '" tabindex="0">' +
                '<span class="sr-only">' + color.name + '</span>' +
                '<span class="pod-var-color-check">✓</span></div>');
            $swatches.append($swatch);
            
            if (isSelected) {
                $form.find('input[name="pod_var_color_id"]').val(color.id);
                $form.find('input[name="pod_var_color_name"]').val(color.name);
                $form.find('input[name="pod_var_color_hex"]').val(color.hex);
                $form.find('.pod-var-color-name-display').text(color.name);
            }
        });
    }
    
    /**
     * Calculate and update price display
     */
    function updatePrice($form) {
        var stylePrice = 0;
        var sizeUpcharge = 0;
        var simplePrice = parseFloat($form.data('simple-price')) || 0;
        
        // Get style price - from selected style or hidden input
        var $selectedStyle = $form.find('.pod-var-style.selected');
        if ($selectedStyle.length > 0) {
            stylePrice = parseFloat($selectedStyle.data('price')) || 0;
        } else {
            // No styles or no selection - use hidden input value
            stylePrice = parseFloat($form.find('input[name="pod_var_style_price"]').val()) || 0;
        }
        
        // If style price is 0, fallback to simple product price
        if (stylePrice === 0 && simplePrice > 0) {
            stylePrice = simplePrice;
        }
        
        // Get size upcharge - from selected size or hidden input
        var $selectedSize = $form.find('.pod-var-size.selected');
        if ($selectedSize.length > 0) {
            sizeUpcharge = parseFloat($selectedSize.data('upcharge')) || 0;
        } else {
            // No sizes or no selection - use hidden input value
            sizeUpcharge = parseFloat($form.find('input[name="pod_var_size_upcharge"]').val()) || 0;
        }
        
        var total = stylePrice + sizeUpcharge;
        
        // Update hidden input
        $form.find('input[name="pod_var_calculated_price"]').val(total.toFixed(2));
        
        // Update display
        var currency = $form.find('.pod-var-price-amount').data('currency') || '$';
        $form.find('.pod-var-price-amount').html('<span class="woocommerce-Price-amount amount">' + currency + total.toFixed(2) + '</span>');
    }
    
    // Keyboard accessibility
    $(document).on('keypress', '.pod-var-swatch', function(e) {
        if (e.which === 13 || e.which === 32) { // Enter or Space
            e.preventDefault();
            $(this).click();
        }
    });
});
