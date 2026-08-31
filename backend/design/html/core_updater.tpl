{$meta_title = $btr->core_updater_meta_title scope=global}

{*
    Застереження про повну резервну копію. Показується тричі — над «Оновити»,
    над «Продовжити оновлення» і в діалозі підтвердження, — тож живе функцією:
    розійтись формулюванням між трьома екранами не можна, це читалось би як
    різні вимоги.

    Окремим шаблоном зробити не можна: `backend/design/` у пакет релізу не
    входить, туди потрапляють лише поіменно перелічені файли, і новий
    `{include}` на цільовій інсталяції не знайшов би файлу зовсім.

    $btr передається явно, а не береться зі скоупу: у Smarty 5 покладатись на
    видимість зовнішніх змінних усередині {function} не варто.
*}
{function name='core_updater_backup_warning'}
    <div class="alert alert--icon alert--warning mt-3">
        <div class="alert__content">
            <div class="alert__title">{$btr->core_updater_backup_warning_title|escape}</div>
            <p>{$btr->core_updater_backup_warning_text|escape}</p>
        </div>
    </div>
{/function}

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
    <div class="col-md-12">
        <div class="boxed">

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
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <p><b>{$btr->core_updater_installed_label|escape}:</b> {$vm.installed|escape}{if $vm.installedUpstreamBase} ({$btr->core_updater_based_on_label|escape} {$vm.installedUpstreamBase|escape}){/if}</p>
                            {if $vm.checkedAt}
                                <p class="text_grey"><b>{$btr->core_updater_last_check_label|escape}:</b> {$vm.checkedAt|date_format:"%d.%m.%Y %H:%M"|escape}</p>
                            {/if}
                        </div>
                    </div>

                {elseif $vm.mode == 'update_available'}
                    {if $vm.previousRunDone}
                        <div class="alert alert--icon alert--success">
                            <div class="alert__content">
                                <p>{$btr->core_updater_previous_run_done_text|escape}</p>
                            </div>
                        </div>
                    {/if}
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <p class="text_grey">{$btr->core_updater_installed_label|escape}</p>
                            <h4>{$vm.installed|escape}</h4>
                            {if $vm.installedUpstreamBase}
                                <p class="text_grey">{$btr->core_updater_based_on_label|escape}: {$vm.installedUpstreamBase|escape}</p>
                            {/if}
                        </div>
                        <div class="col-md-6">
                            <p class="text_grey">{$btr->core_updater_available_label|escape}</p>
                            <h4>{$vm.latest.forkVersion|escape}</h4>
                            {if $vm.latest.meta && $vm.latest.meta.upstreamBase}
                                <p><b>{$btr->core_updater_based_on_label|escape}:</b> {$vm.latest.meta.upstreamBase|escape}</p>
                            {/if}
                            {if $vm.latest.publishedAt}
                                <p><b>{$btr->core_updater_published_label|escape}:</b> {$vm.latest.publishedAt|date_format:"%d.%m.%Y"|escape}</p>
                            {/if}
                            {if $vm.latest.notesUrl}
                                <p><a href="{$vm.latest.notesUrl|escape}" target="_blank" rel="noopener">{$btr->core_updater_view_changes|escape}</a></p>
                            {/if}
                            {if $vm.latest.meta && $vm.latest.meta.requiresMigrations}
                                <p class="text_attention">
                                    {include file='svg_icon.tpl' svgId='warn_icon'}
                                    <span>{$btr->core_updater_requires_migrations_badge|escape}</span>
                                </p>
                            {/if}
                        </div>
                    </div>

                    {if $vm.latest.notesBody}
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <h5>{$btr->core_updater_whats_new_label|escape}</h5>
                                <div>{$vm.latest.notesBody|escape|nl2br}</div>
                            </div>
                        </div>
                    {/if}

                    {call name='core_updater_backup_warning' btr=$btr}

                    <div class="row mt-3">
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
                            <p>{$btr->core_updater_stale_run_maintenance_warning|escape}</p>
                        </div>
                    </div>
                    {call name='core_updater_backup_warning' btr=$btr}

                    <div class="row mt-3">
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
                                    <p>{$btr->core_updater_migrations_not_rolled_back|escape}</p>
                                {else}
                                    <p>{$btr->core_updater_no_migrations_applied_text|escape}</p>
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
                        <div class="row">
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
                            <p>
                                <b>{$btr->core_updater_installed_label|escape}:</b> {$vm.installed|escape}
                                {if $vm.run.updatedAt} &middot; {$vm.run.updatedAt|date_format:"%d.%m.%Y %H:%M"}{/if}
                            </p>
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
                {if $vm.run && $vm.run.stepsTotal}{$stepsTotal = $vm.run.stepsTotal}{/if}

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
                                <span class="step_label">{$btr->getTranslation($stepLangKey)|escape}</span>
                            </li>
                        {/if}
                    {/foreach}
                </ul>
            </div>

        </div>
    </div>
