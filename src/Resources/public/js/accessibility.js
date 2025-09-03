if (typeof jQuery === "undefined") {
    var jQuery = $; 
}
/* +++++++++++++++  :: Accessibility data   ::   ++++++++++*/
function resetAccessibility(mode, toggle, value, lodingColor) {
    var color = (lodingColor === '#' || lodingColor == null) ? '#ec7b18' : lodingColor;
    jQuery('body').append(`
        <div class="vt-accessibility-loading-container">
            <svg width="100" height="100" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="loading-icon">
                <style>
                    .loading-icon {
                    animation: rotate 1s linear infinite;
                    }
                    @keyframes rotate {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                    }
                    .loading-icon path {
                    stroke: ${color};
                    }
                </style>
                <path
                    d="M21.2981 14.2981C20.0289 15.5673 17.9711 15.5673 16.7019 14.2981C15.4327 13.0289 15.4327 10.9711 16.7019 9.7019C17.9711 8.4327 20.0289 8.4327 21.2981 9.7019C22.5673 10.9711 22.5673 13.0289 21.2981 14.2981Z"
                    stroke="#121331" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" />
                <path
                    d="M14.2981 7.2981C13.0289 8.5673 10.9711 8.5673 9.7019 7.2981C8.4327 6.02889 8.4327 3.97111 9.7019 2.7019C10.9711 1.4327 13.0289 1.4327 14.2981 2.7019C15.5673 3.97111 15.5673 6.02889 14.2981 7.2981Z"
                    stroke="#121331" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" />
                <path
                    d="M14.2981 21.2981C13.0289 22.5673 10.9711 22.5673 9.7019 21.2981C8.4327 20.0289 8.4327 17.9711 9.7019 16.7019C10.9711 15.4327 13.0289 15.4327 14.2981 16.7019C15.5673 17.9711 15.5673 20.0289 14.2981 21.2981Z"
                    stroke="#121331" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" />
                <path
                    d="M7.2981 14.2981C6.0289 15.5673 3.9711 15.5673 2.7019 14.2981C1.4327 13.0289 1.4327 10.9711 2.7019 9.7019C3.9711 8.4327 6.0289 8.4327 7.2981 9.7019C8.5673 10.9711 8.5673 13.0289 7.2981 14.2981Z"
                    stroke="#121331" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" />
            </svg>
        </div> 
    `);
    let submitData = { 'mode': mode, 'toggle': toggle, 'value': value };
    jQuery.ajax({
        type: 'POST',
        url: '/ajax-accessibility',
        data: submitData,
        cache: false,
        success: function (styleDefs) {
            jQuery('#acc-styler').html(styleDefs);
            // Hide the loading container after a successful response
            jQuery('.vt-accessibility-loading-container').fadeOut(300, function () {
                jQuery(this).remove(); // Remove it from the DOM after fading out
            });
        }
    });
}
/* +++++++++++++++  :: Accessibility Set Cookies   ::   ++++++++++*/
function setCookie(name, value, days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "") + expires + "; path=/";
}

/* +++++++++++++++  :: Accessibility Get Cookies   ::   ++++++++++*/
function getCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

/* +++++++++++++++  :: Accessibility Remove Inline Style   ::   ++++++++++*/
function removeInlineStyles(currentElement) {
    // Remove the specific inline style from all siblings of the current element
    currentElement.siblings().css('background-color', '');
}

/* +++++++++++++++  :: Accessibility Function Epilepsy Mode   ::   ++++++++++*/
function applyEpilepsyMode(setFlag) {
    if (setFlag === 1) {
        jQuery('html').css({ 'filter': 'grayscale(100%)', 'filter': 'gray', 'webkitFilter': 'grayscale(100%)', '-moz-filter': 'grayscale(100%)' });
    }
    else {
        jQuery('html').css({ 'filter': '', 'filter': '', 'webkitFilter': '', '-moz-filter': '' });
    }
}

/* +++++++++++++++  :: Accessibility Reading Mask and ADHS Function Mode   ::   ++++++++++*/
let readingMaskTop = jQuery('<div>');
let readingMaskBottom = jQuery('<div>');

function createReadingMask() {
    readingMaskTop.addClass('accessibility-reading-mask-top');
    jQuery('body').append(readingMaskTop);
    readingMaskBottom.addClass('accessibility-reading-mask-bottom');
    jQuery('body').append(readingMaskBottom);
    jQuery(document).on('mousemove', function (e) {
        updateReadingMaskPosition(e);
    });
}
function updateReadingMaskPosition(e) {
    const mouseY = e.clientY;
    let topHeight = mouseY - 50;
    let bottomTop = mouseY + 50;

    readingMaskTop.css('height', `${topHeight}px`);
    readingMaskBottom.css('top', `${bottomTop}px`);
}
function removeReadingMask() {
    readingMaskTop.remove();
    readingMaskBottom.remove();
}

/* +++++++++++++++  :: Accessibility Stop Animation Function Mode ::   ++++++++++*/
function stopAnimations(flag, value) {
    if (flag === 1) {
        // Stop animations and transitions globally
        jQuery('*').css({
            animation: value,
            transition: value
        });

        // Stop specific sliders
        if (jQuery('.owl-carousel').length) {
            jQuery('.owl-carousel').trigger('stop.owl.autoplay');
        }
        if (jQuery('.slick-slider').length) {
            jQuery('.slick-slider').slick('slickPause');
        }
        jQuery('.swiper-container').each(function () {
            const swiperInstance = jQuery(this).data('swiper');
            if (swiperInstance) {
                swiperInstance.autoplay.stop();
            }
        });
        if (jQuery('.rocksolid-slider').length) {
            jQuery('.rocksolid-slider').rocksolidSlider('pause');
        }
    } 
    else {
        // Reset animations and transitions globally
        jQuery('*').css({
            animation: '',
            transition: ''
        });

        // Restart specific sliders
        if (jQuery('.owl-carousel').length) {
            jQuery('.owl-carousel').trigger('play.owl.autoplay');
        }
        if (jQuery('.slick-slider').length) {
            jQuery('.slick-slider').slick('slickPlay');
        }
        jQuery('.swiper-container').each(function () {
            const swiperInstance = jQuery(this).data('swiper');
            if (swiperInstance) {
                swiperInstance.autoplay.start();
            }
        });
        if (jQuery('.rocksolid-slider').length) {
            jQuery('.rocksolid-slider').rocksolidSlider('play');
        }
    }
}


/* +++++++++++++++  :: Accessibility Mute Sounds & Unmute Functions Action Controls ::   ++++++++++*/

function initializeYouTubeAPI() {
    jQuery('iframe').each(function () {
        const src = jQuery(this).attr('src');
        if (src && src.includes('youtube.com') && !src.includes('enablejsapi=1')) {
            const newSrc = src.includes('?')
                ? `${src}&enablejsapi=1`
                : `${src}?enablejsapi=1`;
            jQuery(this).attr('src', newSrc);
        }
    });
}
function muteSound() {
    // Mute HTML video and audio elements
    jQuery('video, audio').each(function () {
        this.muted = true;
    });

    // Mute YouTube iframes
    jQuery('iframe').each(function () {
        const src = jQuery(this).attr('src');
        if (src && src.includes('youtube.com')) {
            this.contentWindow.postMessage('{"event":"command","func":"mute","args":""}', '*');
        }
    });
}
function unmuteSound() {
    // Unmute HTML video and audio elements
    jQuery('video, audio').each(function () {
        this.muted = false;
    });

    // Unmute YouTube iframes
    jQuery('iframe').each(function () {
        const src = jQuery(this).attr('src');
        if (src && src.includes('youtube.com')) {
            this.contentWindow.postMessage('{"event":"command","func":"unMute","args":""}', '*');
        }
    });
}

/* +++++++++++++++  :: Accessibility White Cursor Functions Action Controls ::   ++++++++++*/
function resetWhiteCursorAccessibility(action, flag, value) {
    if (action === 'highlight-cursor' && flag === 1) {
        jQuery('body').css('cursor', `url('/bundles/accessplus/images/cursor/white-cursor.svg'), auto`);

        jQuery('a, button, select, input, textarea').hover(function () {
            jQuery(this).css('cursor', `url('/bundles/accessplus/images/cursor/white-pointer.svg'), pointer`);
        }, function () {
            jQuery(this).css('cursor', '');
        });
    }
    else if (action === 'highlight-cursor' && flag === 0) {
        jQuery('body').css('cursor', '');
        jQuery('a, button, select, input, textarea').hover(function () {
            jQuery('a, button, select, input, textarea').css('cursor', '');
        });
    }
}

/* +++++++++++++++  :: Accessibility Black Cursor Functions Action Controls ::   ++++++++++*/
function resetBlackCursorAccessibility(action, flag, value) {
    if (action === 'highlight-cursor' && flag === 1) {
        jQuery('body').css('cursor', `url('/bundles/accessplus/images/cursor/black-cursor.svg'), auto`);
        jQuery('a, button, select, input, textarea').hover(function () {
            jQuery(this).css('cursor', `url('/bundles/accessplus/images/cursor/black-pointer.svg'), pointer`);
        });
    }
    else if (action === 'highlight-cursor' && flag === 0) {
        jQuery('body').css('cursor', '');
        jQuery('a, button, select, input, textarea').hover(function () {
            jQuery('a, button, select, input, textarea').css('cursor', '');
        });
    }
}

/* +++++++++++++++  :: Accessibility Hide Images Functions Action Controls ::   ++++++++++*/
function toggleImages(flag) {
    const hide = flag === 1;

    // Hide or show <img> tags
    jQuery('img').css('visibility', hide ? 'hidden' : '');

    // Loop over all elements
    jQuery('*').each(function () {
        const el = this;
        const $el = jQuery(el);
        const computedStyle = window.getComputedStyle(el);

        const bgImage = computedStyle.backgroundImage;

        if (hide) {
            // Check if element has a background image
            if (bgImage && bgImage !== 'none' && bgImage.includes('url(')) {
                // Save original background-image if not already saved
                if (!$el.attr('data-access-bg-image')) {
                    $el.attr('data-access-bg-image', bgImage);
                }
                $el.css('background-image', 'none');
            }
        } else {
            // Restore saved background-image
            const original = $el.attr('data-access-bg-image');
            if (original) {
                $el.css('background-image', original);
                $el.removeAttr('data-access-bg-image');
            }
        }
    });
}


