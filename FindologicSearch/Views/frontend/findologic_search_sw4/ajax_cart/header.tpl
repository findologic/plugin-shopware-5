{block name="frontend_checkout_ajax_add_article_action_buttons" append}
    <script type="text/javascript">
        {foreach from=$order item=c}
        fl_paq.push(['addEcommerceItem',
            "{$c.productordernumber}",
            "{$c.productname|addslashes}",
            [{foreach from=$c.productcategories item=p name=cat}"{$p}"{if not $smarty.foreach.cat.last}, {/if}{/foreach}],
            {$c.productprice},
            {$c.productquantity}
        ]);
        {/foreach}
        fl_paq.push(['trackEcommerceCartUpdate', {$CART_AMOUNT|replace:',':'.'}]);
        fl_paq.push(['trackPageView']);
    </script>
{/block}