</div>

{*Довідка й налаштування — ховаються на час прогону, щоб не відволікати від прогресу*}
<div class="row fn_side_panels"{if $vm.mode == 'running'} style="display:none;"{/if}>

    {*Паспорт системи: обидві версії разом, щоб не шукати їх по адмінці*}
    <div class="col-lg-6 col-md-12 mb-2">
        <div class="boxed">
            <div class="heading_box mb-2">{$btr->core_updater_current_state_title|escape}</div>
            <table class="table_default">
                <tr class="table_default__row">
                    <td class="table_default__item text_grey">{$btr->core_updater_build_label|escape}</td>
                    <td class="table_default__item"><strong>{$vm.installed|escape}</strong></td>
                </tr>
                {if $vm.installedUpstreamBase}
                    <tr class="table_default__row">
                        <td class="table_default__item text_grey">{$btr->core_updater_based_on_label|escape}</td>
                        <td class="table_default__item">OkayCMS {$vm.installedUpstreamBase|escape}</td>
                    </tr>
                {/if}
                <tr class="table_default__row">
                    <td class="table_default__item text_grey">{$btr->core_updater_last_check_label|escape}</td>
                    <td class="table_default__item fn_last_check">
                        {if $vm.checkedAt}{$vm.checkedAt|date_format:"%d.%m.%Y %H:%M"|escape}{else}&mdash;{/if}
                    </td>
                </tr>
            </table>
            <p class="text_grey font_12 mt-2">{$btr->core_updater_schedule_hint|escape}</p>
        </div>
    </div>

    {*Резервні копії: відповідь на «а копія взагалі є?» без походу по SSH*}
    <div class="col-lg-6 col-md-12 mb-2">
        <div class="boxed">
            <div class="heading_box mb-2">{$btr->core_updater_backups_title|escape}</div>
            {if $backups}
                <table class="table_default">
                    {foreach $backups as $backup}
                        <tr class="table_default__row">
                            <td class="table_default__item">{$backup.name|escape}</td>
                            <td class="table_default__item text_grey">{if $backup.size >= 1048576}{($backup.size/1048576)|string_format:"%.1f"}&nbsp;MB{else}{($backup.size/1024)|string_format:"%.0f"}&nbsp;KB{/if}</td>
                            <td class="table_default__item text_grey">{$backup.date|date_format:"%d.%m.%Y %H:%M"|escape}</td>
                        </tr>
                    {/foreach}
                </table>
            {else}
                <p class="text_grey">{$btr->core_updater_backups_empty|escape}</p>
            {/if}
            <p class="text_grey font_12 mt-2">{$btr->core_updater_backups_hint|escape}</p>
        </div>
    </div>

    <div class="col-md-12">
        <div class="boxed">
            <div class="heading_box mb-2">{$btr->core_updater_settings_title|escape}</div>
            <form method="post" action="index.php?controller=CoreUpdaterAdmin@saveSettings">
                <input type="hidden" name="session_id" value="{$smarty.session.id}">

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="heading_label">{$btr->core_updater_root_url_label|escape}</div>
                            <input class="form-control" type="text" name="core_updater_root_url"
                                   value="{$updater_settings.rootUrl|escape}"
                                   placeholder="{$updater_settings.detectedRootUrl|escape}">
                            <p class="text_grey font_12 mt-1">{$btr->core_updater_root_url_hint|escape}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="heading_label">{$btr->core_updater_auto_check_title|escape}</div>
                            <div class="activity_of_switch activity_of_switch--left">
                                <div class="activity_of_switch_item">
                                    <div class="okay_switch clearfix">
                                        <label class="switch_label" for="core_updater_auto_check">{$btr->core_updater_auto_check_label|escape}</label>
                                        <label class="switch switch-default">
                                            <input class="switch-input" name="core_updater_auto_check" value="1"
                                                   type="checkbox" id="core_updater_auto_check"{if $updater_settings.autoCheck} checked{/if}>
                                            <span class="switch-label"></span>
                                            <span class="switch-handle"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <p class="text_grey font_12 mt-1">{$btr->core_updater_auto_check_hint|escape}</p>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn_blue mt-2">{$btr->core_updater_settings_save|escape}</button>
            </form>
        </div>
    </div>

    <div class="col-md-12 mb-2">
        <a class="font_12" href="https://github.com/devSviat/OkayCMS/blob/main/docs/updates.md" target="_blank" rel="noopener">
            {$btr->core_updater_docs_link_label|escape}
        </a>
    </div>
</div>

