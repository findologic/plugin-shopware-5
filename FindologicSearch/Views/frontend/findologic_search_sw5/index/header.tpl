{block name="frontend_index_header_javascript_modernizr_lib" prepend}
<script type="text/javascript">
    (function() {
        var flDataMain = "https://cdn.findologic.com/autocomplete/{$placeholder1}/autocomplete.js{if $placeholder2 != ''}?usergrouphash={$placeholder2}{/if}";
        var flAutocomplete = document.createElement('script');
        flAutocomplete.type = 'text/javascript';
        flAutocomplete.async = true;
        flAutocomplete.src = "https://cdn.findologic.com/autocomplete/require.js";
        var s = document.getElementsByTagName('script')[0];
        flAutocomplete.setAttribute('data-main', flDataMain);
        s.parentNode.insertBefore(flAutocomplete, s);
    })();
</script>
{/block}  

