{$meta_title = $btr->core_updater_meta_title scope=global}

{*Назва сторінки*}
<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">
                {$btr->core_updater_meta_title|escape}
            </div>
            {if $vm.canCheckNow}
                <div class="box_btn_heading">
                    <button type="button" class="btn btn_small btn-info fn_check_now">
                        {include file='svg_icon.tpl' svgId='refresh_icon'}
                        <span>{$btr->core_updater_check_now|escape}</span>
                    </button>
                </div>
            {/if}
        </div>
    </div>
</div>

{*Основний блок*}
<div class="row">
    <div class="col-xs-12">
        <div class="boxed" style="max-width: 800px;margin: 0 auto;">

            {if $vm.lastError}
                <div class="alert alert--icon alert--warning">
                    <div class="alert__content">
                        <div class="alert__title">{$btr->core_updater_check_failed_label|escape}</div>
                        <p>{$vm.lastError|escape}</p>
                    </div>
                </div>
            {/if}

            <div class="fn_mode_panel"{if $vm.mode == 'running'} style="display:none;"{/if}>

                {if $vm.mode == 'no_data'}
                    <div class="alert alert--center alert--icon alert--warning">
                        <div class="alert__content">
                            <div class="alert__title">{$btr->core_updater_no_data_text|escape}</div>
                        </div>
                    </div>

                {elseif $vm.mode == 'up_to_date'}
                    <div class="alert alert--center alert--icon alert--success">
                        <div class="alert__content">
                            <div class="alert__title">{$btr->core_updater_up_to_date_text|escape}</div>
                        </div>
                    </div>
                    <div class="row d_flex">
                        <div class="col-md-12">
                            <p><b>{$btr->core_updater_installed_label|escape}:</b> {$vm.installed|escape}</p>
                            {if $vm.checkedAt}
                                <p class="text_grey"><b>{$btr->core_updater_last_check_label|escape}:</b> {$vm.checkedAt|date_format:"%d.%m.%Y %H:%M"|escape}</p>
                            {/if}
                        </div>
                    </div>

                {elseif $vm.mode == 'update_available'}
                    <div class="row d_flex">
                        <div class="col-md-6">
                            <p class="text_grey">{$btr->core_updater_installed_label|escape}</p>
                            <h4>{$vm.installed|escape}</h4>
                        </div>
                        <div class="col-md-6">
                            <p class="text_grey">{$btr->core_updater_available_label|escape}</p>
                            <h4>{$vm.latest.forkVersion|escape}</h4>
                            {if $vm.latest.meta.upstreamBase}
                                <p><b>{$btr->core_updater_based_on_label|escape}:</b> {$vm.latest.meta.upstreamBase|escape}</p>
                            {/if}
                            {if $vm.latest.publishedAt}
                                <p><b>{$btr->core_updater_published_label|escape}:</b> {$vm.latest.publishedAt|date_format:"%d.%m.%Y"|escape}</p>
                            {/if}
                            {if $vm.latest.notesUrl}
                                <p><a href="{$vm.latest.notesUrl|escape}" target="_blank" rel="noopener">{$btr->core_updater_view_changes|escape}</a></p>
                            {/if}
                            {if $vm.latest.meta.requiresMigrations}
                                <p class="text_attention">
                                    {include file='svg_icon.tpl' svgId='warn_icon'}
                                    <span>{$btr->core_updater_requires_migrations_badge|escape}</span>
                                </p>
                            {/if}
                        </div>
                    </div>

                    {if $vm.latest.notesBody}
                        <div class="row d_flex">
                            <div class="col-md-12">
                                <h5>{$btr->core_updater_whats_new_label|escape}</h5>
                                <div>{$vm.latest.notesBody|escape|nl2br}</div>
                            </div>
                        </div>
                    {/if}

                    <div class="row d_flex">
                        <div class="col-md-12">
                            <button type="button" class="btn btn_small btn-warning fn_start_update">
                                {include file='svg_icon.tpl' svgId='refresh_icon'}
                                <span>{$btr->core_updater_update_btn|escape} {$vm.latest.forkVersion|escape}</span>
                            </button>
                        </div>
                    </div>

                {elseif $vm.mode == 'stale_run'}
                    <div class="alert alert--icon alert--warning">
                        <div class="alert__content">
                            <div class="alert__title">{$btr->core_updater_stale_run_text|escape}</div>
                        </div>
                    </div>
                    <div class="row d_flex">
                        <div class="col-md-12">
                            <button type="button" class="btn btn_small btn-warning fn_start_update">
                                {include file='svg_icon.tpl' svgId='refresh_icon'}
                                <span>{$btr->core_updater_resume_btn|escape}</span>
                            </button>
                        </div>
                    </div>

                {elseif $vm.mode == 'failed' || $vm.mode == 'rolled_back'}
                    <div class="alert alert--center alert--icon alert--error">
                        <div class="alert__content">
                            <div class="alert__title">
                                {if $vm.mode == 'failed'}{$btr->core_updater_step_failed|escape}{else}{$btr->core_updater_step_rolled_back|escape}{/if}
                            </div>
                            {if $vm.run.error}<p>{$vm.run.error|escape}</p>{/if}
                        </div>
                    </div>

                    {if $vm.run.requiresManualIntervention}
                        <div class="alert alert--icon alert--warning">
                            <div class="alert__content">
                                <div class="alert__title">{$btr->core_updater_manual_intervention_text|escape}</div>
                                {if $vm.run.backupZipPath || $vm.run.migrationsDumpPath}
                                    <p><b>{$btr->core_updater_backup_paths_label|escape}:</b></p>
                                    <ul>
                                        {if $vm.run.backupZipPath}<li>{$vm.run.backupZipPath|escape}</li>{/if}
                                        {if $vm.run.migrationsDumpPath}<li>{$vm.run.migrationsDumpPath|escape}</li>{/if}
                                    </ul>
                                {/if}
                                {if $vm.run.rolledBackMigrations}
                                    <p><b>{$btr->core_updater_rolled_back_migrations_label|escape}:</b></p>
                                    <ul>
                                        {foreach $vm.run.rolledBackMigrations as $migration}
                                            <li>{$migration|escape}</li>
                                        {/foreach}
                                    </ul>
                                {else}
                                    <p>{$btr->core_updater_migrations_not_rolled_back|escape}</p>
                                {/if}
                                <p>
                                    <a href="https://github.com/devSviat/OkayCMS/blob/main/docs/updates.md" target="_blank" rel="noopener">
                                        {$btr->core_updater_docs_link_label|escape}
                                    </a>
                                </p>
                            </div>
                        </div>
                    {/if}

                    {if $vm.run.maintenanceDisabledAfterFailure}
                        <div class="alert alert--icon alert--warning">
                            <div class="alert__content">
                                <p>{$btr->core_updater_maintenance_disabled_text|escape}</p>
                            </div>
                        </div>
                    {/if}

                    {if $vm.run.finalizeWarning}
                        <div class="alert alert--icon alert--warning">
                            <div class="alert__content">
                                <p>{$vm.run.finalizeWarning|escape}</p>
                            </div>
                        </div>
                    {/if}

                    {if $vm.canStartUpdate}
                        <div class="row d_flex">
                            <div class="col-md-12">
                                <button type="button" class="btn btn_small btn-warning fn_start_update">
                                    {include file='svg_icon.tpl' svgId='refresh_icon'}
                                    <span>{$btr->core_updater_retry_btn|escape}</span>
                                </button>
                            </div>
                        </div>
                    {/if}

                {elseif $vm.mode == 'done'}
                    <div class="alert alert--center alert--icon alert--success">
                        <div class="alert__content">
                            <div class="alert__title">{$btr->core_updater_step_done|escape}</div>
                            <p><b>{$btr->core_updater_installed_label|escape}:</b> {$vm.installed|escape}</p>
                        </div>
                    </div>
                    {if $vm.run.finalizeWarning}
                        <div class="alert alert--icon alert--warning">
                            <div class="alert__content">
                                <p>{$vm.run.finalizeWarning|escape}</p>
                            </div>
                        </div>
                    {/if}
                {/if}

            </div>

            {*Панель прогресу — в розмітці завжди, показується для running і одразу після старту з JS*}
            <div class="fn_running_panel core_updater_steps" data-initial-mode="{$vm.mode|escape}"{if $vm.mode != 'running'} style="display:none;"{/if}>
                {$runStepIndex = -1}
                {if $vm.run && $vm.run.stepIndex !== null}{$runStepIndex = $vm.run.stepIndex}{/if}
                {* 10 = кількість UpdateStatus::STEPS; steps_lang_keys додає ще 3 TERMINAL_STEPS поверх них *}
                {$stepsTotal = 10}
                {if $vm.run.stepsTotal}{$stepsTotal = $vm.run.stepsTotal}{/if}

                <p><span class="fn_step_counter">{$runStepIndex+1} / {$stepsTotal}</span></p>

                <ul class="core_updater_steps_list">
                    {foreach $steps_lang_keys as $stepKey => $stepLangKey}
                        {if $stepKey != 'done' && $stepKey != 'failed' && $stepKey != 'rolled_back'}
                            <li class="{if $stepLangKey@index < $runStepIndex}is_done{elseif $stepLangKey@index == $runStepIndex}is_current{else}is_pending{/if}" data-step-index="{$stepLangKey@index}">
                                <span class="step_icon">
                                    {if $stepLangKey@index < $runStepIndex}
                                        {include file='svg_icon.tpl' svgId='checked'}
                                    {elseif $stepLangKey@index == $runStepIndex}
                                        {include file='svg_icon.tpl' svgId='refresh_icon'}
                                    {/if}
                                </span>
                                <span class="step_label">{$btr->$stepLangKey|escape}</span>
                            </li>
                        {/if}
                    {/foreach}
                </ul>
            </div>

        </div>
    </div>
