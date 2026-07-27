{$wrapper='' scope=global}
{* Maintenance page. $wrapper is cleared, so index.tpl never runs and NONE of
   the theme stylesheets are loaded - tokens.css included. The values below are
   therefore inline by necessity; this is the one template in the theme where
   raw values are not a token violation, because there is no token layer to
   reach. Keep it self-contained. *}
<!DOCTYPE html>
<html{if $language->href_lang} lang="{$language->href_lang|escape}"{/if}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{$settings->site_name|escape}</title>
    {literal}
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background-color: #f5f5f6;
            color: #2b2b2f;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }
        .site_off {
            width: 100%;
            max-width: 480px;
            padding: 40px 32px;
            border: 1px solid #e0e0e2;
            border-radius: 16px;
            background-color: #ffffff;
            text-align: center;
        }
        .site_off_logo { margin-bottom: 24px; }
        .site_off_logo img { max-width: 100%; height: auto; }
        .site_off_text { font-size: 18px; line-height: 1.4; }
        .site_off_text :first-child { margin-top: 0; }
        .site_off_text :last-child { margin-bottom: 0; }
    </style>
    {/literal}
</head>
<body>
    <main class="site_off">
        {if $settings->site_logo}
            <div class="site_off_logo">
                <img src="{$rootUrl}/{$config->design_images}{$settings->site_logo}?v={$settings->site_logo_version|escape}" alt="{$settings->site_name|escape}"/>
            </div>
        {/if}
        <div class="site_off_text">
            {$settings->site_annotation}
        </div>
    </main>
</body>
</html>
