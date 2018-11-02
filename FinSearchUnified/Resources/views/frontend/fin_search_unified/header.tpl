{block name="frontend_index_header_javascript_modernizr_lib" prepend}
    <script type="text/javascript">
        (function() {
            var mainUrl = 'https://cdn.findologic.com/static/{$hashedShopkey}/main.js?usergrouphash={$hash}';
            var loader = document.createElement('script');
            loader.type = 'text/javascript';
            loader.async = true;
            loader.src = 'https://cdn.findologic.com/static/loader.min.js';
            var s = document.getElementsByTagName('script')[0];
            loader.setAttribute('data-fl-main', mainUrl);
            s.parentNode.insertBefore(loader, s);
        })();
    </script>
{/block}

{block name="frontend_index_header_css_screen" append}
    <link
        type="text/css"
        media="all"
        rel="stylesheet"
        href="{link file='custom/plugins/FinSearchUnified/Resources/views/frontend/_resources/css/smartsuggest.css'}"
    />
    <link
        type="text/css"
        media="all"
        rel="stylesheet"
        href="{link file='custom/plugins/FinSearchUnified/Resources/views/frontend/_resources/css/findologic.css'}"
    />
{/block}