/* +++++++++++++++  :: Accessibility Reading Guide Position Functions Action Controls ::   ++++++++++*/
function updateReadingGuidePosition(e, guide) {
    const mouseY = e.clientY;
    const mouseX = e.clientX;
    const x = Math.round(mouseX - (guide.width() / 2));
    guide.css('transform', `translate3d(${x}px, ${mouseY}px, 0px)`);
}

/* +++++++++++++++  :: Accessibility Text to Speech Functions Action Controls ::   ++++++++++*/
let synth = window.speechSynthesis;
let ttsEnabled = getCookie('ttsEnabled') === '1';
let currentUtterance = null;
const siteLang = jQuery('html').attr('lang') ? jQuery('html').attr('lang') : 'en';

function textToSpeech(text, lang) {
    if (synth.speaking) {
        synth.cancel();
    }
    const chunks = splitTextIntoChunks(text, 150);
    const speakChunk = (index) => {
        if (index < chunks.length && ttsEnabled) {
            currentUtterance = new SpeechSynthesisUtterance(chunks[index]);
            currentUtterance.lang = lang;
            currentUtterance.rate = 1; 
            currentUtterance.pitch = 1; 
            currentUtterance.onend = function () {
                speakChunk(index + 1);
            };
            synth.speak(currentUtterance);
        }
        else {
            currentUtterance = null;
        }
    };
    speakChunk(0);
}
function splitTextIntoChunks(text, chunkSize) {
    const regex = new RegExp(`(.|[\r\n]){1,${chunkSize}}`, 'g');
    return text.match(regex) || [];
}

/* +++++++++++++++  :: Accessibility Negative Mode Functions Controls ::   ++++++++++*/






/* +++++++++++++++  :: Accessibility Cognitive Reading Functions Controls ::   ++++++++++*/
function initializeCognitiveReading() {
    let cognitiveReadingActive = getCookie('cognitiveReadingActive') === 'true';

    if (cognitiveReadingActive) {
        jQuery('#accessibility-action-cognitive-reading').addClass('access-active');

        // Check if each action has the 'access-active' class and trigger if needed
        if (!jQuery("#accessibility-action-readable-font").hasClass('access-active')) {
            jQuery("#accessibility-action-readable-font").trigger('click');
        }
        if (!jQuery("#accessibility-action-stop-animations").hasClass('access-active')) {
            jQuery("#accessibility-action-stop-animations").trigger('click');
        }
        if (!jQuery("#accessibility-action-highlight-links").hasClass('access-active')) {
            jQuery("#accessibility-action-highlight-links").trigger('click');
        }
        if (!jQuery("#accessibility-action-highlight-hover").hasClass('access-active')) {
            jQuery("#accessibility-action-highlight-hover").trigger('click');
        }
    }
}

/* +++++++++++++++  :: Accessibility Content Scaling Functions Controls ::   ++++++++++*/
let contentScaleDisplay;
let zoomFactor;
// Update content scaling display
function updateContentScaleDisplay() {
    let scalePercent = Math.round((zoomFactor - 1) * 100);
    if (scalePercent === 0) {
        contentScaleDisplay.text('Standard');
    }
    else {
        contentScaleDisplay.text(`${scalePercent}%`);
    }
}

/* +++++++++++++++  :: Accessibility Arial Button Functions Controls ::   ++++++++++*/
function readableFontAction(font, backgroundColor){
    // Initialise local vars
    let setFlag = 0;
    
    // Find the element with the matching data-color value
    const element = jQuery(`#accessibility-action-readable-font`);

    // Remove selection from the dyslexia font button
    element.siblings('#accessibility-action-dyslexia-font').removeClass('access-active');
    setCookie('selectedDyslexiaFont', '', -1);

    // Set active/inactive to self
    element.toggleClass('access-active');

    // If the readable font is active, set the flag and save the selection in a cookie
    if (element.hasClass('access-active')) {
        setFlag = 1;
        setCookie('selectedReadableFont', font, 365);
    }
    else {
        // If deactivated, remove the font cookie
        setCookie('selectedReadableFont', '', -1);  
    }
    // Call the resetAccessibility function (assuming it's defined elsewhere)
    resetAccessibility('font', setFlag, font, backgroundColor);
}

/* +++++++++++++++  :: Accessibility Dyslexia Font Button Functions Controls ::   ++++++++++*/
function dyslexiaFontAction(font, backgroundColor){
    // Initialise local vars
    let setFlag = 0;
    setCookie('selectedReadableFont', '', -1);

    // Find the element with the matching data-color value
    const element = jQuery(`#accessibility-action-dyslexia-font`);

    // Remove selection from the readable font button
    element.siblings('#accessibility-action-readable-font').removeClass('access-active');

    element.toggleClass('access-active');

    // If the dyslexia font is active, set the flag and save the selection in a cookie
    if (element.hasClass('access-active')) {
        setFlag = 1;
        setCookie('selectedDyslexiaFont', font, 365);
    }
    else {
        setCookie('selectedDyslexiaFont', '', -1);
    }
    resetAccessibility('font', setFlag, font, backgroundColor);
}

/* +++++++++++++++  :: Accessibility Highlight Title Functions Controls ::   ++++++++++*/
function resetHighlightTitleAccessibility(action, flag, value) {
    if (action === 'highlight-titles' && flag === 1) {
        jQuery('h1, h2, h3, h4, h5, h6').each(function () {
            // Apply outline styles with !important using setProperty
            jQuery(this)[0].style.setProperty('outline-style', 'solid', 'important');
            jQuery(this)[0].style.setProperty('outline-color', 'rgba(0, 0, 0, 1)', 'important');
            jQuery(this)[0].style.setProperty('outline-width', '2px', 'important');
            jQuery(this)[0].style.setProperty('outline-offset', '2px', 'important');
        });
    }
    else if (action === 'highlight-titles' && flag === 0) {
        jQuery('h1, h2, h3, h4, h5, h6').each(function () {
            // Apply outline styles with !important using setProperty
            jQuery(this)[0].style.setProperty('outline-style', '');
            jQuery(this)[0].style.setProperty('outline-color', '');
            jQuery(this)[0].style.setProperty('outline-width', '');
            jQuery(this)[0].style.setProperty('outline-offset', '');
        });
    }
}

/* +++++++++++++++  :: Accessibility Font Size Functions Controls ::   ++++++++++*/
let totalFontSizeClicks = parseInt(getCookie('fontSizeClicks')) || 0;

let initialFontSizes = {};
function applyFontSizeAdjustments() {
    jQuery('h1, h2, h3, h4, h5, h6, a, p, button, li, span').each(function () {
        let element = jQuery(this);
        let tag = element.prop("tagName");

        let initialSize = initialFontSizes[tag];
        if (!initialSize) return;

        if (totalFontSizeClicks !== 0) {
            // Calculate new font size as a percentage of the original
            let fontSizeAdjustment = initialSize * (1 + totalFontSizeClicks * 0.05);

            // Optional: Prevent font size from becoming too small (e.g., below 50% of original)
            if (fontSizeAdjustment < initialSize * 0.5) {
                fontSizeAdjustment = initialSize * 0.5;
            }

            element.css('font-size', fontSizeAdjustment + 'px');
        } else {
            // Do NOT reset CSS inline style — allow default CSS to take over
            element.css('font-size', '');
        }
    });

    updateDefaultFontSizeLabel();
}
function updateDefaultFontSizeLabel() {
    if (totalFontSizeClicks === 0) {
        jQuery('#scale-display-fontsize-adjuster').text('Standard');
    } else {
        let fontSizePercentageChange = totalFontSizeClicks * 5;
        jQuery('#scale-display-fontsize-adjuster').text(`${fontSizePercentageChange}%`);
    }
}

/* +++++++++++++++  :: Accessibility Line Height Functions Controls ::   ++++++++++*/
let totalLineHeightClicks = parseInt(getCookie('lineHeightClicks')) || 0;

// Store the initial Line Height for reset
let initialLineHeight = {};

// Function to apply line height adjustments based on click count
function applyLineHeightAdjustments() {
    // Only apply adjustments if the line height has been changed (totalLineHeightClicks !== 0)
    if (totalLineHeightClicks !== 0) {
        jQuery('h1, h2, h3, h4, h5, h6, a, p, button, li, span').each(function () {
            let element = jQuery(this);
            let tag = element.prop("tagName");

            // Use the initial line height and apply the adjustments based on clicks
            let initialHeight = initialLineHeight[tag];
            if (initialHeight) {
                element.css('line-height', (initialHeight + totalLineHeightClicks * 1.4) + 'px');
            }
        });
    }
    else{
        jQuery('h1, h2, h3, h4, h5, h6, a, p, button, li, span').each(function () {
            let element = jQuery(this);
            element.css('line-height', '');
        });
    }
    updateDefaultLineHeightLabel();
}

// Update display of line height status
function updateDefaultLineHeightLabel() {
    if (totalLineHeightClicks === 0) {
        jQuery('#scale-display-line-height-adjuster').text('Standard');
    } else {
        let lineHeightPercentageChange = totalLineHeightClicks * 5;
        jQuery('#scale-display-line-height-adjuster').text(`${lineHeightPercentageChange}%`);
    }
}

/* +++++++++++++++  :: Accessibility Letter Spaching Functions Controls ::   ++++++++++*/
let totalLetterSpacingClicks = parseInt(getCookie('letterSpacingClicks')) || 0;

// Initial letter spacing values for reset
let initialLetterSpacing = {};

function applyLetterSpacingAdjustments() {
    if (totalLetterSpacingClicks !== 0) {
        jQuery('h1, h2, h3, h4, h5, h6, a, p, button, li, span').each(function () {
            let element = jQuery(this);
            let tag = element.prop("tagName");

            // Use the initial letter spacing and apply the adjustments based on clicks
            let initialSpacing = initialLetterSpacing[tag];
            let adjustedSpacing = initialSpacing + (totalLetterSpacingClicks * 0.05); // Adjust spacing based on clicks

            // Apply letter spacing with 2 decimal precision
            element.css('letter-spacing', `${adjustedSpacing.toFixed(2)}px`);
        });
    } else {
        // Reset to initial letter spacing if no clicks
        jQuery('h1, h2, h3, h4, h5, h6, a, p, button, li, span').each(function () {
            let element = jQuery(this);
            let tag = element.prop("tagName");

            // Apply the initial letter spacing when resetting to standard
            let initialSpacing = initialLetterSpacing[tag];
            element.css('letter-spacing', `${initialSpacing.toFixed(2)}px`);
        });
    }
    updateDefaultLetterSpacing();
}