</div>

{*Модалка підтвердження — fancybox-inline, за прецедентом auto_deploy.tpl*}
<div id="core_updater_confirm_modal" style="display:none;min-width: 400px;">
    <div class="card-header">
        <div class="heading_modal">{$btr->core_updater_confirm_title|escape}</div>
    </div>
    <div class="modal-body">
        <p>{$btr->core_updater_confirm_text|escape}</p>
        <button type="button" class="btn btn_small btn_blue fn_confirm_start_update">
            {include file='svg_icon.tpl' svgId='checked'}
            <span>{$btr->core_updater_confirm_yes|escape}</span>
        </button>
        <button type="button" class="btn btn_small btn-danger fn_dismiss_confirm" data-fancybox-close>
            {include file='svg_icon.tpl' svgId='delete'}
            <span>{$btr->core_updater_confirm_no|escape}</span>
        </button>
    </div>
</div>

<style>
    .core_updater_steps_list { list-style: none; padding: 0; margin: 0; }
    .core_updater_steps_list li { display: flex; align-items: center; gap: 8px; padding: 6px 0; color: var(--ok-ink-muted); }
    .core_updater_steps_list li.is_done { color: var(--ok-success); }
    .core_updater_steps_list li.is_current { color: var(--ok-accent); font-weight: 600; }
    .core_updater_steps_list li .step_icon svg { width: 16px; height: 16px; }
    .core_updater_steps_list li.is_current .step_icon svg { animation: core_updater_spin 1s linear infinite; }
    @keyframes core_updater_spin { to { transform: rotate(360deg); } }
