<!-- The authors page template -->

<div class="vs-page">
	<div class="vs-page__masthead">
		<h1 class="vs-page__title">
			<span data-page="{$page->id}">{if $page->name_h1|escape}{$page->name_h1|escape}{else}{$page->name|escape}{/if}</span>
		</h1>
	</div>

	{if $authors}
		{* The list of the authors *}
		<div class="vs-authors">
			{foreach $authors as $a}
				<a class="vs-author" data-author="{$a->id}" href="{url_generator route='author' url=$a->url}">
					<span class="vs-author__media">
						{if $a->image}
							<picture>
								{if $settings->support_webp}
									<source type="image/webp" data-srcset="{$a->image|resize:320:500:false:$config->resized_authors_dir|webp}">
								{/if}
								<source data-srcset="{$a->image|resize:320:500:false:$config->resized_authors_dir}">
								<img class="lazy" data-src="{$a->image|resize:320:500:false:$config->resized_authors_dir}" src="{$rootUrl}/design/{get_theme}/images/xloading.svg" alt=""/>
							</picture>
						{else}
							<span class="vs-author__no_image">
								{include file="svg.tpl" svgId="comment-user_icon"}
							</span>
						{/if}
					</span>
					<span class="vs-author__name">{$a->name|escape}</span>
					{if $a->position_name}
						<span class="vs-author__position">{$a->position_name|escape}</span>
					{/if}
				</a>
			{/foreach}
		</div>

		{* Pagination *}
		<div class="products_pagination">
			{include file='pagination.tpl'}
		</div>
	{else}
		<div class="vs-empty vs-empty--center">
			<span class="vs-empty__icon">{include file="svg.tpl" svgId="comment-user_icon"}</span>
			<p class="vs-empty__title" data-language="products_not_found">{$lang->products_not_found}</p>
		</div>
	{/if}

	{* The page body *}
	{if $description}
		<div class="fn_readmore vs-page__outro">
			<div class="block__description vs-prose">{$description}</div>
		</div>
	{/if}
</div>
