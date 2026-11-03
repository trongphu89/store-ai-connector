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

    function parseHexRgb(hex) {
        var clean = String(hex || '#111827').replace('#', '').trim();
        if (clean.length === 3) {
            clean = clean[0] + clean[0] + clean[1] + clean[1] + clean[2] + clean[2];
        }
        if (clean.length !== 6) {
            return { r: 17, g: 24, b: 39 };
        }
        return {
            r: parseInt(clean.slice(0, 2), 16) || 0,
            g: parseInt(clean.slice(2, 4), 16) || 0,
            b: parseInt(clean.slice(4, 6), 16) || 0
        };
    }

    function shadeHex(hex, delta) {
        var c = parseHexRgb(hex);
        var clamp = function (v) {
            return Math.max(0, Math.min(255, Math.round(v)));
        };
        var toH = function (v) {
            var s = clamp(v).toString(16);
            return s.length === 1 ? '0' + s : s;
        };
        return '#' + toH(c.r + delta) + toH(c.g + delta) + toH(c.b + delta);
    }

    function hexLuminance(hex) {
        var c = parseHexRgb(hex);
        return (0.299 * c.r + 0.587 * c.g + 0.114 * c.b) / 255;
    }

    function drawRoundRectPath(ctx, x, y, w, h, r) {
        ctx.beginPath();
        if (typeof ctx.roundRect === 'function') {
            ctx.roundRect(x, y, w, h, r);
            return;
        }
        var rad = Array.isArray(r) ? Number(r[0] || 8) : Number(r || 8);
        rad = Math.min(rad, w / 2, h / 2);
        ctx.moveTo(x + rad, y);
        ctx.arcTo(x + w, y, x + w, y + h, rad);
        ctx.arcTo(x + w, y + h, x, y + h, rad);
        ctx.arcTo(x, y + h, x, y, rad);
        ctx.arcTo(x, y, x + w, y, rad);
        ctx.closePath();
    }

    function renderStudioVariantDataUrl(opts) {
        var canvas = document.createElement('canvas');
        canvas.width = 960;
        canvas.height = 960;
        var ctx = canvas.getContext('2d');
        if (!ctx) return '';

        var styleKey = String(opts.styleId || '').toLowerCase();
        var sizeKey = String(opts.sizeId || '').toLowerCase();
        var colorId = String(opts.colorId || '').toLowerCase();
        var hex = opts.colorHex || '#111827';
        var lum = hexLuminance(hex);
        var isVeryLight = lum > 0.80;

        // 1. Studio backdrop (contrast-aware so white shirts/mugs pop crisply)
        var bgGrad = ctx.createRadialGradient(480, 440, 80, 480, 480, 620);
        if (isVeryLight) {
            bgGrad.addColorStop(0, '#E2E8F0');
            bgGrad.addColorStop(1, '#CBD5E1');
        } else {
            bgGrad.addColorStop(0, '#F8FAFC');
            bgGrad.addColorStop(1, '#E2E8F0');
        }
        ctx.fillStyle = bgGrad;
        ctx.fillRect(0, 0, 960, 960);

        var isMug = styleKey.indexOf('mug') !== -1 || sizeKey === '11oz' || sizeKey === '15oz';
        var isTumbler = styleKey.indexOf('tumbler') !== -1 || sizeKey === '20oz' || sizeKey === '30oz';
        var isHoodie = styleKey.indexOf('hoodie') !== -1 || styleKey.indexOf('pullover') !== -1;
        var isSweatshirt = !isHoodie && (styleKey.indexOf('sweatshirt') !== -1 || styleKey.indexOf('crewneck') !== -1);
        var isLongSleeve = styleKey.indexOf('long-sleeve') !== -1 || styleKey.indexOf('long sleeve') !== -1;
        var isVNeck = styleKey.indexOf('v-neck') !== -1 || styleKey.indexOf('vneck') !== -1;
        var isTank = styleKey.indexOf('tank') !== -1;
        var isJersey = styleKey.indexOf('jersey') !== -1 || styleKey === 'stitched' || styleKey === 'print';

        var artBox = { x: 325, y: 240, w: 310, h: 330 };
        var baseDark = shadeHex(hex, -28);
        var baseLight = shadeHex(hex, 22);
        var strokeCol = isVeryLight ? '#94A3B8' : shadeHex(hex, -45);

        ctx.save();
        ctx.shadowColor = 'rgba(15, 23, 42, 0.22)';
        ctx.shadowBlur = 30;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 16;

        if (isMug) {
            var is15oz = sizeKey === '15oz';
            var mugTop = is15oz ? 210 : 255;
            var mugH = is15oz ? 490 : 425;
            var mugW = 360;
            var mugX = 255;
            var twoTone = colorId.indexOf('black') !== -1;
            var bodyHex = '#FFFFFF';
            var accentHex = twoTone ? '#111827' : '#F1F5F9';

            // C-handle
            ctx.lineWidth = 38;
            ctx.strokeStyle = twoTone ? '#111827' : '#E2E8F0';
            ctx.beginPath();
            ctx.arc(mugX + mugW + 10, mugTop + mugH * 0.5, mugH * 0.29, -Math.PI * 0.46, Math.PI * 0.46);
            ctx.stroke();

            // Mug cylinder body
            var mugGrad = ctx.createLinearGradient(mugX, mugTop, mugX + mugW, mugTop);
            mugGrad.addColorStop(0, '#E2E8F0');
            mugGrad.addColorStop(0.22, bodyHex);
            mugGrad.addColorStop(0.75, '#F8FAFC');
            mugGrad.addColorStop(1, '#CBD5E1');
            ctx.fillStyle = mugGrad;
            drawRoundRectPath(ctx, mugX, mugTop, mugW, mugH, [12, 12, 34, 34]);
            ctx.fill();
            ctx.restore();

            // Top interior rim
            ctx.fillStyle = accentHex;
            ctx.strokeStyle = '#CBD5E1';
            ctx.lineWidth = 4;
            ctx.beginPath();
            ctx.ellipse(mugX + mugW / 2, mugTop + 8, mugW / 2 - 4, 22, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();

            artBox = { x: mugX + 46, y: mugTop + 62, w: mugW - 92, h: mugH - 115 };
        } else if (isTumbler) {
            var is30oz = sizeKey === '30oz';
            var tTop = is30oz ? 185 : 210;
            var tBot = 785;
            var topHalfW = is30oz ? 158 : 132;
            var botHalfW = is30oz ? 116 : 114;

            // Straw
            ctx.fillStyle = '#94A3B8';
            ctx.fillRect(471, 88, 18, tTop - 68);

            // Tumbler body
            var tGrad = ctx.createLinearGradient(480 - topHalfW, tTop, 480 + topHalfW, tTop);
            tGrad.addColorStop(0, shadeHex(hex, -35));
            tGrad.addColorStop(0.32, shadeHex(hex, 28));
            tGrad.addColorStop(0.65, hex);
            tGrad.addColorStop(1, shadeHex(hex, -42));
            ctx.fillStyle = tGrad;
            ctx.beginPath();
            ctx.moveTo(480 - topHalfW, tTop);
            ctx.lineTo(480 + topHalfW, tTop);
            ctx.lineTo(480 + botHalfW, tBot);
            ctx.quadraticCurveTo(480, tBot + 24, 480 - botHalfW, tBot);
            ctx.closePath();
            ctx.fill();
            ctx.restore();

            // Clear lid
            ctx.fillStyle = 'rgba(226, 232, 240, 0.88)';
            ctx.strokeStyle = '#94A3B8';
            ctx.lineWidth = 3;
            drawRoundRectPath(ctx, 480 - topHalfW - 4, tTop - 28, topHalfW * 2 + 8, 30, 8);
            ctx.fill();
            ctx.stroke();

            artBox = { x: 480 - botHalfW + 14, y: tTop + 75, w: (botHalfW - 14) * 2, h: 330 };
        } else {
            // Apparel silhouettes
            var gGrad = ctx.createLinearGradient(220, 140, 740, 800);
            gGrad.addColorStop(0, baseLight);
            gGrad.addColorStop(0.45, hex);
            gGrad.addColorStop(1, baseDark);

            // Optional Pullover Hood behind neckline
            if (isHoodie) {
                ctx.fillStyle = shadeHex(hex, -12);
                ctx.strokeStyle = strokeCol;
                ctx.lineWidth = 3;
                ctx.beginPath();
                ctx.moveTo(352, 182);
                ctx.bezierCurveTo(335, 86, 405, 58, 480, 58);
                ctx.bezierCurveTo(555, 58, 625, 86, 608, 182);
                ctx.closePath();
                ctx.fill();
                ctx.stroke();

                // Inner hood shadow
                ctx.fillStyle = shadeHex(hex, -52);
                ctx.beginPath();
                ctx.ellipse(480, 152, 74, 42, 0, 0, Math.PI * 2);
                ctx.fill();
            }

            ctx.fillStyle = gGrad;
            ctx.strokeStyle = strokeCol;
            ctx.lineWidth = 3;
            ctx.beginPath();

            if (isTank) {
                // Sleeveless Tank Top
                ctx.moveTo(368, 145);
                ctx.quadraticCurveTo(480, 248, 592, 145);
                ctx.lineTo(632, 156);
                ctx.quadraticCurveTo(605, 290, 665, 355);
                ctx.lineTo(672, 790);
                ctx.quadraticCurveTo(480, 806, 288, 790);
                ctx.lineTo(295, 355);
                ctx.quadraticCurveTo(355, 290, 328, 156);
                ctx.closePath();
                artBox = { x: 340, y: 280, w: 280, h: 315 };
            } else if (isHoodie || isSweatshirt || isLongSleeve) {
                // Long-sleeve Fleece / Tee
                ctx.moveTo(382, 158);
                ctx.quadraticCurveTo(480, 198, 578, 158);
                ctx.lineTo(695, 202);
                ctx.lineTo(822, 685);
                ctx.lineTo(742, 712);
                ctx.lineTo(664, 378);
                ctx.lineTo(668, 786);
                ctx.lineTo(292, 786);
                ctx.lineTo(296, 378);
                ctx.lineTo(218, 712);
                ctx.lineTo(138, 685);
                ctx.lineTo(265, 202);
                ctx.closePath();
                artBox = isHoodie
                    ? { x: 342, y: 238, w: 276, h: 276 }
                    : { x: 328, y: 248, w: 304, h: 318 };
            } else {
                // Short-sleeve T-Shirt / V-Neck / Jersey
                ctx.moveTo(385, 150);
                if (isVNeck || isJersey) {
                    ctx.lineTo(480, 232);
                    ctx.lineTo(575, 150);
                } else {
                    ctx.quadraticCurveTo(480, 196, 575, 150);
                }
                ctx.lineTo(705, 196);
                ctx.lineTo(816, 368);
                ctx.lineTo(726, 428);
                ctx.lineTo(666, 350);
                ctx.lineTo(672, 792);
                ctx.quadraticCurveTo(480, 808, 288, 792);
                ctx.lineTo(294, 350);
                ctx.lineTo(234, 428);
                ctx.lineTo(144, 368);
                ctx.lineTo(255, 196);
                ctx.closePath();
                artBox = { x: 325, y: 245, w: 310, h: 330 };
            }

            ctx.fill();
            ctx.stroke();
            ctx.restore();

            // Inner back-neck shadow
            if (!isHoodie) {
                ctx.fillStyle = shadeHex(hex, -48);
                ctx.beginPath();
                if (isVNeck || isJersey) {
                    ctx.moveTo(395, 154);
                    ctx.lineTo(480, 224);
                    ctx.lineTo(565, 154);
                    ctx.quadraticCurveTo(480, 174, 395, 154);
                } else if (!isTank) {
                    ctx.ellipse(480, 162, 86, 22, 0, 0, Math.PI);
                }
                ctx.fill();
            }

            // Ribbed hem & cuffs for Sweatshirt / Hoodie
            if (isHoodie || isSweatshirt) {
                ctx.fillStyle = shadeHex(hex, -18);
                ctx.strokeStyle = strokeCol;
                ctx.lineWidth = 2;
                ctx.fillRect(293, 748, 374, 38);
                ctx.strokeRect(293, 748, 374, 38);
            }

            // Sweatshirt heritage V-notch stitch
            if (isSweatshirt) {
                ctx.strokeStyle = shadeHex(hex, isVeryLight ? -45 : 38);
                ctx.lineWidth = 2.5;
                ctx.beginPath();
                ctx.moveTo(452, 182);
                ctx.lineTo(480, 214);
                ctx.lineTo(508, 182);
                ctx.stroke();
            }

            // Hoodie Kangaroo Pocket + Drawstrings
            if (isHoodie) {
                ctx.fillStyle = shadeHex(hex, -10);
                ctx.strokeStyle = shadeHex(hex, isVeryLight ? -42 : -36);
                ctx.lineWidth = 2.5;
                ctx.beginPath();
                ctx.moveTo(356, 558);
                ctx.lineTo(604, 558);
                ctx.lineTo(638, 668);
                ctx.lineTo(618, 746);
                ctx.lineTo(342, 746);
                ctx.lineTo(322, 668);
                ctx.closePath();
                ctx.fill();
                ctx.stroke();

                // Drawstrings
                ctx.strokeStyle = isVeryLight ? '#64748B' : '#E2E8F0';
                ctx.lineWidth = 5;
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.moveTo(446, 186);
                ctx.quadraticCurveTo(442, 255, 448, 315);
                ctx.moveTo(514, 186);
                ctx.quadraticCurveTo(518, 255, 512, 315);
                ctx.stroke();
            }

            // Jersey center button placket
            if (isJersey) {
                ctx.strokeStyle = isVeryLight ? '#1E293B' : '#F8FAFC';
                ctx.lineWidth = 3;
                ctx.beginPath();
                ctx.moveTo(480, 232);
                ctx.lineTo(480, 795);
                ctx.stroke();
            }
        }

        // 4. Composite product artwork into artBox
        var artImg = opts.artworkImg;
        var fbImg = opts.fallbackLifestyleImg;
        if (artImg && artImg.complete && artImg.naturalWidth > 0) {
            var iw = artImg.naturalWidth;
            var ih = artImg.naturalHeight;
            var scale = Math.min(artBox.w / iw, artBox.h / ih);
            var dw = iw * scale;
            var dh = ih * scale;
            var dx = artBox.x + (artBox.w - dw) / 2;
            var dy = artBox.y + (artBox.h - dh) / 2;
            ctx.drawImage(artImg, dx, dy, dw, dh);
        } else if (fbImg && fbImg.complete && fbImg.naturalWidth > 0) {
            var fw = fbImg.naturalWidth;
            var fh = fbImg.naturalHeight;
            ctx.save();
            drawRoundRectPath(ctx, artBox.x + 18, artBox.y + 18, artBox.w - 36, artBox.h - 36, 18);
            ctx.clip();
            ctx.drawImage(
                fbImg,
                fw * 0.25,
                fh * 0.24,
                fw * 0.50,
                fh * 0.50,
                artBox.x + 18,
                artBox.y + 18,
                artBox.w - 36,
                artBox.h - 36
            );
            ctx.restore();
        }

        // 5. Top-left Selected Variant badge
        var badgeParts = [];
        if (opts.styleLabel) badgeParts.push(opts.styleLabel);
        else if (opts.sizeLabel && (isMug || isTumbler)) badgeParts.push(opts.sizeLabel);
        if (opts.colorLabel) badgeParts.push(opts.colorLabel);
        var badgeText = (badgeParts.join(' · ') || 'Selected Variant').toUpperCase();
        ctx.font = '700 18px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        var bMetrics = ctx.measureText(badgeText);
        var bW = Math.min(860, bMetrics.width + 44);
        ctx.fillStyle = 'rgba(15, 23, 42, 0.84)';
        drawRoundRectPath(ctx, 28, 28, bW, 42, 21);
        ctx.fill();
        ctx.fillStyle = hex;
        ctx.strokeStyle = '#FFFFFF';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(50, 49, 8, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        ctx.fillStyle = '#FFFFFF';
        ctx.fillText(badgeText, 68, 55);

        // 6. Bottom-right Store Domain watermark pill
        var domainLabel = String(opts.storeDomain || window.location.hostname || '').toUpperCase();
        if (domainLabel) {
            ctx.font = '700 16px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            var dW = ctx.measureText(domainLabel).width + 44;
            var dX = 960 - dW - 28;
            var dY = 960 - 66;
            ctx.fillStyle = 'rgba(15, 23, 42, 0.80)';
            drawRoundRectPath(ctx, dX, dY, dW, 38, 19);
            ctx.fill();
            ctx.fillStyle = '#10B981';
            ctx.beginPath();
            ctx.arc(dX + 19, dY + 19, 7, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = '#F8FAFC';
            ctx.fillText(domainLabel, dX + 33, dY + 25);
        }

        try {
            return canvas.toDataURL('image/webp', 0.90);
        } catch (_e) {
            return '';
        }
    }

    function findNativeSummaryPriceTargets() {
        var insTargets = document.querySelectorAll(
            '.summary .price ins .woocommerce-Price-amount bdi, .entry-summary .price ins .woocommerce-Price-amount bdi, .product-page-price ins .woocommerce-Price-amount bdi'
        );
        if (insTargets && insTargets.length > 0) {
            return insTargets;
        }
        return document.querySelectorAll(
            '.summary .price .woocommerce-Price-amount bdi, .entry-summary .price .woocommerce-Price-amount bdi, .product-page-price .woocommerce-Price-amount bdi'
        );
    }

    function initContainer(container) {
        if (!container || container.getAttribute('data-pod-of-ready') === '1') {
            return;
        }
        container.setAttribute('data-pod-of-ready', '1');
        if (document.body) {
            document.body.classList.add('pod-option-forms-active');
        }

        // Prevent duplicate Price/Stock bar & duplicate theme Size Chart on single product pages
        var liveBar = container.querySelector('.pod-of-live-price-bar');
        var nativePriceEls = findNativeSummaryPriceTargets();
        if (liveBar) {
            if (!nativePriceEls || nativePriceEls.length === 0) {
                liveBar.classList.add('is-fallback-visible');
            } else {
                liveBar.classList.remove('is-fallback-visible');
                liveBar.style.display = 'none';
            }
        }

        var duplicateThemeCharts = document.querySelectorAll(
            '.summary [data-ct-sizechart], .summary .ct-sizechart-link, .entry-summary [data-ct-sizechart], .entry-summary .ct-sizechart-link, form.cart [data-ct-sizechart], form.cart .ct-sizechart-link'
        );
        for (var dc = 0; dc < duplicateThemeCharts.length; dc++) {
            duplicateThemeCharts[dc].style.display = 'none';
        }

        var basePrice = parseFloat(container.getAttribute('data-base-price')) || 24.99;
        var showSummary = container.getAttribute('data-show-summary') === '1';
        var artworkUrl = container.getAttribute('data-artwork-url') || '';
        var storeDomain = container.getAttribute('data-store-domain') || window.location.hostname || '';
        var currencySymbol = getCurrencySymbol();

        // Live Variant Gallery Switcher state (TC-045)
        var galleryRoot = document.querySelector(
            '.woocommerce-product-gallery, .product-gallery, .ct-product-gallery-container'
        );
        var mainGalleryImg = galleryRoot
            ? galleryRoot.querySelector(
                  '.woocommerce-product-gallery__image img.wp-post-image, .woocommerce-product-gallery__image a img, img.wp-post-image'
              )
            : null;
        var origGallerySrc = mainGalleryImg ? mainGalleryImg.getAttribute('src') || '' : '';
        var origGallerySrcset = mainGalleryImg ? mainGalleryImg.getAttribute('srcset') || '' : '';
        var origGalleryLarge = mainGalleryImg ? mainGalleryImg.getAttribute('data-large_image') || '' : '';
        var variantCache = {};
        var galleryMode = 'lifestyle';
        var switcherBar = null;
        var lifestylePillBtn = null;
        var variantPillBtn = null;
        var variantDotEl = null;
        var variantLabelEl = null;

        var preloadedArtwork = null;
        if (artworkUrl) {
            preloadedArtwork = new Image();
            preloadedArtwork.crossOrigin = 'anonymous';
            preloadedArtwork.onload = function () {
                variantCache = {};
                if (galleryMode === 'variant') {
                    applyVariantGalleryPreview();
                }
            };
            preloadedArtwork.src = artworkUrl;
        }

        var preloadedLifestyle = null;
        if (origGallerySrc) {
            preloadedLifestyle = new Image();
            preloadedLifestyle.crossOrigin = 'anonymous';
            preloadedLifestyle.src = origGallerySrc;
        }

        function getCurrentVariantDescriptor() {
            var styleBtn = container.querySelector(
                '.pod-of-field[data-semantic-role="style"] .pod-of-choice.is-selected, .pod-of-field[data-field-id="style"] .pod-of-choice.is-selected'
            );
            var sizeBtn = container.querySelector(
                '.pod-of-field[data-semantic-role="size"] .pod-of-choice.is-selected, .pod-of-field[data-field-id="mug_size"] .pod-of-choice.is-selected, .pod-of-field[data-field-id="tumbler_size"] .pod-of-choice.is-selected'
            );
            var colorBtn = container.querySelector(
                '.pod-of-field[data-semantic-role="color"] .pod-of-choice.is-selected, .pod-of-field[data-field-id="color"] .pod-of-choice.is-selected, .pod-of-field[data-field-id="mug_color"] .pod-of-choice.is-selected, .pod-of-field[data-field-id="tumbler_color"] .pod-of-choice.is-selected'
            );
            var pagePath = (window.location && window.location.pathname ? window.location.pathname : '').toLowerCase();
            var fallbackStyleId = 't-shirt';
            var fallbackStyleLabel = 'T-Shirt';
            if (pagePath.indexOf('mug') !== -1 || pagePath.indexOf('ceramic-cup') !== -1) {
                fallbackStyleId = 'mug';
                fallbackStyleLabel = 'Ceramic Mug';
            } else if (pagePath.indexOf('tumbler') !== -1) {
                fallbackStyleId = 'tumbler';
                fallbackStyleLabel = 'Tumbler';
            } else if (pagePath.indexOf('hoodie') !== -1) {
                fallbackStyleId = 'hoodie';
                fallbackStyleLabel = 'Pullover Hoodie';
            } else if (pagePath.indexOf('sweatshirt') !== -1 || pagePath.indexOf('crewneck') !== -1) {
                fallbackStyleId = 'sweatshirt';
                fallbackStyleLabel = 'Crewneck Sweatshirt';
            } else if (pagePath.indexOf('jersey') !== -1) {
                fallbackStyleId = 'jersey';
                fallbackStyleLabel = 'Custom Jersey';
            }

            var styleId = styleBtn ? styleBtn.getAttribute('data-opt-id') || fallbackStyleId : fallbackStyleId;
            var styleLabel = styleBtn
                ? (styleBtn.getAttribute('data-opt-label') || fallbackStyleLabel).replace(/\s*\(\+\$[^)]+\)/g, '')
                : fallbackStyleLabel;
            var sizeId = sizeBtn ? sizeBtn.getAttribute('data-opt-id') || '' : '';
            var sizeLabel = sizeBtn
                ? (sizeBtn.getAttribute('data-opt-label') || '').replace(/\s*\(\+\$[^)]+\)/g, '')
                : '';
            var colorId = colorBtn ? colorBtn.getAttribute('data-opt-id') || 'black' : 'black';
            var colorLabel = colorBtn
                ? (colorBtn.getAttribute('data-opt-label') || 'Black').replace(/\s*\(\+\$[^)]+\)/g, '')
                : 'Black';
            var colorHex = colorBtn ? colorBtn.getAttribute('data-hex') || '#111827' : '#111827';

            return {
                styleId: styleId,
                styleLabel: styleLabel,
                sizeId: sizeId,
                sizeLabel: sizeLabel,
                colorId: colorId,
                colorLabel: colorLabel,
                colorHex: colorHex
            };
        }

        function refreshSwitcherPillLabels() {
            if (!switcherBar) return;
            var desc = getCurrentVariantDescriptor();
            if (variantDotEl) {
                variantDotEl.style.backgroundColor = desc.colorHex;
            }
            if (variantLabelEl) {
                var shortStyle = desc.styleLabel || desc.sizeLabel || 'Variant';
                variantLabelEl.textContent = shortStyle + ' · ' + desc.colorLabel;
            }
            if (lifestylePillBtn && variantPillBtn) {
                if (galleryMode === 'variant') {
                    variantPillBtn.classList.add('is-active');
                    lifestylePillBtn.classList.remove('is-active');
                } else {
                    lifestylePillBtn.classList.add('is-active');
                    variantPillBtn.classList.remove('is-active');
                }
            }
        }

        function setGalleryImgUrl(nextUrl, restoreOrig) {
            if (!mainGalleryImg || !nextUrl) return;
            if (restoreOrig) {
                mainGalleryImg.setAttribute('src', origGallerySrc);
                if (origGallerySrcset) {
                    mainGalleryImg.setAttribute('srcset', origGallerySrcset);
                }
                if (origGalleryLarge) {
                    mainGalleryImg.setAttribute('data-large_image', origGalleryLarge);
                }
            } else {
                mainGalleryImg.removeAttribute('srcset');
                mainGalleryImg.removeAttribute('sizes');
                mainGalleryImg.setAttribute('src', nextUrl);
                mainGalleryImg.setAttribute('data-large_image', nextUrl);
            }
            var slideWrap = mainGalleryImg.closest('.woocommerce-product-gallery__image');
            if (slideWrap) {
                var zoomImg = slideWrap.querySelector('img.zoomImg');
                if (zoomImg) {
                    zoomImg.setAttribute('src', restoreOrig ? origGalleryLarge || origGallerySrc : nextUrl);
                }
                var anchor = slideWrap.querySelector('a');
                if (anchor) {
                    anchor.setAttribute('href', restoreOrig ? origGalleryLarge || origGallerySrc : nextUrl);
                }
            }
        }

        function applyVariantGalleryPreview() {
            if (!mainGalleryImg) return;
            var desc = getCurrentVariantDescriptor();
            var artReady = !!(preloadedArtwork && preloadedArtwork.complete && preloadedArtwork.naturalWidth > 0);
            var cacheKey =
                [desc.styleId, desc.sizeId, desc.colorId, desc.colorHex, artReady ? 'art1' : 'art0'].join('|');
            var dataUrl = variantCache[cacheKey];
            if (!dataUrl) {
                dataUrl = renderStudioVariantDataUrl({
                    styleId: desc.styleId,
                    styleLabel: desc.styleLabel,
                    sizeId: desc.sizeId,
                    sizeLabel: desc.sizeLabel,
                    colorId: desc.colorId,
                    colorLabel: desc.colorLabel,
                    colorHex: desc.colorHex,
                    storeDomain: storeDomain,
                    artworkImg: preloadedArtwork,
                    fallbackLifestyleImg: preloadedLifestyle || mainGalleryImg
                });
                if (dataUrl && artReady) {
                    variantCache[cacheKey] = dataUrl;
                }
            }
            if (dataUrl) {
                galleryMode = 'variant';
                setGalleryImgUrl(dataUrl, false);
                refreshSwitcherPillLabels();
            }
        }

        function restoreLifestyleGalleryPreview() {
            if (!mainGalleryImg || !origGallerySrc) return;
            galleryMode = 'lifestyle';
            setGalleryImgUrl(origGallerySrc, true);
            refreshSwitcherPillLabels();
        }

        if (
            galleryRoot &&
            mainGalleryImg &&
            origGallerySrc &&
            container.querySelector('.pod-of-field[data-semantic-role="color"], .pod-of-field[data-semantic-role="style"]')
        ) {
            switcherBar = document.createElement('div');
            switcherBar.className = 'pod-of-gallery-switcher';

            lifestylePillBtn = document.createElement('button');
            lifestylePillBtn.type = 'button';
            lifestylePillBtn.className = 'pod-of-gallery-pill is-active';
            lifestylePillBtn.textContent = 'Lifestyle Photo';
            lifestylePillBtn.addEventListener('click', function () {
                restoreLifestyleGalleryPreview();
            });

            variantPillBtn = document.createElement('button');
            variantPillBtn.type = 'button';
            variantPillBtn.className = 'pod-of-gallery-pill';
            variantDotEl = document.createElement('span');
            variantDotEl.className = 'pod-of-gallery-color-dot';
            variantLabelEl = document.createElement('span');
            variantPillBtn.appendChild(variantDotEl);
            variantPillBtn.appendChild(variantLabelEl);
            variantPillBtn.addEventListener('click', function () {
                applyVariantGalleryPreview();
            });

            switcherBar.appendChild(lifestylePillBtn);
            switcherBar.appendChild(variantPillBtn);
            galleryRoot.appendChild(switcherBar);
            refreshSwitcherPillLabels();
        }

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

            // Include Personalization Extra Fee when active on the same product page
            var cfContainers = document.querySelectorAll('.pod-custom-fields-container[data-enable-fee="true"]');
            for (var cfi = 0; cfi < cfContainers.length; cfi++) {
                var cfc = cfContainers[cfi];
                var cfFee = parseFloat(cfc.getAttribute('data-extra-fee')) || 0;
                if (cfFee <= 0) continue;
                var cfOptional = cfc.getAttribute('data-optional') === 'true';
                var cfActive = false;
                if (cfOptional) {
                    var optChk = cfc.querySelector('#pod_enable_personalization');
                    cfActive = !!(optChk && optChk.checked);
                } else {
                    var cfInputs = cfc.querySelectorAll('.pod-custom-fields-form input:not([type="file"]):not([type="hidden"]), .pod-custom-fields-form textarea, .pod-custom-fields-form select, .pod-custom-fields-form .pod-file-path');
                    for (var k = 0; k < cfInputs.length; k++) {
                        if (cfInputs[k].value && String(cfInputs[k].value).trim() !== '') {
                            cfActive = true;
                            break;
                        }
                    }
                }
                if (cfActive) {
                    addTotal += cfFee;
                }
            }

            var finalPrice = Math.max(0.01, Math.round((effectiveBase + addTotal) * 100) / 100);
            var formattedFinal = formatMoney(finalPrice, currencySymbol);

            var liveAmtEl = container.querySelector('.pod-of-live-price-amount');
            if (liveAmtEl) {
                liveAmtEl.textContent = formattedFinal;
            }

            var summaryPriceTargets = findNativeSummaryPriceTargets();
            for (var sp = 0; sp < summaryPriceTargets.length; sp++) {
                summaryPriceTargets[sp].textContent = formattedFinal;
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

            var role = (field.getAttribute('data-semantic-role') || '').toLowerCase();
            var fid = (field.getAttribute('data-field-id') || '').toLowerCase();
            if (
                role === 'style' ||
                role === 'color' ||
                fid === 'mug_size' ||
                fid === 'tumbler_size'
            ) {
                applyVariantGalleryPreview();
            } else {
                refreshSwitcherPillLabels();
            }
        }

        function activateSizeGuideTab(modal, tabId) {
            if (!modal || !tabId) return;
            var tabs = modal.querySelectorAll('.pod-sg-tab-btn');
            var panels = modal.querySelectorAll('.pod-sg-tab-panel, [data-sg-panel]');
            for (var i = 0; i < tabs.length; i++) {
                var isMatch = tabs[i].getAttribute('data-sg-tab') === tabId;
                if (isMatch) {
                    tabs[i].classList.add('is-active');
                    tabs[i].setAttribute('aria-selected', 'true');
                } else {
                    tabs[i].classList.remove('is-active');
                    tabs[i].setAttribute('aria-selected', 'false');
                }
            }
            for (var j = 0; j < panels.length; j++) {
                var panelMatch = panels[j].getAttribute('data-sg-panel') === tabId;
                if (panelMatch) {
                    panels[j].classList.add('is-active');
                    panels[j].style.display = '';
                } else {
                    panels[j].classList.remove('is-active');
                    panels[j].style.display = 'none';
                }
            }
        }

        function autoMatchSizeGuideTab(modal) {
            if (!modal) return;
            var tabs = modal.querySelectorAll('.pod-sg-tab-btn');
            if (!tabs || tabs.length === 0) return;

            var fitField = container.querySelector(
                '.pod-of-field[data-semantic-role="fit"] .pod-of-hidden-val, .pod-of-field[data-field-id="fit_type"] .pod-of-hidden-val, .pod-of-field[data-field-id="gender"] .pod-of-hidden-val'
            );
            var styleField = container.querySelector(
                '.pod-of-field[data-semantic-role="style"] .pod-of-hidden-val, .pod-of-field[data-field-id="style"] .pod-of-hidden-val'
            );
            var fitVal = fitField && fitField.value ? String(fitField.value).toLowerCase() : '';
            var styleVal = styleField && styleField.value ? String(styleField.value).toLowerCase() : '';

            var candidates = [];
            if (fitVal === 'youth' || fitVal === 'kids' || fitVal === 'preschool') {
                candidates.push(fitVal);
            }
            if (styleVal) {
                candidates.push(styleVal);
            }
            var pagePath = (window.location && window.location.pathname ? window.location.pathname : '').toLowerCase();
            var keywords = ['hockey', 'basketball', 'football', 'baseball', 'sweatshirt', 'hoodie', 'long-sleeve', 'v-neck', 'mug', 'tumbler'];
            for (var k = 0; k < keywords.length; k++) {
                if (pagePath.indexOf(keywords[k]) !== -1) {
                    candidates.push(keywords[k]);
                }
            }

            for (var c = 0; c < candidates.length; c++) {
                var token = candidates[c];
                // Pass 1: exact match across all tabs first (prevents "tshirt" matching inside "sweatshirt")
                for (var t = 0; t < tabs.length; t++) {
                    var rawExact = (tabs[t].getAttribute('data-match-styles') || '').toLowerCase();
                    if (!rawExact) continue;
                    var exactParts = rawExact.split(',');
                    for (var ep = 0; ep < exactParts.length; ep++) {
                        if (exactParts[ep].trim() === token) {
                            activateSizeGuideTab(modal, tabs[t].getAttribute('data-sg-tab'));
                            return;
                        }
                    }
                }
                // Pass 2: hyphen/space segment match (e.g. "pullover-hoodie" matches "hoodie", but "sweatshirt" never matches "tshirt")
                var tokenSegs = token.split(/[-_\s]+/);
                for (var t2 = 0; t2 < tabs.length; t2++) {
                    var rawSeg = (tabs[t2].getAttribute('data-match-styles') || '').toLowerCase();
                    if (!rawSeg) continue;
                    var segParts = rawSeg.split(',');
                    for (var sp2 = 0; sp2 < segParts.length; sp2++) {
                        var pt = segParts[sp2].trim();
                        if (pt && tokenSegs.indexOf(pt) !== -1) {
                            activateSizeGuideTab(modal, tabs[t2].getAttribute('data-sg-tab'));
                            return;
                        }
                    }
                }
            }
        }

        // Delegated click handler inside container
        container.addEventListener('click', function (e) {
            var target = e.target;
            if (!target || !target.closest) return;

            var sgTabBtn = target.closest('.pod-sg-tab-btn');
            if (sgTabBtn && container.contains(sgTabBtn)) {
                e.preventDefault();
                var parentModal = sgTabBtn.closest('.pod-of-modal-backdrop');
                var sgTabId = sgTabBtn.getAttribute('data-sg-tab');
                if (parentModal && sgTabId) {
                    activateSizeGuideTab(parentModal, sgTabId);
                }
                return;
            }

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
                    autoMatchSizeGuideTab(modal);
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

        document.addEventListener('pod_custom_fields_updated', function () {
            recalculatePrice();
        });

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
