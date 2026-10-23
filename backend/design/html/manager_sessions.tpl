{* Огляд сесій усіх менеджерів (AllManagerSessionsAdmin). Завершення — у профілі менеджера. *}
{$meta_title=$btr->manager_sessions_all_title scope=global}

<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">{$btr->manager_sessions_all_title|escape} - {$sessions|count}</div>
        </div>
    </div>
</div>

<div class="boxed">
    <div class="text_grey mb-1">{$btr->manager_sessions_all_hint|escape}</div>

    {if $sessions}
        <div class="okay_list">
            <div class="okay_list_head">
                <div class="okay_list_heading" style="width: 15%">{$btr->manager_sessions_manager|escape}</div>
                <div class="okay_list_heading" style="width: 15%">{$btr->manager_sessions_started|escape}</div>
                <div class="okay_list_heading" style="width: 15%">{$btr->manager_sessions_last_seen|escape}</div>
                <div class="okay_list_heading" style="width: 12%">{$btr->manager_sessions_ip|escape}</div>
                <div class="okay_list_heading" style="width: 43%">{$btr->manager_sessions_device|escape}</div>
            </div>
            <div class="okay_list_body">
                {foreach $sessions as $s}
                    <div class="okay_list_body_item">
                        <div class="okay_list_row">
                            <div class="okay_list_boding" style="width: 15%">
                                {if isset($session_logins[$s->manager_id])}
                                    {if $can_open_profiles}
                                        <a class="link" href="{url controller=ManagerAdmin id=$s->manager_id active_tab=security}">{$session_logins[$s->manager_id]|escape}</a>
                                    {else}
                                        {$session_logins[$s->manager_id]|escape}
                                    {/if}
                                {else}
                                    #{$s->manager_id|escape}
                                {/if}
                                {if $s->id == $current_session_id}
                                    <span class="tag tag-success">{$btr->manager_sessions_current|escape}</span>
                                {/if}
                            </div>
                            <div class="okay_list_boding" style="width: 15%">{$s->created_at|escape}</div>
                            <div class="okay_list_boding" style="width: 15%">{$s->last_seen_at|escape}</div>
                            <div class="okay_list_boding" style="width: 12%">{$s->ip|escape}</div>
                            <div class="okay_list_boding" style="width: 43%; word-break: break-word">{$s->user_agent|escape}</div>
                        </div>
                    </div>
                {/foreach}
            </div>
        </div>
    {else}
        <div class="heading_box mt-1">
            <div class="text_grey">{$btr->manager_sessions_none|escape}</div>
        </div>
    {/if}
</div>
