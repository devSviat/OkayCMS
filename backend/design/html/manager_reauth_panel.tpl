{* Підтвердження паролем (ManagerReauth) у момент дії: захищені форми позначені data-reauth.
   Поки вікно закрите, відправка такої форми спершу питає пароль у модальному вікні, пароль
   перевіряється без перезавантаження (action=reauth, ajax), і та сама дія відправляється далі.
   data-reauth="changed" — лише коли змінено поле з data-reauth-field (свій логін чи email).
   Сервер перевіряє вікно сам, тож без скрипта дія просто відповість «спершу підтвердіть». *}
{if $reauth_fresh}
    {* Пароль щойно підтверджено: до цього часу зміни безпеки не питають його знову. Лише підказка
       стану, тож маленька плашка, а не смуга; колір стану-попередження — це підвищені повноваження. *}
    <div class="reauth_status" role="status">
        {include file='svg_icon.tpl' svgId='clock'}
        <span>{$btr->manager_reauth_open_until|escape} <b class="reauth_status__time">{$reauth_expires_at|date_format:'%H:%M'}</b></span>
        <form method="post" class="reauth_status__form">
            <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
            {if isset($m->id)}<input type="hidden" name="id" value="{$m->id|escape}">{/if}
            <button type="submit" name="action" value="reauth_close" class="reauth_status__end" title="{$btr->manager_reauth_close_hint|escape}">{$btr->manager_reauth_close|escape}</button>
        </form>
    </div>
{/if}

{* Модалка адмінки (okay-modal.js, a11y-dialog): відкриває прихована кнопка data-toggle, вона ж повертає фокус *}
<button type="button" class="fn_reauth_open" data-toggle="modal" data-target="#fn_reauth_modal" hidden></button>
<div id="fn_reauth_modal" class="modal fade" role="dialog" aria-labelledby="fn_reauth_title" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="card-header">
                <div class="heading_modal" id="fn_reauth_title">{$btr->manager_reauth_title|escape}</div>
            </div>
            <div class="modal-body reauth_modal">
                <p class="reauth_modal__hint">{$btr->manager_reauth_modal_hint|escape}</p>
                <form class="fn_reauth_modal_form" autocomplete="off" novalidate>
                    <label class="heading_label" for="fn_reauth_password">{$btr->manager_reauth_password|escape}</label>
                    <input class="form-control" id="fn_reauth_password" type="password" autocomplete="current-password" required/>
                    <p class="settings_error fn_reauth_error" role="alert" hidden></p>
                    <div class="reauth_modal__actions">
                        <button type="submit" class="btn btn_blue btn_small fn_reauth_submit">{$btr->manager_reauth_confirm|escape}</button>
                        <button type="button" class="btn btn-info btn_small fn_reauth_cancel" data-dismiss="modal">{$btr->manager_totp_cancel|escape}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(function () {
        var fresh = {if $reauth_fresh}true{else}false{/if};
        var token = '{$smarty.session.id|escape:'javascript'}';
        var messages = {
            password_wrong: '{$btr->manager_reauth_wrong|escape:'javascript'}',
            limit_try: '{$btr->auth_limit_try|escape:'javascript'}',
            failed: '{$btr->manager_save_failed|escape:'javascript'}'
        };
        var $modal = $('#fn_reauth_modal'), $password = $('#fn_reauth_password'), $error = $modal.find('.fn_reauth_error');
        var pending = null;

        function needsConfirm(form) {
            if (fresh || !form.hasAttribute('data-reauth')) {
                return false;
            }
            if (form.getAttribute('data-reauth') !== 'changed') {
                return true;
            }
            return $(form).find('[data-reauth-field]').toArray().some(function (field) {
                return field.value !== field.defaultValue;
            });
        }

        $(document).on('submit', 'form[data-reauth]', function (event) {
            if (!needsConfirm(this)) {
                return;
            }
            event.preventDefault();
            pending = { form: this, submitter: event.originalEvent ? event.originalEvent.submitter : null };
            $error.prop('hidden', true).text('');
            $password.val('').removeAttr('aria-invalid');
            $('.fn_reauth_open').trigger('click');
            setTimeout(function () { $password.trigger('focus'); }, 50);
        });

        $modal.find('.fn_reauth_modal_form').on('submit', function (event) {
            event.preventDefault();
            var $button = $modal.find('.fn_reauth_submit').prop('disabled', true);
            $.post(window.location.href, { session_id: token, action: 'reauth', ajax: 1, reauth_password: $password.val() }, null, 'json')
                .done(function (result) {
                    if (result && result.ok) {
                        fresh = true;
                        $modal.find('.fn_reauth_cancel').trigger('click');
                        if (pending) {
                            {* Та сама кнопка, що й натиснули: name/value дії йдуть разом із формою *}
                            pending.form.requestSubmit(pending.submitter && pending.submitter.form === pending.form ? pending.submitter : undefined);
                        }
                        return;
                    }
                    $error.text(messages[result && result.error] || messages.failed).prop('hidden', false);
                    $password.attr('aria-invalid', 'true').val('').trigger('focus');
                })
                .fail(function () {
                    $error.text(messages.failed).prop('hidden', false);
                })
                .always(function () { $button.prop('disabled', false); });
        });
    });
</script>