</style>

<script>
    var coreUpdaterPollInterval = null;

    function coreUpdaterHandleAjaxError(data) {
        if (data && data.error === 'csrf') {
            toastr.error("{$btr->core_updater_csrf_error|escape:'javascript'}");
        } else if (data && data.error === 'cannot_start') {
            toastr.error("{$btr->core_updater_cannot_start|escape:'javascript'}");
        } else if (data && data.error) {
            toastr.error(data.message ? data.message : "{$btr->core_updater_start_failed|escape:'javascript'}");
        } else {
            toastr.error("{$btr->core_updater_ajax_error|escape:'javascript'}");
        }
    }

    function coreUpdaterShowRunningPanel() {
        $('.fn_mode_panel').hide();
        $('.fn_running_panel').show();
    }

    function coreUpdaterShowModePanel() {
        $('.fn_running_panel').hide();
        $('.fn_mode_panel').show();
    }

    function coreUpdaterStopPolling() {
        if (coreUpdaterPollInterval) {
            clearInterval(coreUpdaterPollInterval);
            coreUpdaterPollInterval = null;
        }
    }

    function coreUpdaterUpdateProgress(vm) {
        if (!vm || !vm.run) {
            return;
        }
        $('.core_updater_steps_list li').each(function () {
            var idx = parseInt($(this).attr('data-step-index'), 10);
            $(this).removeClass('is_done is_current is_pending');
            if (idx < vm.run.stepIndex) {
                $(this).addClass('is_done');
            } else if (idx === vm.run.stepIndex) {
                $(this).addClass('is_current');
            } else {
                $(this).addClass('is_pending');
            }
        });
        $('.fn_step_counter').text((vm.run.stepIndex + 1) + ' / ' + vm.run.stepsTotal);
    }

    function coreUpdaterPoll() {
        coreUpdaterStopPolling();
        coreUpdaterPollInterval = setInterval(function () {
            $.ajax({
                type: 'get',
                dataType: 'json',
                url: "{url controller='OkayCMS.CoreUpdater.CoreUpdaterAdmin@status'}",
                success: function (vm) {
                    coreUpdaterUpdateProgress(vm);
                    if (vm && (vm.mode === 'done' || vm.mode === 'failed' || vm.mode === 'rolled_back')) {
                        coreUpdaterStopPolling();
                        location.reload();
                    }
                }
            });
        }, 3000);
    }

    $(function () {
        // Сторінку відкрили/оновили ПІД ЧАС прогону — панель уже відрендерена
        // сервером у стані running, лишається тільки підхопити поллінг.
        if ($('.fn_running_panel').attr('data-initial-mode') === 'running') {
            coreUpdaterPoll();
        }
    });

    $(document).on('click', '.fn_check_now', function () {
        var btn = $(this);
        btn.prop('disabled', true);
        $.ajax({
            type: 'post',
            dataType: 'json',
            url: "{url controller='OkayCMS.CoreUpdater.CoreUpdaterAdmin@checkNow'}",
            data: {
                session_id: '{$smarty.session.id}',
            },
            success: function (data) {
                if (data && data.error) {
                    coreUpdaterHandleAjaxError(data);
                    btn.prop('disabled', false);
                    return;
                }
                location.reload();
            },
            error: function () {
                toastr.error("{$btr->core_updater_ajax_error|escape:'javascript'}");
                btn.prop('disabled', false);
            }
        });
    });

    $(document).on('click', '.fn_start_update', function () {
        $.fancybox.open({
            src: '#core_updater_confirm_modal',
            type: 'inline',
            touch: false,
        });
    });

    $(document).on('click', '.fn_dismiss_confirm', function () {
        $.fancybox.close();
    });

    $(document).on('click', '.fn_confirm_start_update', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.fancybox.close();
        coreUpdaterShowRunningPanel();
        coreUpdaterPoll();

        $.ajax({
            type: 'post',
            dataType: 'json',
            url: "{url controller='OkayCMS.CoreUpdater.CoreUpdaterAdmin@startUpdate'}",
            data: {
                session_id: '{$smarty.session.id}',
            },
            success: function (data) {
                if (data && data.error) {
                    // Сервер відповів синхронно й одразу відмовив (csrf/cannot_start/
                    // start_failed) — прогону не було, відкочуємо оптимістичний UI.
                    coreUpdaterStopPolling();
                    coreUpdaterShowModePanel();
                    coreUpdaterHandleAjaxError(data);
                }
                $btn.prop('disabled', false);
            },
            error: function () {
                // POST виконується синхронно і може тривати хвилини — ajax error тут
                // не обов'язково означає провал оновлення: з'єднання браузера могло
                // обірватись (таймаут/мережа), поки сервер продовжує прогін. Перевіряємо
                // реальний стан через status: якщо сервер уже в running чи в термінальному
                // кроці — оновлення живе, лишаємо поллінг; інакше це справжній збій.
                $.ajax({
                    type: 'get',
                    dataType: 'json',
                    url: "{url controller='OkayCMS.CoreUpdater.CoreUpdaterAdmin@status'}",
                    success: function (vm) {
                        var alive = vm && (vm.mode === 'running' || vm.mode === 'done'
                            || vm.mode === 'failed' || vm.mode === 'rolled_back');
                        if (alive) {
                            coreUpdaterUpdateProgress(vm);
                            if (vm.mode !== 'running') {
                                coreUpdaterStopPolling();
                                location.reload();
                            }
                        } else {
                            coreUpdaterStopPolling();
                            coreUpdaterShowModePanel();
                            toastr.error("{$btr->core_updater_ajax_error|escape:'javascript'}");
                        }
                        $btn.prop('disabled', false);
                    },
                    error: function () {
                        coreUpdaterStopPolling();
                        coreUpdaterShowModePanel();
                        toastr.error("{$btr->core_updater_ajax_error|escape:'javascript'}");
                        $btn.prop('disabled', false);
                    }
                });
            }
        });
    });
</script>
