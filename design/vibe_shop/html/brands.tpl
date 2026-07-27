{* The brand page template *}

{* Same rail contract as products.tpl: one .vs-filters element that is a bottom
   sheet below 992px and a static column from 992px. The trigger does not carry
   fn_switch_mobile_filter - see the note in products.tpl for why. *}

<div class="vs-catalogue">
	<div class="vs-catalogue__masthead">
		<h1 class="vs-catalogue__title">
			<span data-page="{$page->id}">{$h1|escape}</span>
		</h1>
	</div>

	<div class="vs-catalogue__layout">
		{* Sidebar with filters *}
		<aside id="vs_filters" class="fn_mobile_toogle vs-filters vs-sheet" aria-label="{$lang->filters|escape}">
			<div class="vs-filters__bar">
				<span class="vs-filters__heading" data-language="filters">{$lang->filters}</span>
				<button type="button" class="vs-btn vs-btn--ghost vs-btn--icon vs-filters__close" data-vs-sheet-close aria-label="{$lang->mobile_filter_close|escape}">
					{include file="svg.tpl" svgId="close"}
				</button>
			</div>

			<div class="vs-filters__scroll">
				<div class="fn_features">
					{if !$settings->deferred_load_features}
						{include file='features.tpl'}
					{else}
						{* Deferred load features *}
						<div class='fn_skeleton_load'>
							{section name=foo start=1 loop=7 step=1}
								<div class='vs-skeleton vs-skeleton--filter'></div>
							{/section}
						</div>
					{/if}
				</div>

				{* Browsed products *}
				<div class="vs-filters__browsed">
					{include file='browsed_products.tpl'}
				</div>
			</div>

			<div class="vs-filters__foot">
				<form method="post">
					<button type="submit" name="prg_seo_hide" class="fn_filter_reset vs-btn vs-btn--secondary vs-filters__reset" value="{url_generator route="brands" absolute=1}">
						{include file="svg.tpl" svgId="reset_icon"}
						<span>{$lang->mobile_filter_reset}</span>
					</button>
				</form>
				<button type="button" class="vs-btn vs-btn--primary vs-filters__apply" data-vs-sheet-close>
					<span class="vs-filters__apply_label" data-language="filters">{$lang->filters}</span>
				</button>
			</div>
		</aside>

		<div class="vs-catalogue__main">
			<div class="vs-catalogue__toolbar">
				{* Mobile button filters *}
				<button type="button" class="vs-btn vs-btn--secondary vs-filters__open hidden-lg-up" data-vs-sheet-open="vs_filters" aria-controls="vs_filters" aria-expanded="false">
					{include file="svg.tpl" svgId="filter_icon"}
					<span data-language="filters">{$lang->filters}</span>
				</button>
			</div>

			<div class="fn_selected_features">
				{if !$settings->deferred_load_features}
					{include file='selected_features.tpl'}
				{/if}
			</div>

			{* Brand list *}
			<div id="fn_products_content" class="fn_categories">
				{include file="brands_content.tpl"}
			</div>

			{if $brands}
				{* Friendly URLs Pagination *}
				<div class="fn_pagination products_pagination">
					{include file='chpu_pagination.tpl'}
				</div>
			{/if}

			{* The page body *}
			{if $description}
				<div class="vs-catalogue__outro">
					<div class="fn_readmore">
						<div class="block__description vs-prose">{$description}</div>
					</div>
				</div>
			{/if}
		</div>
	</div>
</div>

<div class="vs-sheet__backdrop"></div>
