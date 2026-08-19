{$wrapper='' scope=global}
<!DOCTYPE html>
<html lang="{$manager->lang|escape}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$btr->file_picker_title|escape}</title>
    {$ok_head}
    <style>
        .file_picker {
            padding: 16px;
        }
        .file_picker__bar {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 16px;
        }
        .file_picker__search {
            flex: 1 1 auto;
            margin: 0;
        }
        .file_picker__file {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
        }
        .file_picker__grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }
        .file_picker__item {
            position: relative;
            display: block;
            width: 100%;
            padding: 8px;
            border: 1px solid var(--ok-line);
            border-radius: var(--ok-radius);
            background: var(--ok-surface);
            text-align: center;
            cursor: pointer;
        }
        .file_picker__item:hover {
            border-color: var(--ok-accent);
        }
        .file_picker__thumb {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 96px;
            margin-bottom: 6px;
            overflow: hidden;
        }
        .file_picker__thumb img {
            max-width: 100%;
            max-height: 96px;
        }
        .file_picker__ext {
            font-size: 20px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--ok-ink-muted);
        }
        .file_picker__name {
            display: block;
            overflow: hidden;
            font-size: 12px;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .file_picker__delete {
            position: absolute;
            top: 4px;
            right: 4px;
            display: none;
            padding: 2px 6px;
            border: 0;
            border-radius: var(--ok-radius);
            background: var(--ok-surface);
            color: var(--ok-danger);
            cursor: pointer;
        }
        .file_picker__cell:hover .file_picker__delete {
            display: block;
        }
        .file_picker__cell {
            position: relative;
        }
        .file_picker__empty {
            padding: 40px 0;
            text-align: center;
            color: var(--ok-ink-muted);
        }
        .file_picker__pages {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 16px;
        }
    </style>
</head>
<body>
<div class="file_picker fn_picker" data-session="{$smarty.session.id}" data-path="{$picker_path|escape}"
     data-confirm="{$btr->file_picker_delete_confirm|escape}" data-error="{$btr->file_picker_upload_error|escape}">

    <div class="file_picker__bar">
        <form class="file_picker__search" method="get" action="index.php">
            <input type="hidden" name="controller" value="FilePickerAdmin">
            <input type="hidden" name="filetype" value="{$picker_type|escape}">
            <input type="hidden" name="path" value="{$picker_path|escape}">
            <input type="text" name="q" class="form-control" value="{$picker_query|escape}"
                   placeholder="{$btr->file_picker_search|escape}">
        </form>

        <label class="btn btn_small btn-info">
            <input type="file" class="file_picker__file fn_picker_file" multiple>
            {$btr->file_picker_upload|escape}
        </label>
    </div>

    <div class="file_picker__grid">
        {if $picker_parent !== null}
            <a class="file_picker__item" href="index.php?controller=FilePickerAdmin&amp;filetype={$picker_type|escape}&amp;path={$picker_parent|escape}">
                <span class="file_picker__thumb"><span class="file_picker__ext">..</span></span>
                <span class="file_picker__name">{$btr->file_picker_up|escape}</span>
            </a>
        {/if}

        {foreach $picker_folders as $folder}
            <a class="file_picker__item" href="index.php?controller=FilePickerAdmin&amp;filetype={$picker_type|escape}&amp;path={$folder.path|escape}">
                <span class="file_picker__thumb">{include file='svg_icon.tpl' svgId='folder'}</span>
                <span class="file_picker__name">{$folder.name|escape}</span>
            </a>
        {/foreach}

        {foreach $picker_files as $file}
            <div class="file_picker__cell">
                <button type="button" class="file_picker__item fn_picker_pick" data-url="{$file.url|escape}">
                    <span class="file_picker__thumb">
                        {if $file.isImage}
                            <img loading="lazy" src="{$file.url|escape}" alt="">
                        {else}
                            <span class="file_picker__ext">{$file.extension|escape}</span>
                        {/if}
                    </span>
                    <span class="file_picker__name" title="{$file.name|escape}">{$file.name|escape}</span>
                </button>
                <button type="button" class="file_picker__delete fn_picker_delete"
                        data-name="{$file.name|escape}" title="{$btr->file_picker_delete|escape}">&times;</button>
            </div>
        {/foreach}
    </div>

    {if !$picker_files && !$picker_folders}
        <div class="file_picker__empty">{$btr->file_picker_empty|escape}</div>
    {/if}

    {if $picker_pages_count > 1}
        <div class="file_picker__pages">
            {for $page=1 to $picker_pages_count}
                <a class="btn btn_small {if $page == $picker_page}btn-info{/if}"
                   href="index.php?controller=FilePickerAdmin&amp;filetype={$picker_type|escape}&amp;path={$picker_path|escape}&amp;q={$picker_query|escape}&amp;page={$page}">{$page}</a>
            {/for}
        </div>
    {/if}
</div>

<script src="design/js/okay-file-picker.js?v={$config->version|escape}"></script>
</body>
</html>
