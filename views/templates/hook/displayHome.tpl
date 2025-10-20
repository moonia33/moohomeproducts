{if isset($moohp_blocks) && $moohp_blocks}

  {foreach from=$moohp_blocks item=block}
  <section class="container mt-5 pt-5">
    <header class="section-title__heading heading-line">
      <h2 class="h2 section-title">{$block.category_name|escape:'html':'UTF-8'}
      </h2>
    </header>
    <div class="row gx-1">
        <div class="col-lg-3 g-0 border bg-light">
          <div class="row align-items-center h-100 g-0 overflow-hidden">
          <div class="content-body p-3">
          <h5 class="title h5 mb-4">{$block.category_name|escape:'html':'UTF-8'}
          </h5>
          {if $block.category_desc}
            <p class="text-muted mb-0">{$block.category_desc|truncate:166:'...'|escape:'html':'UTF-8'}</p>
          {/if}
          <a href="{$block.link}" class="btn btn-outline-primary rounded-pill my-4">More</a>
          {if $block.category_image}
          <img src="{$block.category_image}" alt="{$block.category_name|escape:'html':'UTF-8'}" class="rounded-3 img-bg" />
          {/if}
          </div>
          </div>
        </div>

      <div class="col-lg-9">
        <div class="row">
        {include file='catalog/_partials/productlist-homecat.tpl' products=$block.products productClass='col-6 col-xs-6 col-lg-3 col-xl-2'}
        </div>
      </div>
    </div>
  </section>
  {/foreach}

{/if}