function updateDefaultLetterSpacing() {
    if (totalLetterSpacingClicks === 0) {
        jQuery('#scale-display-letter-spacing-adjuster').text('Standard');
    } else {
        let letterSpacingPercentageChange = totalLetterSpacingClicks * 5;
        jQuery('#scale-display-letter-spacing-adjuster').text(`${letterSpacingPercentageChange}%`);
    }
}

// Reset function when going back to standard
function resetToStandard() {
    totalLetterSpacingClicks = 0;
    setCookie('letterSpacingClicks', totalLetterSpacingClicks); // Update the cookie to reflect the reset
    applyLetterSpacingAdjustments(); // Apply the reset changes
}

/* +++++++++++++++  :: Accessibility Link Navigate Functions Controls ::   ++++++++++*/
function navigateToLink(link) {
    // Check if the link is external
    if (link.startsWith('http')) {
        // Open external links in a new tab
        window.open(link, '_blank');
    } else {
        // Navigate to internal links within the same tab
        window.location.href = link;
    }
}

/* +++++++++++++++  :: Accessibility Mark Hover Functions Controls ::   ++++++++++*/
function initializeMarkHover() {
    removeMarkHover();
    jQuery('*').on('mouseenter.accessibilityHover', function () {
        let backgroundColor = jQuery(this).css('background-color');
        let outlineColor = (backgroundColor === 'rgb(0, 0, 0)') ? 'white' : (backgroundColor === 'rgb(255, 255, 255)' ? 'black' : 'black');
        jQuery(this).css({
            'outline-style': 'solid',
            'outline-color': outlineColor,
            'outline-width': '2px',
            'outline-offset': '2px'
        });
    }).on('mouseleave.accessibilityHover', function () {
        jQuery(this).css({
            'outline': '',
            'outline-offset': ''
        });
    });
}

function removeMarkHover() {
    jQuery('*').off('.accessibilityHover');
}

/* ++++++++++++ Accessibility Highlight Focus Functions Controls :: ++++++++++++ */
var prevClickedElement = null;
function initializeHighlightFocus() {
    removeHighlightFocus();
    jQuery('input, select, textarea, a, button').on('click.accessibilityFocus', function (e) {
        // Remove border from the previously clicked element
        if (prevClickedElement !== null) {
            jQuery(prevClickedElement).css('border', '');
        }

        let backgroundColor = jQuery(this).css('background-color');
        let borderColor = (backgroundColor === 'rgb(0, 0, 0)') ? 'white' : 'black';
        jQuery(this).css('border', `4px solid ${borderColor}`);
        prevClickedElement = this;

        // Handle anchor and button clicks with delay
        if (jQuery(this).is('a') || jQuery(this).is('button')) {
            e.preventDefault();
            const element = jQuery(this);
            setTimeout(function () {
                element.css('border', '');
                if (element.is('a')) {
                    window.location.href = element.attr('href');
                } else if (element.is('button')) {
                    element.closest('form').submit();
                }
            }, 400);
        }
    });
}

function removeHighlightFocus() {
    jQuery('input, select, textarea, a, button').off('.accessibilityFocus');
    prevClickedElement = null;
}

/* +++++++++++++++  :: Accessibility Alignment Text Functions Controls ::   ++++++++++*/
function alignmentTextItems(align, color){
    // Initialise local vars
    let setFlag = 0;
    // Find the element with the matching data-color value
    const element = jQuery(`#accessibility-action-align-${align}`);
    // Set active/inactive to self
    element.toggleClass('access-active');
    if (element.hasClass('access-active')) {
        setFlag = 1;
        setCookie('selectedAlignment', align, 365);

        // Remove the specific inline style from other buttons
        removeInlineStyles(element);
    } 
    else {
        setCookie('selectedAlignment', '', -1); // Remove the cookie if deactivated
    }

    resetAccessibility('alignment', setFlag, align, color);
}

/* +++++++++++++++  :: Accessibility All Text Color Function  ::   ++++++++++*/
function allTextColor(colorValue, backgroundColor){
    // Initialise local vars
    let setFlag = 0;
    
    // Find the element with the matching data-color value
    const element = jQuery(`#accessibility-action-alltext-colors .accessibility-color[data-color="${colorValue}"]`);

    // Remove selection from siblings
    element.siblings().removeClass('access-active-b');

    // Set active/inactive to self
    element.toggleClass('access-active-b');

    // If the element is active, set the flag and save the color in a cookie
    if (element.hasClass('access-active-b')) {
        setFlag = 1;
        setCookie('selectedTextColor', colorValue, 365);
    } 
    else {
        // If deactivated, remove the cookie
        setCookie('selectedTextColor', '', -1); // Setting a negative expiry will delete the cookie
    }

    // Call the resetAccessibility function (assuming it's defined elsewhere)
    resetAccessibility('alltextcolor', setFlag, colorValue, backgroundColor);
}

/* +++++++++++++++  :: Accessibility Text Color Function  ::   ++++++++++*/
function textColor(colorValue, backgroundColor){
    // Initialise local vars
    let setFlag = 0;

    // Find the element with the matching data-color value
    const element = jQuery(`#accessibility-action-text-colors .accessibility-color[data-color="${colorValue}"]`);

    // Remove selection from siblings
    element.siblings().removeClass('access-active-b');

    // Set active/inactive to self
    element.toggleClass('access-active-b');

    // If the element is active, set the flag and save the color in a cookie
    if (element.hasClass('access-active-b')) {
        setFlag = 1;
        setCookie('textcolor', colorValue, 365);
    } 
    else {
        // If deactivated, remove the cookie
        setCookie('textcolor', '', -1);  // Setting a negative expiry will delete the cookie
    }

    resetAccessibility('textcolor', setFlag, colorValue, backgroundColor);
}

/* +++++++++++++++  :: Accessibility Link Color Function  ::   ++++++++++*/
function linkColorAcrion(colorValue, backgroundColor){
    // Initialise local vars
    let setFlag = 0;

    // Find the element with the matching data-color value
    const element = jQuery(`#accessibility-action-link-colors .accessibility-color[data-color="${colorValue}"]`);

    // Remove selection from siblings
    element.siblings().removeClass('access-active-b');

    // Set active/inactive to self
    element.toggleClass('access-active-b');

    // If the element is active, set the flag and save the color in a cookie
    if (element.hasClass('access-active-b')) {
        setFlag = 1;
        setCookie('selectedLinkColor', colorValue, 365);
    } 
    else {
        // If deactivated, remove the cookie
        setCookie('selectedLinkColor', '', -1);  
    }
    resetAccessibility('linkcolor', setFlag, colorValue, backgroundColor);
}

/* +++++++++++++++  :: Accessibility Title Color Function  ::   ++++++++++*/
function titleColorAction(colorValue, backgroundColor){
    // Initialise local vars
    let setFlag = 0;

    // Find the element with the matching data-color value
    const element = jQuery(`#accessibility-action-title-colors .accessibility-color[data-color="${colorValue}"]`);

    // Remove selection from siblings
    element.siblings().removeClass('access-active-b');

    // Set active/inactive to self
    element.toggleClass('access-active-b');

    // If the element is active, set the flag and save the color in a cookie
    if (element.hasClass('access-active-b')) {
        setFlag = 1;
        setCookie('selectedTitleColor', colorValue, 365);
    } 
    else {
        setCookie('selectedTitleColor', '', -1); 
    }
    resetAccessibility('titlecolor', setFlag, colorValue, backgroundColor);
}

/* +++++++++++++++  :: Accessibility Background Color Function  ::   ++++++++++*/
function backgroundColorAction(colorValue, backgroundColor){
    // Initialise local vars
    let setFlag = 0;

    // Find the element with the matching data-color value
    const element = jQuery(`#accessibility-action-background-colors .accessibility-color[data-color="${colorValue}"]`);

    // Remove selection from siblings
    element.siblings().removeClass('access-active-b');

    // Set active/inactive to self
    element.toggleClass('access-active-b');

    // If the element is active, set the flag and save the color in a cookie
    if (element.hasClass('access-active-b')) {
        setFlag = 1;
        setCookie('selectedBgColor', colorValue, 365);
    } else {
        // If deactivated, remove the cookie
        setCookie('selectedBgColor', '', -1);
    }

    // Call the resetAccessibility function (assuming it's defined elsewhere)
    resetAccessibility('bgcolor', setFlag, colorValue, backgroundColor);
}


