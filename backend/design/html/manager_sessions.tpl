{* Хто зараз увійшов (AllManagerSessionsAdmin) *}
{$meta_title=$btr->manager_sessions_all_title scope=global}

<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">{$btr->manager_sessions_all_title|escape} - {$sessions|count}</div>
        </div>
    </div>
</div>

{if $message_success}
    <div class="alert alert--icon alert--success">
        <div class="alert__content">
            <div class="alert__title">{$btr->manager_sessions_revoked|escape}</div>
        </div>
    </div>
{/if}

{if $message_error}
    <div class="alert alert--icon alert--error">
        <div class="alert__content">
            <div class="alert__title">
                {if $message_error == 'reauth_required'}{$btr->manager_reauth_required|escape}
                {elseif $message_error == 'password_wrong'}{$btr->manager_reauth_wrong|escape}
                {elseif $message_error == 'limit_try'}{$btr->auth_limit_try|escape}
                {else}{$btr->manager_no_access|escape}{/if}
            </div>
        </div>
    </div>
{/if}

{include file='manager_reauth_panel.tpl'}

<div class="boxed">
    {if $sessions}
        <ul class="session_list">
            {foreach $sessions as $s}
                <li class="session_list__item">
                    <div class="session_list__main">
                        <div class="session_list__device" title="{$s->user_agent|escape}">
                            {if $can_open_profiles}
                                <a class="link" href="{url controller=ManagerAdmin id=$s->manager_id active_tab=security}">{$s->login|escape}</a>
                            {else}
                                <span>{$s->login|escape}</span>
                            {/if}
                            <span class="text_grey">·</span>
                            {if $s->is_mobile}{include file='svg_icon.tpl' svgId='phone'}{/if}
                            <span>{$s->device|escape}</span>
                            {if $s->id == $current_session_id}<span class="tag tag-success">{$btr->manager_sessions_current|escape}</span>{/if}
                        </div>
                        <div class="session_list__meta">
                            {$s->ip|escape} · {$btr->manager_sessions_started|escape} {$s->created_at|date} {$s->created_at|time} · {$btr->manager_sessions_last_seen|escape} {$s->last_seen_at|date} {$s->last_seen_at|time}
                        </div>
                    </div>
                    {if $s->id != $current_session_id && $s->can_revoke}
                        <form method="post" class="session_list__action">
                            <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                            <input type="hidden" name="action" value="revoke_session">
                            <input type="hidden" name="session" value="{$s->id|escape}">
                            <button type="submit" class="btn btn-outline-danger btn_mini" {if !$reauth_fresh}disabled title="{$btr->manager_reauth_required|escape}"{/if}>{$btr->manager_sessions_revoke|escape}</button>
                        </form>
                    {/if}
                </li>
            {/foreach}
        </ul>
        <div class="session_list__footer">
            <span>{$btr->manager_sessions_hint|escape}</span>
        </div>
    {else}
        <p class="text_grey">{$btr->manager_sessions_none|escape}</p>
    {/if}
</div>
