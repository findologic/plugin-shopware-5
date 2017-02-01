{block name="frontend_index_header_javascript_modernizr_lib" prepend}
    <script type="text/javascript">
    (function() {
        var mainUrl = "https://cdn.findologic.com/static/{$placeholder1}/main.js{if $placeholder2 != ''}?usergrouphash=P{$placeholder2}{/if}";
        var loader = document.createElement('script');
        loader.type = 'text/javascript';
        loader.async = true;
        loader.src = "https://cdn.findologic.com/static/loader.min.js";
        var s = document.getElementsByTagName('script')[0];
        loader.setAttribute('data-fl-main', mainUrl);
        s.parentNode.insertBefore(loader, s);
    })();
    </script>

    <script type="text/javascript">
    var fl_paq = fl_paq || [];
    (function(){ var u=(("https:" == document.location.protocol) ? "https://tracking.findologic.com/" : "http://tracking.findologic.com/");
    fl_paq.push(['setSiteId', '{$placeholder1}']);
    fl_paq.push(['setTrackerUrl', u+'tracking.php']);
    fl_paq.push(['trackPageView']);
    fl_paq.push(['enableLinkTracking']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0]; g.type='text/javascript'; g.defer=true; g.async=true; g.src=u+'tracking.js';
    s.parentNode.insertBefore(g,s); })();
</script>
{/block}  

