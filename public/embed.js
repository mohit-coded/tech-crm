/*
 * Tech CRM funnel embed.
 *
 * Usage, on any website:
 *   <script src="https://<this app>/embed.js" data-slug="your-funnel-slug"></script>
 *
 * Inserts an iframe showing /f/{slug}/embed directly after the <script> tag.
 * The app's origin is read from this script's own src, so the snippet works
 * on any deployment without a hardcoded domain.
 *
 * Sizing: the width is responsive with no JS (100% of the container, capped
 * at 480px, so it shrinks on phones and never gets wider than a comfortable
 * form). The height is a fixed 560px, enough for the headline, a two-line
 * subheadline, three fields and the button without an inner scrollbar.
 * Override it with data-height="640" if a funnel's copy is longer. A height
 * that fits the content automatically would need the framed page to report
 * its height through postMessage, which is left for later.
 */
(function () {
    var script = document.currentScript;

    // currentScript is null when the tag is loaded with async or defer.
    // In that case, fall back to the first matching tag not yet handled.
    if (!script) {
        script = document.querySelector('script[data-slug][src*="embed.js"]:not([data-techcrm-embedded])');
    }

    if (!script || script.hasAttribute('data-techcrm-embedded')) {
        return;
    }

    script.setAttribute('data-techcrm-embedded', '');

    var slug = script.getAttribute('data-slug');

    if (!slug) {
        console.error('Tech CRM embed: missing data-slug attribute on the <script> tag.');
        return;
    }

    var origin = new URL(script.src, window.location.href).origin;
    var height = parseInt(script.getAttribute('data-height'), 10) || 560;

    var iframe = document.createElement('iframe');
    iframe.src = origin + '/f/' + encodeURIComponent(slug) + '/embed';
    iframe.title = 'Offer form';
    iframe.loading = 'lazy';
    iframe.setAttribute('frameborder', '0');
    iframe.style.cssText = [
        'display:block',
        'width:100%',
        'max-width:480px',
        'height:' + height + 'px',
        'border:0',
        'border-radius:12px',
        'overflow:hidden',
    ].join(';');

    script.parentNode.insertBefore(iframe, script.nextSibling);
})();
