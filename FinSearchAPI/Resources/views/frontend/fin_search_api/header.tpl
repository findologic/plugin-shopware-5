{block name="frontend_index_header_javascript_modernizr_lib" prepend}
    <script
        type="text/javascript"
        src="https://cdn.findologic.com/static/loader.min.js"
        data-fl-main="{$mainUrl}"
    ></script>
{/block}

{block name="frontend_index_header_css_screen" append}
    <link
        type="text/css"
        media="all"
        rel="stylesheet"
        href="{link file='custom/plugins/FinSearchAPI/Resources/views/frontend/_resources/css/smartsuggest.css'}"
    />
{/block}