{*Модалка підтвердження — fancybox-inline, за прецедентом auto_deploy.tpl*}
<div id="core_updater_confirm_modal" style="display:none;min-width: 400px;">
    <div class="card-header">
        <div class="heading_modal">{$btr->core_updater_confirm_title|escape}</div>
    </div>
    <div class="modal-body">
        <p>{$btr->core_updater_confirm_text|escape}</p>
        {call name='core_updater_backup_warning' btr=$btr}
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
        $('.fn_check_now').prop('disabled', true).hide();
    }

    function coreUpdaterShowModePanel() {
        $('.fn_running_panel').hide();
        $('.fn_mode_panel').show();
        $('.fn_check_now').prop('disabled', false).show();
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

    var coreUpdaterPollErrorShown = false;

    function coreUpdaterPoll() {
        coreUpdaterStopPolling();
        coreUpdaterPollErrorShown = false;
        coreUpdaterPollInterval = setInterval(function () {
            $.ajax({
                type: 'get',
                dataType: 'json',
                url: "{url controller='CoreUpdaterAdmin@status'}",
                success: function (vm) {
                    if (vm && (vm.mode === 'done' || vm.mode === 'failed' || vm.mode === 'rolled_back')) {
                        coreUpdaterStopPolling();
                        location.reload();
                        return;
                    }
                    coreUpdaterUpdateProgress(vm);
                },
                error: function () {
                    // Оновлення на сервері триває незалежно від поллінгу — не гасимо
                    // інтервал, лише один раз попереджаємо, що зв'язок перервався.
                    if (!coreUpdaterPollErrorShown) {
                        coreUpdaterPollErrorShown = true;
                        toastr.warning("{$btr->core_updater_poll_lost_text|escape:'javascript'}");
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
        var label = btn.find('span');
        var labelText = label.text();

        // Без цього клік виглядав як «нічого не сталося»: запит іде мовчки, а
        // коли нової версії немає — сторінка перезавантажується в той самий
        // вигляд. Тому спершу видимий прогрес, а в кінці — прямий вердикт.
        btn.prop('disabled', true);
        label.text("{$btr->core_updater_checking|escape:'javascript'}");

        $.ajax({
            type: 'post',
            dataType: 'json',
            url: "{url controller='CoreUpdaterAdmin@checkNow'}",
            data: {
                session_id: '{$smarty.session.id}',
            },
            success: function (data) {
                if (data && data.error) {
                    coreUpdaterHandleAjaxError(data);
                    label.text(labelText);
                    btn.prop('disabled', false);
                    return;
                }

                // Є що ставити — перемальовуємо сторінку, щоб показати картку
                // з версією, нотатками й кнопкою оновлення.
                if (data && data.mode === 'update_available') {
                    location.reload();
                    return;
                }

                if (data && data.lastError) {
                    toastr.error(data.lastError);
                } else {
                    toastr.success("{$btr->core_updater_up_to_date_text|escape:'javascript'}");
                }

                if (data && data.checkedAt) {
                    var d = new Date(data.checkedAt * 1000);
                    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
                    $('.fn_last_check').text(
                        pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear()
                        + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes())
                    );
                }

                label.text(labelText);
                btn.prop('disabled', false);
            },
            error: function (jqXHR) {
                // Пре-диспетчерська CSRF-перевірка (backend/index.php) віддає 403 ще
                // до виклику контролера — така відповідь потрапляє сюди, а не в
                // success із полем error, рівним 'csrf'.
                if (jqXHR && jqXHR.status === 403) {
                    toastr.error("{$btr->core_updater_csrf_error|escape:'javascript'}");
                } else {
                    toastr.error("{$btr->core_updater_ajax_error|escape:'javascript'}");
                }
                label.text(labelText);
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
            url: "{url controller='CoreUpdaterAdmin@startUpdate'}",
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
            error: function (jqXHR) {
                // 403 — пре-диспетчерська CSRF-перевірка відмовила ще до контролера:
                // прогону не було й перевіряти status() нема сенсу, на відміну від
                // решти помилок (там сервер міг таки почати прогін).
                if (jqXHR && jqXHR.status === 403) {
                    coreUpdaterStopPolling();
                    coreUpdaterShowModePanel();
                    toastr.error("{$btr->core_updater_csrf_error|escape:'javascript'}");
                    $btn.prop('disabled', false);
                    return;
                }

                // POST виконується синхронно і може тривати хвилини — ajax error тут
                // не обов'язково означає провал оновлення: з'єднання браузера могло
                // обірватись (таймаут/мережа), поки сервер продовжує прогін. Перевіряємо
                // реальний стан через status: якщо сервер уже в running чи в термінальному
                // кроці — оновлення живе, лишаємо поллінг; інакше це справжній збій.
                $.ajax({
                    type: 'get',
                    dataType: 'json',
                    url: "{url controller='CoreUpdaterAdmin@status'}",
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
