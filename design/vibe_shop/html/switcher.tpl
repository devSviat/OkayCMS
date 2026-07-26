<!-- Languages -->
{if $languages|count > 1}
	{$cnt = 0}
	{foreach $languages as $ln}
		{if $ln->enabled}
			{$cnt = $cnt+1}
		{/if}
	{/foreach}
	{if $cnt>1}
		<div class="vs-switcher__item vs-disclosure">
			<button type="button" class="vs-switcher__current vs-disclosure__trigger" aria-expanded="false">
				{if is_file("{$config->lang_images_dir}{$language->label}.png")}
					<img alt="{$language->current_name}" width="20" height="20" src='{("{$language->label}.png")|resize:20:20:false:$config->lang_resized_dir}'/>
				{/if}
				<span>{$language->name}</span>
				<span class="vs-switcher__chevron">{include file="svg.tpl" svgId="chevron"}</span>
			</button>
			<div class="vs-switcher__menu vs-disclosure__panel">
				{foreach $languages as $l}
					{if $l->enabled}
						<a class="vs-switcher__link{if $language->id == $l->id} is-current{/if}" href="{preg_replace('/^(.+)\/$/', '$1', $l->url)}">
							{if is_file("{$config->lang_images_dir}{$l->label}.png")}
								<img alt="{$l->current_name}" width="20" height="20" src='{("{$l->label}.png")|resize:20:20:false:$config->lang_resized_dir}'/>
							{/if}
							<span>{$l->name|escape}</span>
						</a>
					{/if}
				{/foreach}
			</div>
		</div>
	{/if}
{/if}

<!-- Currencies -->
{if $currencies|count > 1}
	<div class="vs-switcher__item vs-disclosure">
		<button type="button" class="vs-switcher__current vs-disclosure__trigger" aria-expanded="false">
			<span>{$currency->name|escape}</span>
			<span class="vs-switcher__chevron">{include file="svg.tpl" svgId="chevron"}</span>
		</button>
		<div class="vs-switcher__menu vs-disclosure__panel">
			{foreach $currencies as $c}
				{if $c->enabled}
					<form method="POST">
						<button type="submit" name="prg_seo_hide" class="vs-switcher__link{if $currency->id== $c->id} is-current{/if}" value="{url path={furl price=null} currency_id=$c->id}">
							<span>{$c->name|escape}</span>
						</button>
					</form>
				{/if}
			{/foreach}
		</div>
	</div>
{/if}
