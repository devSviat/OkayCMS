{* Перемикачі прав. Права, яких немає в того, хто редагує, не показуються й не змінюються
   (Managers::determineNewPermissions). system_modules не має свого пункту меню, тож окремо. *}
<div class="fn_perms_all perms_form">
    <div class="perms_form__head">
        <label class="perms_form__all">
            <span class="switch switch-default">
                <input class="switch-input fn_all_perms" value="" type="checkbox"/>
                <span class="switch-label"></span>
                <span class="switch-handle"></span>
            </span>
            <span>{$btr->manager_all_access|escape}</span>
        </label>
        <span class="perms_form__total fn_perms_total"></span>
    </div>
    {foreach $permission as $title => $items}
        <div class="permission_block fn_perms_group">
            <div class="heading_box">
                {$btr->getTranslation({$title})}
                <span class="perms_form__count fn_perms_count"></span>
            </div>
            <div class="permission_boxes row fn_perms_wrap">
                {foreach $items as $key => $item}
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <label class="permission_box">
                            <span>{$item|escape}</span>
                            <span class="switch switch-default">
                                <input class="switch-input fn_item_perm" name="permissions[]" value="{$key|escape}" type="checkbox" {if $m->permissions && in_array($key, $m->permissions)}checked{/if}/>
                                <span class="switch-label"></span>
                                <span class="switch-handle"></span>
                            </span>
                        </label>
                    </div>
                {/foreach}
            </div>
        </div>
        <div class="col-xs-12 clearfix"></div>
    {/foreach}
    {if $manager->permissions && in_array('system_modules', $manager->permissions)}
        <div class="permission_block fn_perms_group">
            <div class="permission_boxes row">
                <div class="col-xl-4 col-lg-4 col-md-6">
                    <label class="permission_box">
                        <span>{$btr->allow_to_visible_system_modules|escape}</span>
                        <span class="switch switch-default">
                            <input class="switch-input fn_item_perm" name="permissions[]" value="system_modules" type="checkbox" {if $m->permissions && in_array('system_modules', $m->permissions)}checked{/if}/>
                            <span class="switch-label"></span>
                            <span class="switch-handle"></span>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    {/if}
</div>

<script>
    $(function () {
        var $form = $('.fn_perms_all');
        var countTemplate = '{$btr->manager_perms_count|escape:'javascript'}';

        {* Лічильник «N з M» у групах і проміжний стан «Всі права», коли ввімкнено частину *}
        function refresh() {
            var all = $form.find('.fn_item_perm'), on = all.filter(':checked').length;
            $form.find('.fn_perms_group').each(function () {
                var items = $(this).find('.fn_item_perm');
                $(this).find('.fn_perms_count').text(countTemplate.replace('%on%', items.filter(':checked').length).replace('%all%', items.length));
            });
            $form.find('.fn_perms_total').text(countTemplate.replace('%on%', on).replace('%all%', all.length));
            var master = $form.find('.fn_all_perms').get(0);
            master.checked = on === all.length && on > 0;
            master.indeterminate = on > 0 && on < all.length;
        }

        $form.on('change', '.fn_all_perms', function () {
            $form.find('.fn_item_perm').prop('checked', this.checked);
            refresh();
        });
        $form.on('change', '.fn_item_perm', refresh);
        refresh();
    });
</script>
