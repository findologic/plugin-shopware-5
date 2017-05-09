{block name="frontend_index_header_javascript_modernizr_lib" prepend}
<script type="text/javascript">
(function() {
    var placeHolder1 = "{$placeholder1}";
    var placeHolder2 = "{if $placeholder2 != ''}?usergrouphash=P{$placeholder2}{/if}";

    var mainUrl = "https://cdn.findologic.com/static/"+ placeHolder1 +"/main.js" + placeHolder2;

    var loader = document.createElement('script');
    loader.type = 'text/javascript';
    loader.async = true;
    loader.src = "https://cdn.findologic.com/static/loader.min.js";
    var s = document.getElementsByTagName('script')[0];
    loader.setAttribute('data-fl-main', mainUrl);
    s.parentNode.insertBefore(loader, s);
})();
</script>
{/block}  