/*
=================================================================================================================================
Load Accessiablity on Page load
=================================================================================================================================
*/
jQuery(document).ready(function () { 
    
    
    /* ++++++++++++ Accessibility System Color change ++++++++++++ */
    var backgroundColor = jQuery('#accessibility-trigger-button').data('backgroungcolor');
    if (backgroundColor) {
        // Set background color for #accessibility-popup
        jQuery('#accessibility-popup').attr('style', 'background: ' + backgroundColor + ' !important');
        jQuery('head').append(`
            <style id="dynamic-style">
                .access-active {
                    background-color: ${backgroundColor} !important;
                }
                .accessibility-action-box:hover {
                    background-color: ${backgroundColor} !important;
                    border: 2px solid ${backgroundColor} !important;
                    color: #fff !important;
                }
            </style>
        `);
    }

    /* +++++++++++++++  :: Accessibility Add aria text on document  ::   ++++++++++*/
    jQuery('a, button, label, [role="button"], [role="link"]').each(function () {
        var $this = jQuery(this);
        // Skip if aria-label is already set
        if ($this.attr('aria-label')) return;
        // Case 1: <a> tag with <img alt="">
        if ($this.is('a') && $this.find('img[alt]').length > 0) {
          const alt = $this.find('img[alt]').first().attr('alt').trim();
          if (alt) $this.attr('aria-label', alt);
          return;
        }
        // Case 2: Empty buttons/links – fallback
        const trimmedText = $this.text().trim();
        if (!trimmedText) {
            if ($this.attr('title')) {
            $this.attr('aria-label', $this.attr('title').trim());
            } else {
            $this.attr('aria-label', 'Interactive element');
            }
            return;
        }
        // Case 3: Buttons or links with visible text
        if ($this.is('button') || $this.is('a') || $this.attr('role') === 'button') {
            $this.attr('aria-label', trimmedText);
        }
        // Case 4: Label pointing to input
        if ($this.is('label')) {
            const forId = $this.attr('for');
            const $input = jQuery('#' + forId);
            if ($input.length) {
            const labelId = assignId($this);
                $input.attr('aria-labelledby', labelId);
            }
        }
    });
    // Helper: assign unique ID if missing
    function assignId($el) {
        if (!$el.attr('id')) {
            const id = 'aria-id-' + Math.random().toString(36).substr(2, 6);
            $el.attr('id', id);
            return id;
        }
        return $el.attr('id');
    }
    
    /* +++++++++++++++  :: Accessibility Add Extra text on document  ::   ++++++++++*/
    jQuery('header, nav, main, footer, aside, section, article, button, li, select, input, ul, ol, form, dl, dt, dd, fieldset, table, th, td, caption, thead, tbody, tfoot, tr, dialog, .breadcrumb ').each(function () {
        var $this = jQuery(this);
        if ($this.is('header')) {
            $this.attr('role', 'banner');
        }
        else if ($this.is('nav')) {
            $this.attr('role', 'navigation');
        }
        else if ($this.is('main')) {
            $this.attr('role', 'main');
        }
        else if ($this.is('footer')) {
            $this.attr('role', 'contentinfo');
        }
        else if ($this.is('aside')) {
            $this.attr('role', 'complementary');
        }
        else if ($this.is('section')) {
            $this.attr('role', 'region');
        }
        else if ($this.is('article')) {
            $this.attr('role', 'article');
        }
        else if ($this.is('form')) {
            $this.attr('role', 'form');
        }
        else if ($this.is('button')) {
            $this.attr('role', 'button');
        }
        else if ($this.is('input[type="checkbox"]')) {
            $this.attr('role', 'checkbox');
        }
        else if ($this.is('input[type="radio"]')) {
            $this.attr('role', 'radio');
        }
        else if ($this.is('input[type="range"]')) {
            $this.attr('role', 'slider');
        }
        else if ($this.is('input[type="file"]')) {
            $this.attr('role', 'button');
        }
        else if ($this.is('input[type="color"]')) {
            $this.attr('role', 'textbox');
        }
        else if ($this.is('input[type="text"]')) {
            $this.attr('role', 'textbox');
        }
        else if ($this.is('input[type="date"]')) {
            $this.attr('role', 'combobox');
        }
        else if ($this.is('input[type="datetime"]')) {
            $this.attr('role', 'combobox');
        }
        else if ($this.is('input[type="search"]')) {
            $this.attr('role', 'searchbox');
        }
        else if ($this.is('input[type="email"]')) {
            $this.attr('role', 'email');
        }
        else if ($this.is('input[type="tel"]')) {
            $this.attr('role', 'tel');
        }
        else if ($this.is('input[type="url"]')) {
            $this.attr('role', 'url');
        }
        else if ($this.is('input[type="password"]')) {
            $this.attr('role', 'password');
        }
        else if ($this.is('select')) {
            $this.attr('role', 'combobox');
        }
        else if ($this.is('ul') || $this.is('ol') || $this.is('dl')) {
            $this.attr('role', 'list');
        }
        else if ($this.is('textarea')) {
            $this.attr('role', 'textbox');
        }
        else if ($this.is('li')) {
            $this.attr('role', 'listitem');
        }
        else if ($this.is('dt')) {
            $this.attr('role', 'term');
        }
        else if ($this.is('dd')) {
            $this.attr('role', 'definition');
        }
        else if ($this.is('fieldset')) {
            $this.attr('role', 'group');
        }
        else if ($this.is('table')) {
            $this.attr('role', 'table');
        }
        else if ($this.is('th')) {
            $this.attr('role', 'rowheader');
        }
        else if ($this.is('td')) {
            $this.attr('role', 'cell');
        }
        else if ($this.is('caption')) {
            $this.attr('role', 'caption');
        }
        else if ($this.is('thead') || $this.is('tbody') || $this.is('tfoot')) {
            $this.attr('role', 'rowgroup');
        }
        else if ($this.is('tr')) {
            $this.attr('role', 'row');
        }
        else if ($this.is('dialog')) {
            $this.attr('role', 'dialog');
        }
        else if ($this.is('.breadcrumb')) {
            $this.attr('role', 'navigation');
        }
        // Table structure roles
        else if ($this.is('table')) {
            $this.attr('role', 'table');
        } 
        else if ($this.is('thead, tbody, tfoot')) {
            $this.attr('role', 'rowgroup');
        } 
        else if ($this.is('tr')) {
            $this.attr('role', 'row');
        } 
        else if ($this.is('th')) {
            $this.attr('role', 'columnheader');
        } 
        else if ($this.is('td')) {
            $this.attr('role', 'cell');
        }
        // Custom components based on class
        else if ($this.hasClass('js-aria-button')) {
            $this.attr('role', 'button');
            $this.attr('tabindex', '0');
        } 
        else if ($this.hasClass('js-alert')) {
            $this.attr('role', 'alert');
        } 
        else if ($this.hasClass('js-tooltip')) {
            $this.attr('role', 'tooltip');
        } 
        else if ($this.hasClass('js-progressbar')) {
            $this.attr('role', 'progressbar');
        } 
        else if ($this.hasClass('js-tablist')) {
            $this.attr('role', 'tablist');
        } 
        else if ($this.hasClass('js-tab')) {
            $this.attr('role', 'tab');
        } 
        else if ($this.hasClass('js-tabpanel')) {
            $this.attr('role', 'tabpanel');
        }
    });

    var logoFound = false; // Track if a logo is found and data-logo is added

    // For <img> logos
    jQuery('img.logo, #logo img, img[alt*="logo"]').each(function () {
        jQuery(this).attr('data-logo', '1');
        logoFound = true;
    });

    // For background image logos
    jQuery('.logo, #logo, [class*="logo"]').each(function () {
        var backgroundImage = jQuery(this).css('background-image');
        if (backgroundImage && backgroundImage !== 'none') {
            jQuery(this).attr('data-logo', '1');
            logoFound = true;
        }
    });

    // For <a> tag logos
    jQuery('.logo a, #logo a, a[class*="logo"], header img:first').each(function () {
        jQuery(this).attr('data-logo', '1');
        logoFound = true;
    });

    // For SVG logos
    jQuery('svg.logo, #logo svg, svg[class*="logo"]').each(function () {
        jQuery(this).attr('data-logo', '1');
        logoFound = true;
    });
    // Assign ARIA roles for the main structure elements
    jQuery('header').attr('role', 'banner');
    jQuery('nav').attr('role', 'navigation');
    jQuery('main').attr('role', 'main');
    jQuery('footer').attr('role', 'contentinfo');
    jQuery('aside').attr('role', 'complementary');
    jQuery('section').attr('role', 'region');
    jQuery('article').attr('role', 'article');
 
    // Assign ARIA roles for interactive elements
    jQuery('button').attr('role', 'button');
    jQuery('a[href]:not([role="button"])').attr('role', 'link');
    jQuery('input[type="checkbox"]').attr('role', 'checkbox');
    jQuery('input[type="radio"]').attr('role', 'radio');
    jQuery('select').attr('role', 'combobox');
    jQuery('input[type="search"]').attr('role', 'searchbox');
 
    // Assign ARIA roles for lists and groups
    jQuery('ul, ol').attr('role', 'list');
    jQuery('li').attr('role', 'listitem');
    jQuery('dl').attr('role', 'list');
    jQuery('dt').attr('role', 'term');
    jQuery('dd').attr('role', 'definition');
 
    // Assign ARIA roles for tables and grids
    jQuery('table').attr('role', 'table');
    jQuery('th').each(function() {
        var scope = jQuery(this).attr('scope');
        if (scope === 'col') {
            jQuery(this).attr('role', 'columnheader');
        } else if (scope === 'row') {
            jQuery(this).attr('role', 'rowheader');
        }
    });
    jQuery('td').attr('role', 'cell');
    jQuery('caption').attr('role', 'caption');
 
    // Assign ARIA roles for modals and dialogs
    jQuery('dialog, .modal').attr('role', 'dialog');
    jQuery('.alert').attr('role', 'alert');
 
    // Assign ARIA roles for navigation and breadcrumbs
    jQuery('.breadcrumb').attr('role', 'navigation');
    jQuery('.breadcrumb li').attr('role', 'listitem');


    /* ++++++++++++ Accessibility Trigger Controls for Accessibility Plugin ++++++++++++ */
    jQuery('#accessibility-trigger-button').click(function () {
        jQuery(this).parent('.upk-dynamic-design-changer').siblings('#accessibility-popup-box').toggleClass('access-is-open');
    });
    jQuery('#accessibility-popup-close').click(function () {
        jQuery(this).parents('#accessibility-popup-box').toggleClass('access-is-open');
    });
    jQuery('input[type="checkbox"]').click(function () {
        if (jQuery(this).is(':checked')) {
            jQuery(this).parents(".accessibility-accessibility-profile-item").addClass('access-active');
        }
        else {
            jQuery(this).parents(".accessibility-accessibility-profile-item").removeClass('access-active');
        }
    });

    /* ++++++++++++ Accessibility Trigger Controls Epilepsy Mode ++++++++++++ */
    let epilepsyFlag = getCookie('epilepsy_mode') || 0;
    epilepsyFlag = parseInt(epilepsyFlag);

    if (epilepsyFlag === 1) {
        jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-epilepsy"]').addClass('access-active').prop('checked', true);
        jQuery('#accessibility-accessibility-profile-epilepsy').addClass('access-active');
    }
    applyEpilepsyMode(epilepsyFlag);
    // Epilepsy Mode Toggle
    jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-epilepsy"]').click(function () {
        jQuery(this).toggleClass('access-active');
        let setFlag = jQuery(this).hasClass('access-active') ? 1 : 0;
        // Set the epilepsy mode cookie for 7 days
        setCookie('epilepsy_mode', setFlag, 365);

        // Apply or remove epilepsy mode based on the flag
        applyEpilepsyMode(setFlag);

        // Trigger the stop animations button when epilepsy mode is toggled
        if (setFlag === 1) { 
            if (!jQuery('#accessibility-action-black-and-white').hasClass('access-active')) {
                jQuery('#accessibility-action-black-and-white').trigger('click');
            }
            // If epilepsy mode is activated, activate stop animations as well
            jQuery('#accessibility-action-stop-animations').addClass('access-active');

            resetAccessibility('stop-animations', 1, 'none', backgroundColor);
            setCookie('stopAnimationsMode', 1, 365);
        }
        else {
            if (jQuery('#accessibility-action-black-and-white').hasClass('access-active')) {
                jQuery('#accessibility-action-black-and-white').trigger('click');
            }
            // If epilepsy mode is deactivated, deactivate stop animations
            jQuery('#accessibility-action-stop-animations').removeClass('access-active');
            resetAccessibility('stop-animations', 0, '', backgroundColor);
            setCookie('stopAnimationsMode', 0, 365);
        }
    });
    /* ++++++++++++ Accessibility Trigger Controls Visually Impaired Mode ++++++++++++ */
    let cookieValue = getCookie('accessibility-visually-impaired');
    jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-visually-impaired"]').click(function () {
        let setFlag = 0;
        jQuery(this).toggleClass('access-active');
        let valueX = 'none';
        if (jQuery(this).hasClass('access-active')) {
            setFlag = 1;
            setCookie('accessibility-visually-impaired', '1', 365);
        }
        else {
            setCookie('accessibility-visually-impaired', '', 365);
        }
        resetAccessibility('visually-impaired', setFlag, valueX, backgroundColor);
    });
    if (cookieValue) {
        jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-visually-impaired"]').trigger('click') 
    }

    /* ++++++++++++ Accessibility Trigger ADHS Freundlicher Mode ++++++++++++ */
    jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-ADHD-friendly"]').click(function () {
        if (jQuery(this).hasClass('access-active')) {
            jQuery(this).removeClass('access-active');

            // Deactivate actions related to ADHD-friendly mode
            if (jQuery("#accessibility-action-reading-mask").hasClass('access-active')) {
                jQuery("#accessibility-action-reading-mask").trigger('click');
            }

            // Remove cookie when ADHD-friendly mode is disabled
            setCookie('ADHDFriendlyMode', '', 365);
        } 
        else {
            jQuery(this).addClass('access-active');

            // Activate actions related to ADHD-friendly mode
            if (!jQuery("#accessibility-action-reading-mask").hasClass('access-active')) {
                jQuery("#accessibility-action-reading-mask").trigger('click');
            }

            // Set cookie when ADHD-friendly mode is active
            setCookie('ADHDFriendlyMode', 'ADHDFriendlyModeTrigger', 365);
        }
    });
    let adhdModeCookie = getCookie('ADHDFriendlyMode');
    if (adhdModeCookie) {
        jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-ADHD-friendly"]').trigger('click');
    }

    /* ++++++++++++ Accessibility  Online Search ++++++++++++ */
    jQuery('#clear-input-dictionary').click(function () {
        jQuery('.dictionary-input-field').val('');
    });
    jQuery('input[type="text"][name="accessibility-online-dictionary"]').on('input', function () {
        var lang = jQuery('html').attr('lang') ? jQuery('html').attr('lang') : 'en';
        let inputValue = jQuery(this).val();
        if (inputValue.length > 2) {
            var link = 'https://' + lang + '.wikipedia.org/w/api.php';
            jQuery.ajax({
                url: `https://${lang}.wikipedia.org/w/api.php`,
                dataType: 'jsonp',
                data: {
                    action: 'opensearch',
                    format: 'json',
                    search: inputValue
                },
                success: function (data) {
                    jQuery('#accessibility-online-dictionary-result').empty();
                    if (data[1].length) {
                        data[1].forEach(function (item, index) {
                            jQuery('#accessibility-online-dictionary-result').append(`
                                <div class="result-item">
                                    <h6>${item}</h6>
                                    <a href="${data[3][index]}" target="_blank">Read more</a>
                                </div>
                            `);
                        });
                    } else {
                        jQuery('#accessibility-online-dictionary-result').append('<p>No results found</p>');
                    }
                }
            });
        }
        else {
            jQuery('#accessibility-online-dictionary-result').empty();
        }
    });

    /* ++++++++++++ Accessibility Contrast button ++++++++++++ */
    let savedContrast = getCookie('selectedContrast');
    if (savedContrast) {
        jQuery(`#accessibility-action-${savedContrast}-contrast`).addClass('access-active');
    }

    /* ++++++++++++ Accessibility Dark Contrast ++++++++++++ */
    jQuery('#accessibility-action-dark-contrast').click(function () {

        // Initialise local vars
        let setFlag = 0;

        //disible light contrast classes
        jQuery('.accessibility-icon').removeClass('light-contrast-activated');
        jQuery('.accessibility-title').removeClass('light-contrast-activated');

        // Remove selection from siblings
        jQuery('#accessibility-action-light-contrast').removeClass('access-active');

        // Set active/inactive to self
        jQuery(this).toggleClass('access-active');

        removeInlineStyles(jQuery(this));

        let valueX = jQuery(this).data('contrast');
        if (jQuery(this).hasClass('access-active')) {
            setFlag = 1;
            setCookie('darkContrast', 'dark', 365);
            setCookie('lightContrast', '', 365);
        }
        else {
            setCookie('darkContrast', '', -1); // Remove the cookie if deactivated
        }

        resetAccessibility('contrast', setFlag, valueX, backgroundColor);
    });
    let darkContrast = getCookie('darkContrast');
    if(darkContrast == 'dark'){
        jQuery('#accessibility-action-dark-contrast').click();
    }
    else{
        removeInlineStyles(jQuery('#accessibility-action-dark-contrast'));
    }

    /* ++++++++++++ Accessibility Light Contrast ++++++++++++ */
    jQuery('#accessibility-action-light-contrast').click(function () {

        const $this = jQuery(this);

        const title = ('.accessibility-title');
        
        // Initialise local vars
        let setFlag = 0;
        // Remove selection from siblings
        jQuery('#accessibility-action-dark-contrast').removeClass('access-active');

        // Set active/inactive to self
        jQuery(this).toggleClass('access-active');

        removeInlineStyles(jQuery(this));
        let valueX = jQuery(this).data('contrast');
        if (jQuery(this).hasClass('access-active')) {
            setFlag = 1;
            setCookie('lightContrast', 'light', 365);
            setCookie('darkContrast', '', 365);
            // Add class to all .accessibility-icon EXCEPT the one inside this element
            jQuery('.accessibility-icon').not($this.find('.accessibility-icon')).addClass('light-contrast-activated');
            jQuery(title).addClass('light-contrast-activated');
        }
        else {
            setCookie('lightContrast', '', -1); // Remove the cookie if deactivated
            // Remove class from all others except this
            jQuery('.accessibility-icon').not($this.find('.accessibility-icon')).removeClass('light-contrast-activated');
            jQuery(title).removeClass('light-contrast-activated');
        }
        resetAccessibility('contrast', setFlag, valueX, backgroundColor);
    });
    let lightContrast = getCookie('lightContrast');
    if(lightContrast == 'light'){
        jQuery('#accessibility-action-light-contrast').click(); 
    }
    else{
        removeInlineStyles(jQuery('#accessibility-action-dark-contrast'))
    }
    
    /* ++++++++++++ Accessibility Stop animation button ++++++++++++ */
    jQuery('#accessibility-action-stop-animations').click(function () {
        let setFlag = 0;
        let valueX = 'none';

        jQuery(this).toggleClass('access-active');
        if (jQuery(this).hasClass('access-active')) {
            setFlag = 1;
            stopAnimations(1, valueX);
        }
        else {
            stopAnimations(0, '');
        }
        // Update cookie
        setCookie('stopAnimationsMode', setFlag, 365);
    });
    let stopAnimationsCookie = getCookie('stopAnimationsMode');
    if (stopAnimationsCookie === '1') {
        jQuery('#accessibility-action-stop-animations').trigger('click');
    }

    /* ++++++++++++ Mute Sounds button ++++++++++++ */
    initializeYouTubeAPI();
    let muteSoundsCookie = getCookie('muteSoundsMode');

    if (muteSoundsCookie === '1') {
        jQuery('#accessibility-action-mute-sounds').addClass('access-active');
        muteSound(); 
    }
    jQuery('#accessibility-action-mute-sounds').click(function () {
        let setFlag = 0;

        // Toggle the class and state
        jQuery(this).toggleClass('access-active');
        if (jQuery(this).hasClass('access-active')) {
            setFlag = 1;
            muteSound();
        }
        else {
            unmuteSound();
        }
        // Update cookie
        setCookie('muteSoundsMode', setFlag, 365);
    });

    /* ++++++++++++ Black Cursor Button ++++++++++++ */
    jQuery('#accessibility-action-big-black-cursor').click(function () {
        // Reset cookie for big white cursor
        setCookie('bigWhiteCursorMode', '', 365);

        // Initialise local vars
        let setFlag = 0;
        let valueX = 'black';

        // Remove selection from siblings (e.g., other cursor options)
        jQuery('#accessibility-action-big-white-cursor').removeClass('access-active');

        // Set active/inactive to self
        jQuery(this).toggleClass('access-active');

        // If active, set the flag and value for a big black cursor
        if (jQuery(this).hasClass('access-active')) {
            setFlag = 1;
            valueX = 'black';
            setCookie('bigBlackCursorMode', 'black', 365);
        } 
        else {
            setFlag = 0;
            valueX = 'default';
            setCookie('bigBlackCursorMode', '', 365);
        }
        resetBlackCursorAccessibility('highlight-cursor', setFlag, valueX);        
    });

    let bigBlackCursorCookie = getCookie('bigBlackCursorMode');
    if (bigBlackCursorCookie) {
        jQuery('#accessibility-action-big-black-cursor').trigger('click');
    }

    /* ++++++++++++ White Cursor Button ++++++++++++ */
    jQuery('#accessibility-action-big-white-cursor').click(function () {
        // Reset cookie for big black cursor
        setCookie('bigBlackCursorMode', '', 365);

        // Initialise local vars
        let setFlag = 0;
        let valueX = 'white';

        // Remove selection from siblings (e.g., other cursor options)
        jQuery('#accessibility-action-big-black-cursor').removeClass('access-active');

        // Set active/inactive to self
        jQuery(this).toggleClass('access-active');

        // If active, set the flag and value for a big white cursor
        if (jQuery(this).hasClass('access-active')) {
            setFlag = 1;
            valueX = 'white';
            setCookie('bigWhiteCursorMode', 'white', 365);
        }
        else {
            setFlag = 0;
            valueX = 'default';
            setCookie('bigWhiteCursorMode', '', 365);
        }

        resetWhiteCursorAccessibility('highlight-cursor', setFlag, valueX);
    });
    let bigWhiteCursorCookie = getCookie('bigWhiteCursorMode');
    if (bigWhiteCursorCookie){
        jQuery('#accessibility-action-big-white-cursor').trigger('click');
    }

    /* ++++++++++++ Hide Image Button ++++++++++++ */
    const hideImagesCookie = getCookie('hideImagesMode');
    if (hideImagesCookie === '1') {
        jQuery('#accessibility-action-hide-images').addClass('access-active');
        toggleImages(1); 
    }
    jQuery('#accessibility-action-hide-images').click(function () {
        jQuery(this).toggleClass('access-active');
        const setFlag = jQuery(this).hasClass('access-active') ? 1 : 0;
        toggleImages(setFlag);
        // Update cookie
        setCookie('hideImagesMode', setFlag, 365);
    });

    /* ++++++++++++ Readable Guide Element Button ++++++++++++ */
    let newReadingGuide = jQuery('<div class="accessibility-reading-guide-element"></div>');

    jQuery('#accessibility-action-reading-guide').click(function () {
        // Set active/inactive to self
        jQuery(this).toggleClass('access-active');

        if (jQuery(this).hasClass('access-active')) {
            // Check if the reading guide already exists
            if (!jQuery('.accessibility-reading-guide-element').length) {
                // Append the reading guide only if it doesn't exist
                jQuery('body').append(newReadingGuide);
            }
            // Update position of reading guide on mouse move
            jQuery(document).on('mousemove.readingGuide', function (e) {
                updateReadingGuidePosition(e, newReadingGuide);
            });
            // Set cookie to remember the state for 365 days
            setCookie('readingGuideActive', 'true', 365);
        }
        else {
            // Remove reading guide directly here
            newReadingGuide.remove(); // This will remove the existing line
            jQuery(document).off('mousemove.readingGuide'); // Remove mousemove event handler

            // Set cookie to remember the state as inactive
            setCookie('readingGuideActive', 'false', 365);
        }

        resetAccessibility('reading-guides', jQuery(this).hasClass('access-active') ? 1 : 0, 'none', backgroundColor);
    });
    // Check cookie for reading guide state
    if (getCookie('readingGuideActive') === 'true') {
        jQuery('#accessibility-action-reading-guide').trigger('click');
    }

    /* ++++++++++++ Accessibility Button Reading Mask Mode ++++++++++++ */
    jQuery('#accessibility-action-reading-mask').click(function () {
        jQuery(this).toggleClass('access-active');
        let setReadingMask = jQuery(this).hasClass('access-active') ? '1' : '0';

        if (setReadingMask === '1') {
            createReadingMask();
            setCookie('ReadingMaskMode', 'ReadingMaskMode', 365);

            // Ensure ADHD-friendly mode is activated if reading mask is enabled
            if (!jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-ADHD-friendly"]').hasClass('access-active')) {
                jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-ADHD-friendly"]').trigger('click');
            }
        } 
        else {
            removeReadingMask();
            setCookie('ReadingMaskMode', '', 365);

            // Ensure ADHD-friendly mode is deactivated if reading mask is disabled
            if (jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-ADHD-friendly"]').hasClass('access-active')) {
                jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-ADHD-friendly"]').trigger('click');
            }
        }
    });
    let readingMaskCookie = getCookie('ReadingMaskMode');
    if (readingMaskCookie) {
        jQuery('#accessibility-action-reading-mask').trigger('click')
    }

    /* ++++++++++++ Accessibility Button Text to Speech Mode ++++++++++++ */
    if (ttsEnabled) {
        jQuery('#accessibility-action-text-to-speech').addClass('access-active');
    }
    jQuery('#accessibility-action-text-to-speech').click(function () {
        ttsEnabled = !ttsEnabled;
        jQuery(this).toggleClass('access-active');
        if (!ttsEnabled && synth.speaking) {
            synth.cancel();
        }
        // Save state in cookie
        setCookie('ttsEnabled', ttsEnabled ? '1' : '0', 365);
    });
    jQuery('p, h1, h2, h3, h4, h5, h6, span, li, button, a').click(function () {
        if (ttsEnabled) {
            const text = jQuery(this).text();
            textToSpeech(text, siteLang);
        }
    });

    /* ++++++++++++ Accessibility Button Negative Contrast Mode ++++++++++++ 
    
    Not Added
    
    
    */


    /* ++++++++++++ Accessibility Button Cognitive Riding Mode ++++++++++++ */
    jQuery('#accessibility-action-cognitive-reading').click(function () {
        if (jQuery(this).hasClass('access-active')) {
            jQuery(this).removeClass('access-active');

            // Trigger actions if active and remove their 'access-active' state
            if (jQuery("#accessibility-action-stop-animations").hasClass('access-active')) {
                jQuery("#accessibility-action-stop-animations").trigger('click');
            }
            if (jQuery("#accessibility-action-highlight-links").hasClass('access-active')) {
                jQuery("#accessibility-action-highlight-links").trigger('click');
            }
            if (jQuery("#accessibility-action-highlight-titles").hasClass('access-active')) {
                jQuery("#accessibility-action-highlight-titles").trigger('click');
            }
            if (jQuery("#accessibility-action-highlight-hover").hasClass('access-active')) {
                jQuery("#accessibility-action-highlight-hover").trigger('click');
            }
            if (jQuery("#accessibility-action-readable-font").hasClass('access-active')) {
                jQuery("#accessibility-action-readable-font").trigger('click');
            }

            // Set cookie to false when Cognitive Reading is disabled
            setCookie('cognitiveReadingActive', 'false', 365);
        }
        else {
            jQuery(this).addClass('access-active');

            // Activate all related actions
            if (!jQuery("#accessibility-action-readable-font").hasClass('access-active')) {
                jQuery("#accessibility-action-readable-font").trigger('click');
            }
            if (!jQuery("#accessibility-action-stop-animations").hasClass('access-active')) {
                jQuery("#accessibility-action-stop-animations").trigger('click');
            }
            if (!jQuery("#accessibility-action-highlight-links").hasClass('access-active')) {
                jQuery("#accessibility-action-highlight-links").trigger('click');
            }
            if (!jQuery("#accessibility-action-highlight-hover").hasClass('access-active')) {
                jQuery("#accessibility-action-highlight-hover").trigger('click');
            }

            // Set cookie to true when Cognitive Reading is active
            setCookie('cognitiveReadingActive', 'true', 365);
        }
    });
    initializeCognitiveReading();

    /* ++++++++++++ Accessibility Key board nevigation Mode ++++++++++++ */
    let currentIndex = -1;
    let currentFormIndex = -1;
    let currentNavIndex = -1;
    let currentButtonIndex = -1;

    // Find headlines, skipping empty or only non-breaking space headlines
    let findHeadlines = jQuery("h1, h2, h3, h4, h5, h6").filter(function () {
        return jQuery(this).text().trim() !== "" && jQuery(this).html().trim() !== "&nbsp;";
    });

    let findNavs = jQuery("nav");
    let findFormElements = jQuery("form, input[type='text']");
    let fineButtonsElements = jQuery(".accessibility-action-box, .accessibility-action-box button, .accessibility-color");
    let findLogo = jQuery('img[data-logo="1"]');

    // Check cookie to determine if keyboard navigation should be active
    const isKeyboardNavActive = getCookie("keyboardNavActive") === "true";
    jQuery("#accessibility-action-keyboard-navigation").toggleClass("access-active", isKeyboardNavActive);
    if (isKeyboardNavActive) {
        activateKeyboardNavigation();
    }

    // Click event for the keyboard navigation toggle button
    jQuery("#accessibility-action-keyboard-navigation").on("click", function () {
        jQuery(this).toggleClass("access-active");
        const isActive = jQuery(this).hasClass("access-active");
        setCookie("keyboardNavActive", isActive, 364); // Set the cookie to remember the state

        if (isActive) {
            activateKeyboardNavigation();
        }
        else {
            deactivateKeyboardNavigation();
        }
    });

    // Function to activate keyboard navigation
    function activateKeyboardNavigation() {
        jQuery(document).on("keypress.navigation", function (e) {
            if (e.key === 'h') {

                removeAllHighlights();
                currentIndex = (currentIndex + 1) % findHeadlines.length;
                jQuery(findHeadlines[currentIndex]).addClass("active-highlight");
            }
            else if (e.key === 'm') {
                removeAllHighlights();
                currentNavIndex = (currentNavIndex + 1) % findNavs.length;
                jQuery(findNavs[currentNavIndex]).addClass("active-highlight");
            }
            else if (e.key === 'f') {
                removeAllHighlights();
                currentFormIndex = (currentFormIndex + 1) % findFormElements.length;
                jQuery(findFormElements[currentFormIndex]).addClass("active-highlight");
            }
            else if (e.key === 'b') {
                removeAllHighlights();

                var currentButtonBox = jQuery(fineButtonsElements[currentButtonIndex]);

                // Check if the current box has a button or accessibility-color as a child
                var focusableChild = currentButtonBox.find('button, .accessibility-color').first();

                if (focusableChild.length > 0) {

                    // If a button or accessibility-color is found, focus on it
                    focusableChild.addClass("active-highlight-focus").focus();
                    currentButtonBox.addClass("active-highlight-focus").attr('tabindex', '0').focus();
                }
                else {
                    // If no button or accessibility-color is found, focus on the current box itself
                    currentButtonBox.addClass("active-highlight-focus").attr('tabindex', '0').focus();
                }

                // Move to the next element after the focus has been applied
                currentButtonIndex = (currentButtonIndex + 1) % fineButtonsElements.length;
            }

            else if (e.key === 'g') {
                removeAllHighlights();
                findLogo.addClass("active-highlight"); // Highlight the logo image
            }
        });
    }
    // Handle 'Enter' key for button click or box click
    jQuery(document).on("keydown", function (e) {
        if (e.key === 'Enter') {
            var focusedElement = jQuery(document.activeElement);

            if (focusedElement.is(".accessibility-action-box, .accessibility-action-box button")) {
                // If focused on a button, click the button
                if (focusedElement.is("button")) {
                    focusedElement.click();
                } else {
                    // Otherwise, click the box itself if no button is present
                    focusedElement.find("button").first().click(); // Click the button inside if exists
                    if (focusedElement.find("button").length === 0) {
                        focusedElement.click(); // If no button is found, click the box
                    }
                }
            }
        }
    });
    // Function to deactivate keyboard navigation
    function deactivateKeyboardNavigation() {
        jQuery(document).off("keypress.navigation");
        removeAllHighlights();
    }
    // Function to remove all highlights
    function removeAllHighlights() {
        findHeadlines.removeClass("active-highlight");
        findNavs.removeClass("active-highlight");
        findFormElements.removeClass("active-highlight");
        fineButtonsElements.removeClass("active-highlight-focus");
        findLogo.removeClass("active-highlight");
    }

    /* ++++++++++++ Accessibility Content Scaling Adjuster Action Controls Mode ++++++++++++ */
    zoomFactor = parseFloat(getCookie('zoomFactor')) || 1;
    contentScaleDisplay = jQuery('#scale-display-content-scaling');

    // Apply the initial zoom factor
    jQuery('body').css('zoom', zoomFactor);
    updateContentScaleDisplay();

    // Decrease content scaling
    jQuery('#decrease-content-scaling').click(function () {
        zoomFactor -= 0.05;
        jQuery('body').css('zoom', zoomFactor);
        setCookie('zoomFactor', zoomFactor, 365);
        updateContentScaleDisplay();
    });

    // Increase content scaling
    jQuery('#increase-content-scaling').click(function () {
        zoomFactor += 0.05;
        jQuery('body').css('zoom', zoomFactor);
        setCookie('zoomFactor', zoomFactor, 365);
        updateContentScaleDisplay();
    });

    /* ++++++++++++ Accessibility High contrast Action Controls Button ++++++++++++ */
    jQuery('#accessibility-action-high-contrast').click(function () {

        let setFlag = 0;

        // Set active/inactive to self
        jQuery(this).toggleClass('access-active');

        let valueX = 'none';
        if (jQuery(this).hasClass('access-active')) {
            setFlag = 1;
            if (jQuery('#accessibility-action-black-and-white').hasClass('access-active')) {
                jQuery('#accessibility-action-black-and-white').trigger('click');
            }
            setCookie('accessibility-action-high-contrast', '1', 365);
        }
        else {
            setCookie('accessibility-action-high-contrast', '', 365);
        }

        resetAccessibility('high-contrast', setFlag, valueX, backgroundColor);
    });
    let highContrast = getCookie('accessibility-action-high-contrast');
    if (highContrast) {
        jQuery('#accessibility-action-high-contrast').trigger('click');
    }
    /* ++++++++++++ Accessibility Trigger Black and white Mode ++++++++++++ */
    jQuery('#accessibility-action-black-and-white').click(function () {
        jQuery(this).toggleClass('access-active');
        let setBlackWhite = jQuery(this).hasClass('access-active') ? '1' : '0';
        
        if(setBlackWhite == '1') {
            setCookie('blackAndWhite', setBlackWhite, 365);
            if (!jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-epilepsy"]').hasClass('access-active')) {
                jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-epilepsy"]').trigger('click');
            }
            if (jQuery('#accessibility-action-high-contrast').hasClass('access-active')) {
                jQuery('#accessibility-action-high-contrast').trigger('click');
            }
        }
        else{
            setCookie('blackAndWhite', '', 365);
            if (jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-epilepsy"]').hasClass('access-active')) {
                jQuery('input[type="checkbox"][name="accessibility-accessibility-profile-epilepsy"]').trigger('click');
            }
        }
    });
    let blackAndWhiteCookie = getCookie('blackAndWhite');
    if (blackAndWhiteCookie) {
        jQuery('#accessibility-action-black-and-white').addClass('access-active');
    }
    /* ++++++++++++ Accessibility Text Magnifier Adjuster Action Controls Mode ++++++++++++ */
    let magnifierEnabled = (getCookie('magnifierEnabled') === 'true');

    // Update button state based on cookie
    if (magnifierEnabled) {
        jQuery('#accessibility-action-text-magnifier').addClass('access-active');
    }
    // Toggle magnifier state on button click
    jQuery('#accessibility-action-text-magnifier').click(function () {
        magnifierEnabled = !magnifierEnabled;
        jQuery(this).toggleClass('access-active');
        setCookie('magnifierEnabled', magnifierEnabled, 365);

        if (!magnifierEnabled) {
            jQuery('#accessibility-magnifier').hide();
        }
    });

    // Display magnified text near the cursor when magnifier is enabled
    jQuery(document).on('mousemove', 'p, h1, h2, h3, h4, h5, h6, a, li, span, button', function (event) {
        if (magnifierEnabled) {
            let targetText = jQuery(event.target).text().trim();
            if (targetText !== "") {
                jQuery('#accessibility-magnifier').css({
                    top: event.pageY + 10 + 'px',
                    left: event.pageX + 10 + 'px'
                }).text(targetText).show();
            }
            else {
                jQuery('#accessibility-magnifier').hide();
            }
        }
    });

    // Hide magnifier when the cursor leaves the target elements
    jQuery(document).on('mouseleave', 'p, h1, h2, h3, h4, h5, h6, a, li, span, button', function (event) {
        if (magnifierEnabled) {
            jQuery('#accessibility-magnifier').hide();
        }
    });

    /* ++++++++++++ Accessibility Arial Font Adjuster Action Controls Mode ++++++++++++ */
    let savedReadableFont = getCookie('selectedReadableFont');
    if (savedReadableFont === 'arial') {
        readableFontAction(savedReadableFont, backgroundColor);
    }
    jQuery('#accessibility-action-readable-font').click(function () {
        let valueX = 'arial';
        readableFontAction(valueX, backgroundColor);
    });

    /* ++++++++++++ Accessibility Dyslexia Font Adjuster Action Controls Mode ++++++++++++ */
    let savedDyslexiaFont = getCookie('selectedDyslexiaFont');
    if (savedDyslexiaFont === 'OpenDyslexic') {
        dyslexiaFontAction(savedDyslexiaFont, backgroundColor);
    }
    // Handle click event for dyslexia font selection
    jQuery('#accessibility-action-dyslexia-font').click(function () {
        let valueX = 'OpenDyslexic';
        dyslexiaFontAction(valueX, backgroundColor);
    });

    /* ++++++++++++ Accessibility Highlight Title Action Controls Mode ++++++++++++ */
    jQuery('#accessibility-action-highlight-titles').click(function () {
        // Initialise local vars
        let setFlag = 0;
        let valueX = jQuery(this).data('highlight'); // Custom highlight value (color, background, etc.)

        // Set active/inactive to self
        jQuery(this).toggleClass('access-active');

        // Set flag if the button is active
        if (jQuery(this).hasClass('access-active')) {
            setFlag = 1;
        }

        resetHighlightTitleAccessibility('highlight-titles', setFlag, valueX);

        // Set cookie for highlight titles mode
        setCookie('highlightTitlesMode', setFlag, 365);
    });

    // On page load, check if the highlight titles mode is active via cookie
    let highlightTitlesCookie = getCookie('highlightTitlesMode');
    if (highlightTitlesCookie === '1') {
        jQuery('#accessibility-action-highlight-titles').trigger('click');
    }

    /* ++++++++++++ Accessibility Highlight Link Action Controls Mode ++++++++++++ */
    let savedHighlight = getCookie('highlightLinks');

    // Handle click event for highlight links
    jQuery('#accessibility-action-highlight-links').click(function () {
        // Initialise local vars
        let setFlag = 0;

        // Set active/inactive to self
        jQuery(this).toggleClass('access-active');
        let valueX = jQuery(this).data('highlight');
        if (jQuery(this).hasClass('access-active')) {
            setFlag = 1;
            setCookie('highlightLinks', 'active', 365);
        } 
        else {
            setCookie('highlightLinks', '', -1); 
        }
        resetAccessibility('highlight-links', setFlag, valueX, backgroundColor);
    });
    if (savedHighlight ) {
        jQuery('#accessibility-action-highlight-links').trigger('click');
    }

    /* ++++++++++++ Accessibility Font Size  Action Controls Mode ++++++++++++ */
    jQuery('h1, h2, h3, h4, h5, h6, a, p, button, li, span').each(function () {
        let element = jQuery(this);
        let tag = element.prop("tagName");


        // Store the initial size if not already stored
        if (!initialFontSizes[tag]) {
            initialFontSizes[tag] = parseFloat(element.css('font-size'));
        }
    });
    applyFontSizeAdjustments();
    // Increase font size
    jQuery("#increase-fontsize-adjuster").click(function () {
        totalFontSizeClicks++; // Increase on click
        setCookie('fontSizeClicks', totalFontSizeClicks, 365); // Store the updated value in the cookie (7 days)
        applyFontSizeAdjustments();
    });
    jQuery("#decrease-fontsize-adjuster").click(function () {
        totalFontSizeClicks--; // Decrease on click
        setCookie('fontSizeClicks', totalFontSizeClicks, 365); // Store the updated value in the cookie (365 days)
        applyFontSizeAdjustments();
    });

    /* ++++++++++++ Accessibility Line Height  Action Controls Mode ++++++++++++ */
    jQuery('h1, h2, h3, h4, h5, h6, a, p, button, li, span').each(function () {
        let element = jQuery(this);
        let tag = element.prop("tagName");

        // Store the initial line height if not already stored
        if (!initialLineHeight[tag]) {
            initialLineHeight[tag] = parseFloat(element.css('line-height'));
        }
    });
    applyLineHeightAdjustments();
    // Increase line height
    jQuery('#increase-line-height-adjuster').click(function () {
        totalLineHeightClicks++; // Increase on click
        setCookie('lineHeightClicks', totalLineHeightClicks, 365); // Store the updated value in the cookie (365 days)
        applyLineHeightAdjustments();
    });
    // Decrease line height
    jQuery("#decrease-line-height-adjuster").click(function () {
        totalLineHeightClicks--; // Decrease on click
        setCookie('lineHeightClicks', totalLineHeightClicks, 365); // Store the updated value in the cookie (365 days)
        applyLineHeightAdjustments();
    });

    /* ++++++++++++ Accessibility Letter Spaching Action Controls Mode ++++++++++++ */
    jQuery('*').filter('h1, h2, h3, h4, h5, h6, a, p, button, li, span').each(function () {
        let element = jQuery(this);
        let tag = this.tagName; // Use native `tagName` for better performance

        if (!(tag in initialLetterSpacing)) {
            let letterSpacing = element.css('letter-spacing');
            initialLetterSpacing[tag] = letterSpacing !== 'normal' ? parseFloat(letterSpacing) : 0;
        }
    });

    // Apply the saved letter spacing adjustments on page load (only if the user has modified the spacing)
    applyLetterSpacingAdjustments();

    // Increase letter spacing
    jQuery('#increase-letter-spacing-adjuster').click(function () {
        totalLetterSpacingClicks++; // Increase on click
        setCookie('letterSpacingClicks', totalLetterSpacingClicks, 365); // Store the updated value in the cookie (365 days)
        applyLetterSpacingAdjustments();
    });

    // Decrease letter spacing
    jQuery("#decrease-letter-spacing-adjuster").click(function () {
        totalLetterSpacingClicks--; // Decrease on click
        setCookie('letterSpacingClicks', totalLetterSpacingClicks, 365); // Store the updated value in the cookie (365 days)
        applyLetterSpacingAdjustments();
    });

    /* +++++++++++++++  Accessibility Link Navigation Controls   ++++++++++*/
    const $selectElement = jQuery('#useful-links');

    // Extensions to skip (images, optional others)
    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp', '.bmp', '.tif', '.tiff'];
    const skipExtensions = [...imageExtensions];

    // Skip if href starts with these (e.g., mailto, tel, javascript)
    const skipPrefixes = ['mailto:', 'tel:', 'javascript:'];

    jQuery('a').each(function () {
        const $link = jQuery(this);
        const href = $link.attr('href');

        // Skip if not visible or hidden by any parent
        if (
            !$link.is(':visible') ||
            $link.parents().filter(function () {
                const $p = jQuery(this);
                return $p.css('display') === 'none' || $p.css('visibility') === 'hidden';
            }).length > 0
        ) {
            return;
        }

        // Skip empty href or just "#"
        if (!href || href.trim() === '' || href === '#') {
            return;
        }

        // Skip if starts with disallowed prefixes
        const hrefLower = href.toLowerCase();
        if (skipPrefixes.some(prefix => hrefLower.startsWith(prefix))) {
            return;
        }

        // Skip if URL points to an image
        if (skipExtensions.some(ext => hrefLower.includes(ext))) {
            return;
        }

        // Determine display text: title → fallback cleaned href
        let text = $link.attr('title')?.trim();

        if (!text) {
            try {
                const url = new URL(href, window.location.href); // handle relative URLs
                const parts = url.pathname.split('/');
                let lastPart = parts.pop() || parts.pop(); // avoid trailing slash
                lastPart = lastPart.replace(/\.(html?|php|aspx?|htm)$/i, ''); // remove file extensions
                text = decodeURIComponent(lastPart);
            } catch (e) {
                text = href;
            }
        }

        if (text) {
            const option = jQuery('<option></option>').attr('value', href).text(text);
            $selectElement.append(option);
        }
    });
    $selectElement.on('change', function () {
        const selectedOption = jQuery(this).find('option:selected');
        if (selectedOption && selectedOption.val() !== 'default') {
            const link = selectedOption.val();
            navigateToLink(link);
        }
    });
    
    /* ++++++++++++ Accessibility Highlight Focus Action Controls Mode ++++++++++++ */
    jQuery('#accessibility-action-highlight-focus').click(function () {
        jQuery(this).toggleClass('access-active');
        let setHighlightFocus = jQuery(this).hasClass('access-active') ? '1' : '0';
        setCookie('highlightFocus', setHighlightFocus, 365);
        if(setHighlightFocus == '1'){
            initializeHighlightFocus();
        }
        else{
            setCookie('highlightFocus', setHighlightFocus, 365);
            jQuery('input, select, textarea, a, button').css('border', '');
            removeHighlightFocus();
        }
    });

    let savedHighlightFocus = getCookie('highlightFocus');
        if(savedHighlightFocus == 1){
        jQuery('#accessibility-action-highlight-focus').trigger('click');
    }

    /* ++++++++++++ Accessibility Mark hover  Action Controls Mode ++++++++++++ */
    jQuery('#accessibility-action-highlight-hover').click(function () {
        jQuery(this).toggleClass('access-active');
        let setHighlightHover = jQuery(this).hasClass('access-active') ? '1' : '0';
        setCookie('highlightHover', setHighlightHover, 365);
        if(setHighlightHover == '1'){
            initializeMarkHover();
        }
        else{
            setCookie('highlightHover', setHighlightHover, 365);
            jQuery('*').css({
                'outline': '',
                'outline-offset': ''
            });
            removeMarkHover();
        }
    });
    let savedHighlightHover = getCookie('highlightHover');
    if(savedHighlightHover == 1){
       jQuery('#accessibility-action-highlight-hover').trigger('click');
    }

    /* ++++++++++++ Accessibility Aling  Action Controls Mode ++++++++++++ */
    let savedAlignment = getCookie('selectedAlignment');

    // Attach the click handler first
    jQuery(`#accessibility-action-align-left`).click(function () {
        // Remove selection from siblings
        jQuery('#accessibility-action-align-center').removeClass('access-active');
        jQuery('#accessibility-action-align-right').removeClass('access-active');

        // Get the alignment value and apply it
        let valueX = jQuery(this).data('align');
        alignmentTextItems(valueX, backgroundColor);
    });
    // Handle click event for center alignment selection
    jQuery(`#accessibility-action-align-center`).click(function () {
        // Remove selection from siblings
        jQuery('#accessibility-action-align-left').removeClass('access-active');
        jQuery('#accessibility-action-align-right').removeClass('access-active');

        // Get the alignment value and apply it
        let valueX = jQuery(this).data('align');
        alignmentTextItems(valueX, backgroundColor);
    });
    jQuery(`#accessibility-action-align-right`).click(function () {
        // Remove selection from siblings
        jQuery('#accessibility-action-align-left').removeClass('access-active');
        jQuery('#accessibility-action-align-center').removeClass('access-active');

        // Get the alignment value and apply it
        let valueX = jQuery(this).data('align');
        alignmentTextItems(valueX, backgroundColor);
    });
    // Trigger the click event programmatically after attaching the handler
    if (savedAlignment != null) {
        jQuery(`#accessibility-action-align-${savedAlignment}`).trigger('click');
    }

    /* ++++++++++++ Accessibility All Text Color Action Controls Mode ++++++++++++ */
    let savedColor = getCookie('selectedTextColor');
    if (savedColor) {
        allTextColor(savedColor, backgroundColor); 
    }
    // Handle click event for color selection
    jQuery('#accessibility-action-alltext-colors .accessibility-color').click(function () {
        const colorValue = jQuery(this).data('color'); 
        allTextColor(colorValue, backgroundColor);  
    });

    /* ++++++++++++ Accessibility Text Color Action Controls Mode ++++++++++++ */
    let savedTextColor = getCookie('textcolor');
    if (savedTextColor) {
        textColor(savedTextColor, backgroundColor);
    }

    // Handle click event for text color selection
    jQuery('#accessibility-action-text-colors .accessibility-color').click(function () {
        const colorValue = jQuery(this).data('color'); 
        textColor(colorValue, backgroundColor); 
    });

    /* ++++++++++++ Accessibility Title Color Action Controls Mode ++++++++++++ */
    let savedTitleColor = getCookie('selectedTitleColor');
    if (savedTitleColor) {
        titleColorAction(savedTitleColor, backgroundColor); 
    }
    // Handle click event for title color selection
    jQuery('#accessibility-action-title-colors .accessibility-color').click(function () {
        const colorValue = jQuery(this).data('color'); 
        titleColorAction(colorValue, backgroundColor); 
    });

    /* ++++++++++++ Accessibility Link Color Action Controls Mode ++++++++++++ */
    let savedLinkColor = getCookie('selectedLinkColor');
    if (savedLinkColor) {
        linkColorAcrion(savedLinkColor, backgroundColor); 
    }
    // Handle click event for link color selection
    jQuery('#accessibility-action-link-colors .accessibility-color').click(function () {
        const colorValue = jQuery(this).data('color'); 
        linkColorAcrion(colorValue, backgroundColor); 
    });

    /* ++++++++++++ Accessibility Background Color Action Controls Mode ++++++++++++ */
    let savedBgColor = getCookie('selectedBgColor');
    if (savedBgColor) {
       backgroundColorAction(savedBgColor, backgroundColor); 
    }
    // Handle click event for background color selection
    jQuery('#accessibility-action-background-colors .accessibility-color').click(function () {
        const colorValue = jQuery(this).data('color'); 
        backgroundColorAction(colorValue, backgroundColor); 
    });

    /* ++++++++++++ Accessibility Reset and Hide button Action Controls Mode ++++++++++++ */
    jQuery('#accessibility-reset-btn').on("click", function (e) {
        e.preventDefault(); 
        e.stopPropagation();
        // Get all cookies
        var cookies = document.cookie.split(";");
        // Loop through and delete each cookie
        for (var i = 0; i < cookies.length; i++) {
            var cookie = cookies[i];
            var eqPos = cookie.indexOf("=");
            var name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
            document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        }
        location.reload();
    });
    jQuery('#accessibility-hide-btn').click(function () {
        resetAccessibility('disableAccessibility', 1, 1, backgroundColor);
        setTimeout(function () {
            window.location.reload();
        }, 2000);
    });

});