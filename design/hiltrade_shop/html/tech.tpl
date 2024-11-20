{$wrapper='' scope=global}
<html>
<head>
    <title>{$settings->site_name}</title>

    {* Favicon *}
    <link rel="icon" type="image/x-icon" href="{$rootUrl}/{$config->design_images|escape}{$settings->site_favicon|escape}?v={$settings->site_favicon_version|escape}">
    <link rel="shortcut icon" type="image/x-icon" href="{$rootUrl}/{$config->design_images|escape}{$settings->site_favicon|escape}?v={$settings->site_favicon_version|escape}">
    <link rel="apple-touch-icon" sizes="180x180" href="{$rootUrl}/{$config->design_images|escape}/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="{$rootUrl}/{$config->design_images|escape}/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="{$rootUrl}/{$config->design_images|escape}/favicon-16x16.png">
    <link rel="mask-icon" href="{$rootUrl}/{$config->design_images|escape}/safari-pinned-tab.svg" color="#2e6f23">
    
</head>
    <body>
    <div class="site_off">
        <div class="site_off_logo">
            <img src="{$rootUrl}/{$config->design_images}{$settings->site_logo}?v={$settings->site_logo_version}" alt="{$settings->site_name|escape}"/>
        </div>
            <div class="site_off_text">
                {* {$settings->site_annotation} *}
            </div>
        </div>
    </body>
</html>
{literal}
<style>
    .site_off{
        height: 100vh;
        display: -webkit-box;
        display: -webkit-flex;
        display: -ms-flexbox;
        display: flex;
        -webkit-box-orient: vertical;
        -webkit-box-direction: normal;
        -webkit-flex-direction: column;
        -ms-flex-direction: column;
        flex-direction: column;
        -webkit-box-pack: center;
        -webkit-justify-content: center;
        -ms-flex-pack: center;
        justify-content: center;
        -webkit-box-align: center;
        -webkit-align-items: center;
        -ms-flex-align: center;
        align-items: center;
        text-align: center;
    }
    .site_off_logo{
        display: flex;
        align-content: center;
        justify-content: center;
        margin-bottom: 2%;
    }
    .site_off_text{
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 12px;
        font-size: 30px;
        line-height: 1.2;
    }
</style>
{/literal}