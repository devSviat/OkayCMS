{$wrapper = '' scope=global}
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <META HTTP-EQUIV="Pragma" CONTENT="no-cache">
    <META HTTP-EQUIV="Expires" CONTENT="-1">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin Panel</title>

    <link href="design/css/okay.css" rel="stylesheet" type="text/css" />
    <link href="design/css/grid.css" rel="stylesheet" type="text/css" />
    <link rel="icon" href="design/images/favicon.svg" type="image/x-icon">
    <link rel="shortcut icon" type="image/x-icon" href="design/images/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="design/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="design/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="design/images/favicon-16x16.png">
    <link rel="mask-icon" href="design/images/safari-pinned-tab.svg" color="#2e6f23">
    
    <script src="design/js/jquery/jquery.js"></script>
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
                        </div>
                        <div class="">
                            {*Форма авторизации*}
                            <form method="post">
                                <input type=hidden name="session_id" value="{$smarty.session.id}">
                                {if $recovery_mod}
                                    <h1 class="auth_heading">Password recovery</h1>
                                    <p class="auth_heading_promo">on the site {$smarty.server.HTTP_HOST}</p>
                                    <div class="input-group mb-1">
                                        <span class="input-group-addon admin-addon ">
                                            {include file='svg_icon.tpl' svgId='user_icon'}
                                        </span>
                                        <input name="new_login" value="" type="text" class="form-control admin-form" autofocus="" tabindex="1" placeholder="Enter login">
                                    </div>
                                    <div class="input-group mb-1">
                                        <span class="input-group-addon admin-addon ">
                                            {include file='svg_icon.tpl' svgId='pass_icon'}
                                        </span>
                                        <input type="password" name="new_password" value="" tabindex="2" class="form-control admin-form" placeholder="Enter password">
                                    </div>
                                    <div class="input-group mb-1">
                                        <span class="input-group-addon admin-addon ">
                                            {include file='svg_icon.tpl' svgId='pass_icon'}
                                        </span>
                                        <input type="password" name="new_password_check" value="" tabindex="3" class="form-control admin-form" placeholder="Repeat password">
                                    </div>
                                    <div class="auth_buttons">
                                        <button type="submit" value="login" class="auth_buttons__login btn btn_blue btn_big btn-block" tabindex="3">Sign in</button>
                                    </div>
                                {else}
                                    <h1 class="auth_heading">Login to the control panel</h1>
                                    {* <p class="auth_heading_promo">{$smarty.server.HTTP_HOST}</p> *}

                                    <div class="input-group mb-1">
                                        <span class="input-group-addon admin-addon ">
                                            {include file='svg_icon.tpl' svgId='user_icon'}
                                        </span>
                                        <input name="login" value="{$login}" type="text" class="form-control admin-form" autofocus="" tabindex="1" placeholder="Enter login">
                                    </div>
                                    <div class="input-group mb-1">
                                        <span class="input-group-addon admin-addon ">
                                            {include file='svg_icon.tpl' svgId='pass_icon'}
                                        </span>
                                        <input type="password" name="password" value="" tabindex="2" class="form-control admin-form" placeholder="Enter password">
                                    </div>
                                    {if $error_message}
                                    <div class="mb-1 error_box">
                                        {if $error_message == 'auth_wrong'}
                                        Incorrect login or password.
                                        {if $limit_cnt}<br>Осталось {$limit_cnt} {$limit_cnt|plural:'attempt':'attempts':'attempts'}{/if}
                                        {elseif $error_message == 'limit_try'}
                                        You have exhausted your number of attempts for the day.
                                        {/if}
                                    </div>
                                    {/if}
                                    <div class="auth_buttons">
                                        <a class="auth_buttons__recovery link px-0 mb-1 fn_recovery" href="">Forgot your password?</a>
                                        <button type="submit" value="login" class="auth_buttons__login btn btn_blue btn_big btn-block" tabindex="3">Sign in</button>
                                    </div>
                                {/if}
                            </form>
                            <div class="col-xs-12 mt-1 p-h fn_recovery_wrap hidden px-0">
                                <div class="fn_error" style="display: none;margin-bottom:15px;color: #bf1e1e;font-weight: 600;font-size: 15px;"></div>
                                <div class="fn_success" style="display: none;margin-bottom:15px;color: #13bb13;font-weight: 600;font-size: 15px;">The message has been emailed to the administrator</div>
                                <label class="fn_recovery_label">Enter your email address to recover your password</label>
                                <div class="input-group mb-1">
                                    <span class="input-group-addon admin-addon ">
                                        {include file='svg_icon.tpl' svgId='email'}
                                    </span>
                                    <input type="email" class="form-control admin-form fn_email" value="" name="recovery_email" placeholder="E-mail">
                                </div>

                                <button type="button" value="recovery" class="btn btn_border_blue fn_ajax_recover">Remind me</button>
                            </div>
                        </div>
                    </div>
                    {* <div class="card card-inverse okay_bg py-3 hidden-md-down" style="width:50%">
                        <div class="card-block text-xs-center">
                            <div>
                                <p>
                                    <img src="design/images/system_logo.png" alt="Hiltrade" />
                                </p>
                            </div>
                        </div>
                    </div> *}
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
                                $(".fn_error").text('Enter correct E-mail');
                                break;
                            case 'not_admin_email':
                                $(".fn_error").text('The specified E-mail does not belong to the site administrator');
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
