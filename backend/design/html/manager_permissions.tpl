{* Перемикачі прав. Права, яких немає в того, хто редагує, не показуються й не змінюються
   (Managers::determineNewPermissions). system_modules не має свого пункту меню, тож окремо. *}
<div class="boxed fn_toggle_wrap">
    <div class="heading_box">
        {$btr->manager_rights|escape}
        <span class="font_14 text_600 ml-1">{$btr->manager_all_access|escape}</span>
        <label class="switch switch-default">
            <input class="switch-input fn_all_perms" value="" type="checkbox"/>
            <span class="switch-label"></span>
            <span class="switch-handle"></span>
        </label>
    </div>
    {foreach $permission as $title => $items}
        <div class="permission_block">
            <div class="heading_box">{$btr->getTranslation({$title})}</div>
            <div class="permission_boxes row fn_perms_wrap">
                {foreach $items as $key => $item}
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="permission_box">
                            <span>{$item|escape}</span>
                            <label class="switch switch-default">
                                <input class="switch-input fn_item_perm" name="permissions[]" value="{$key|escape}" type="checkbox" {if $m->permissions && in_array($key, $m->permissions)}checked{/if}/>
                                <span class="switch-label"></span>
                                <span class="switch-handle"></span>
                            </label>
                        </div>
                    </div>
                {/foreach}
            </div>
        </div>
        <div class="col-xs-12 clearfix"></div>
    {/foreach}
    {if $manager->permissions && in_array('system_modules', $manager->permissions)}
        <div class="permission_block">
            <div class="permission_boxes row">
                <div class="col-xl-4 col-lg-4 col-md-6">
                    <div class="permission_box">
                        <span>{$btr->allow_to_visible_system_modules|escape}</span>
                        <label class="switch switch-default">
                            <input class="switch-input fn_item_perm" name="permissions[]" value="system_modules" type="checkbox" {if $m->permissions && in_array('system_modules', $m->permissions)}checked{/if}/>
                            <span class="switch-label"></span>
                            <span class="switch-handle"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    {/if}
</div>

<script>
    $(document).on("change", ".fn_all_perms", function () {
        var on = $(this).is(":checked");
        $('.fn_item_perm').each(function () {
            if ($(this).is(":checked") !== on) {
                $(this).trigger("click");
            }
        });
    });
</script>
