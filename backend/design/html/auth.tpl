{$wrapper = '' scope=global}
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <META HTTP-EQUIV="Pragma" CONTENT="no-cache">
    <META HTTP-EQUIV="Expires" CONTENT="-1">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{$btr->auth_title|escape}</title>

    <link href="design/css/okay.css" rel="stylesheet" type="text/css" />
    <link href="design/css/grid.css" rel="stylesheet" type="text/css" />
    <link rel="icon" href="design/images/favicon.png" type="image/x-icon">
    <script src="design/js/jquery/jquery.js?v=4.0.0"></script>
</head>
<body>
<div class="container d-table">
    <div class="d-100vh-va-middle">
        <div class="row">
            <div class="col-md-4 push-md-4">
                <div class="card-group">
                    <div class="card p-2">
                        <div class="auth_heading">
                            <img style="height: 50" src="{$rootUrl}/{$config->design_images}{$settings->site_logo}?v={$settings->site_logo_version}" alt="{$settings->site_name|escape}"/>
                            <div class="card-block text-xs-center">
                                <div class="card-block--version">Version {$config->version|escape}</div>
                            </div>
                        </div>
                        <div class="">
                            {*Форма авторизации*}
                            <form method="post">
                                <input type=hidden name="session_id" value="{$smarty.session.id}">
                                {if $recovery_mod}
                                    <h1 class="auth_heading heading-divider">{$btr->auth_form_login_recovery|escape}</h1>
                                    <p class="auth_heading_promo">{$btr->auth_form_login_recovery2|escape} {$smarty.server.HTTP_HOST|escape}</p>
                                    <input type="hidden" name="code" value="{$recovery_code|escape}">
                                    <div class="input-group mb-1">
                                        <span class="input-group-addon">
                                            {include file='svg_icon.tpl' svgId='pass_icon'}
                                        </span>
                                        <input type="password" name="new_password" value="" autocomplete="new-password" autofocus="" tabindex="1" class="form-control" placeholder="{$btr->auth_form_pass|escape}">
                                    </div>
                                    <div class="input-group mb-1">
                                        <span class="input-group-addon">
                                            {include file='svg_icon.tpl' svgId='pass_icon'}
                                        </span>
                                        <input type="password" name="new_password_check" value="" autocomplete="new-password" tabindex="2" class="form-control" placeholder="{$btr->auth_form_pass2|escape}">
                                    </div>
                                    {if $error_message}
                                        <div class="mb-1 error_box" role="alert">
                                            {if $error_message == 'password_empty'}
                                                {$btr->auth_form_new_pass|escape}
                                            {elseif $error_message == 'password_wrong'}
                                                {$btr->auth_form_pass_mismatch|escape}
                                            {/if}
                                        </div>
                                    {/if}
                                    <div class="auth_buttons">
                                        <button type="submit" value="login" class="auth_buttons__login btn btn_blue btn_big btn-block" tabindex="3">{$btr->auth_form_save_pass|escape}</button>
                                    </div>
                                {else}
                                    <h1 class="auth_heading heading-divider">{$btr->auth_form_login_admin|escape}</h1>
                                    <p class="auth_heading_promo">{$smarty.server.HTTP_HOST|escape}</p>

                                    <div class="input-group mb-1">
                                        <span class="input-group-addon">
                                            {include file='svg_icon.tpl' svgId='user_icon'}
                                        </span>
                                        <input name="login" value="{$login|escape}" type="text" class="form-control" autofocus="" tabindex="1" placeholder="{$btr->auth_form_login_placeholder|escape}">
                                    </div>
                                    <div class="input-group mb-1">
                                        <span class="input-group-addon">
                                            {include file='svg_icon.tpl' svgId='pass_icon'}
                                        </span>
                                        <input type="password" name="password" value="" tabindex="2" class="form-control" placeholder="{$btr->auth_form_pass|escape}">
                                    </div>
                                    {if $error_message}
                                    <div class="mb-1 error_box">
                                        {if $error_message == 'auth_wrong'}
                                        {$btr->auth_wrong|escape}
                                        {if $limit_cnt}<br>{$btr->auth_wrong1|escape} {$limit_cnt} {$limit_cnt|plural:$btr->auth_limit_cnt1:$btr->auth_limit_cnt2:$btr->auth_limit_cnt3}{/if}
                                        {elseif $error_message == 'limit_try'}
                                        {$btr->auth_limit_try|escape}
                                        {/if}
                                    </div>
                                    {/if}
                                    <div class="auth_buttons">
                                        <a class="auth_buttons__recovery link px-0 mb-1 fn_recovery" href="">{$btr->auth_form_recovery|escape}</a>
                                        <button type="submit" value="login" class="auth_buttons__login btn btn_blue btn_big btn-block" tabindex="3">{$btr->auth_form_login|escape}</button>
                                    </div>
                                {/if}
                            </form>
                            <div class="col-xs-12 mt-1 p-h fn_recovery_wrap hidden px-0">
                                <div class="fn_error" style="display: none;margin-bottom:15px;color: #bf1e1e;font-weight: 600;font-size: 15px;"></div>
                                <div class="fn_success" style="display: none;margin-bottom:15px;color: #13bb13;font-weight: 600;font-size: 15px;">{$btr->auth_recovery_success|escape}</div>
                                <label class="fn_recovery_label heading-divider">{$btr->auth_recovery_label|escape}</label>
                                <div class="input-group mb-1">
                                    <span class="input-group-addon">
                                        {include file='svg_icon.tpl' svgId='email'}
                                    </span>
                                    <input type="email" class="form-control fn_email" value="" name="recovery_email" placeholder="E-mail">
                                </div>

                                <button type="button" value="recovery" class="btn btn_border_blue fn_ajax_recover">{$btr->auth_remind|escape}</button>
                            </div>
                            {* <div class="mt-2 hidden-lg-up">
                                <div class="card-block--version">OkayCMS v.{$config->version|escape}</div>
                            </div> *}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $(function () {
        $(document).on("click", ".fn_recovery", function (e) {
            e.preventDefault();
            $(".fn_recovery_wrap").toggleClass("hidden");
            return false;
        });
        $(document).on("click", ".fn_ajax_recover", function () {
            link = window.location.href;
            email = $(".fn_email").val();
            //$(this).attr('disabled',true);
            $.ajax( {
                url: link,
                data: {
                    ajax_recovery : true,
                    recovery_email : email
                },
                method : 'get',
                dataType: 'json',
                success: function(data) {
                    if (data.send){
                        $(".fn_error").hide();
                        $(".fn_success").show();
                        $(".fn_recovery_label").remove();
                        $(".fn_email").remove();
                    } else if (data.error) {
                        switch (data.error) {
                            case 'wrong_email':
                                $(".fn_error").text('{$btr->auth_recovery_email_invalid|escape:'javascript'}');
                                break;
                        }
                        $(".fn_error").show();
                        $(".fn_success").hide();
                    }
                }
            })
        });
    })
</script>
</body>
</html>
