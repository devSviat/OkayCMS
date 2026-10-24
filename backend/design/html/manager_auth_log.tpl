{* Журнал безпеки (ManagerAuthLog): у профілі — події одного менеджера, на сторінці
   сесій — усіх ($auth_log_all). Невдалі спроби — окремим списком: їх може насипати
   будь-хто, і входи та зміни захисту вони не витісняють. Тон рядка дає сервіс:
   alert — невдалі спроби й ліміт, change — зміни захисту, info — решта. *}
{function name=auth_log_rows rows=[]}
    <ul class="auth_log">
        {foreach $rows as $e}
            <li class="auth_log__item auth_log__item--{$e->tone|escape}">
                <div class="auth_log__what">
                    {if $auth_log_all}
                        {if $e->manager_login !== null}
                            {if $can_open_profiles}
                                <a class="link" href="{url controller=ManagerAdmin id=$e->manager_id active_tab=security}">{$e->manager_login|escape}</a>
                            {else}
                                <span>{$e->manager_login|escape}</span>
                            {/if}
                            <span class="text_grey">·</span>
                        {elseif $e->event != 'manager_deleted'}
                            <span class="text_grey">{if $e->manager_id}#{$e->manager_id|escape}{else}{$btr->manager_log_unknown_login|escape}{/if}</span>
                            <span class="text_grey">·</span>
                        {/if}
                    {/if}
                    <span class="auth_log__event">{$btr->getTranslation("manager_log_event_`$e->event`")|escape}{if $e->event == 'sessions_revoked' && $e->details.count}: {$e->details.count|escape}{/if}</span>
                    {if $e->event == 'manager_deleted' && $e->details.login}<span class="auth_log__detail">{$e->details.login|escape}</span>{/if}
                    {if $e->details.mfa}<span class="auth_log__detail">{$btr->manager_log_mfa|escape}</span>{/if}
                    {if $e->details.via}<span class="auth_log__detail">{$btr->getTranslation("manager_log_via_`$e->details.via`")|escape}</span>{/if}
                    {if $e->details.stage}<span class="auth_log__detail">{$btr->getTranslation("manager_log_stage_`$e->details.stage`")|escape}</span>{/if}
                </div>
                <div class="auth_log__meta">
                    <time datetime="{$e->created_at|escape}">{$e->created_at|date} {$e->created_at|time}</time>
                    {if $e->ip} · {$e->ip|escape}{/if}
                    {if $e->device} · <span title="{$e->user_agent|escape}">{$e->device|escape}</span>{/if}
                    {if $e->actor_id} · {$btr->manager_log_by|escape} {if $e->actor_login !== null}{$e->actor_login|escape}{else}#{$e->actor_id|escape}{/if}{/if}
                </div>
            </li>
        {/foreach}
    </ul>
{/function}

{if !$auth_log.events && !$auth_log.failures}
    <p class="text_grey">{$btr->manager_log_none|escape}</p>
{else}
    {if $auth_log.events}
        {call name=auth_log_rows rows=$auth_log.events}
    {/if}
    {if $auth_log.failures}
        <h3 class="auth_log__heading">{$btr->manager_log_failures|escape}</h3>
        {call name=auth_log_rows rows=$auth_log.failures}
    {/if}
{/if}
