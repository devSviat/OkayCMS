<!-- User account -->
{if $user}
	<a class="vs-utility__link" href="{url_generator route='user'}">
		{include file="svg.tpl" svgId="user"}
		<span>{$user->name|escape}</span>
	</a>
{else}
	<a class="vs-utility__link" rel="nofollow" href="{url_generator route="login"}" title="{$lang->index_login}">
		{include file="svg.tpl" svgId="user"}
		<span class="account__login" data-language="index_login">{$lang->index_login}</span>
	</a>
{/if}
