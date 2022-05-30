{block name="frontend_index_header_javascript_modernizr_lib" prepend}
    <link rel="preconnect" href="https://cdn.findologic.com/" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.findologic.com/">
    <link rel="preload" href="https://cdn.findologic.com/config/loader.min.js" as="script">
    <link rel="prefetch" href="https://cdn.findologic.com/config/{$shopkey}/main.js" as="script">
    <script type="text/javascript">
        {literal}
            (function (f,i,n,d,o,l,O,g,I,c){var V=[];var m=f.createElement("style");if(d){V.push(d)}if(c&&I.location.hash.indexOf("#search:")===0){V.push(c)}if(V.length>0){var Z=V.join(",");m.textContent=Z+"{opacity: 0;transition: opacity "+O+" ease-in-out;}."+o+" {opacity: 1 !important;}";I.flRevealContainers=function(){var a=f.querySelectorAll(Z);for(var T=0;T<a.length;T++){a[T].classList.add(o)}};setTimeout(I.flRevealContainers,l)}var W=g+"/config/"+i+"/main.js?usergrouphash="+n;var p=f.createElement("script");p.type="text/javascript";p.async=true;p.src=g+"/config/loader.min.js";var q=f.getElementsByTagName("script")[0];p.setAttribute("data-fl-main",W);q.parentNode.insertBefore(p,q);q.parentNode.insertBefore(m,p)})
        {/literal}
        (document,'{$shopkey}','{$userGroupHash}','.{$navigationContainer}','fl-reveal',3000,'.3s','//cdn.findologic.com',window,'.{$searchResultContainer}');
        {literal}
        document.addEventListener('DOMContentLoaded',function(n){document.querySelectorAll('img.filter-panel--media-image').forEach(function(n){n.onerror=function(){this.src='/custom/plugins/FinSearchUnified/Resources/views/frontend/_public/src/img/no-picture.png'}})});
        {/literal}
    </script>
{/block}
