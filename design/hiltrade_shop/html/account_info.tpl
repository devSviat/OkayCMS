<!-- Account info -->
{if $user}
    <a class="header_informers__link d-flex align-items-center" href="{url_generator route='user'}">
        <i class="d-flex align-items-center fa fa-user-o"></i>
        {* <span class="account__text" data-language="index_account">{$lang->index_account} </span>
		<span>{$user->name|escape}</span> *}
    </a>
{else}
    <a class="header_informers__link d-flex align-items-center" href="javascript:;" onclick="document.location.href = '{url_generator route="login"}'" title="{$lang->index_login}">
        <i class="d-flex align-items-center fa fa-user-o"></i>
        {* <span class="account__text" data-language="index_account">{$lang->index_account} </span>
		<span class="account__login" data-language="index_login">{$lang->index_login}</span> *}
    </a>
{/if}