{block name="frontend_index_header_javascript" append}
    <script type="text/javascript">
        {foreach from=$order item=c}
        _paq.push(['addEcommerceItem',
            "{$c.productordernumber}",
            "{$c.productname|addslashes}",
            [{foreach from=$c.productcategories item=p name=cat}"{$p}"{if not $smarty.foreach.cat.last}, {/if}{/foreach}],
            {$c.productprice},
            {$c.productquantity}
        ]);
        {/foreach}
        _paq.push(['trackEcommerceCartUpdate', {$sAmount|replace:',':'.'}]);
        _paq.push(['trackPageView']);
    </script>
{/block}

