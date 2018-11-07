{block name="frontend_listing_promotion"}
    {if $finPromotion}
        <div id="fl-promotion" class="panel has--border is--rounded">
            <div class="panel--body is--wide">
                <a href="{$finPromotion.link}"><img class="image" src="{$finPromotion.image}"></a>
            </div>
        </div>
    {/if}
{/block}