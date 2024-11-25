{* Title *}
{$meta_title=$btr->inform_title scope=parent}


<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="heading_page">
            {if $products_count}
                {$btr->inform_ticket|escape} - {$records_count}
            {else}
                {$btr->inform_no_ticket|escape}
            {/if}
        </div>
    </div>
</div>

{if $products}
<div class="boxed fn_toggle_wrap">
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="boxed_sorting action_options">
                <div class="row">
                    <div class="col-md-3 col-lg-3 col-sm-12">
                        <span>Показывать по: </span>
                        <select class="selectpicker" onchange="location = this.value;">
                            <option value="{url limit=5}" {if $current_limit == 5}selected{/if}>5</option>
                            <option value="{url limit=10}" {if $current_limit == 10}selected{/if}>10</option>
                            <option value="{url limit=25}" {if $current_limit == 25}selected{/if}>25</option>
                            <option value="{url limit=50}" {if $current_limit == 50}selected{/if}>50</option>
                            <option value="{url limit=100}" {if $current_limit == 100}selected=""{/if}>100</option>
                        </select>
                    </div>

                    <div class="col-md-3 col-lg-3 col-sm-12">
                        <span>Сортировать по: </span>
                        <select class="selectpicker" onchange="location = this.value;">
                            <option value="{url sort='name'}" {if $sort == 'name'}selected{/if}>Названии товара</option>
                            <option value="{url sort='informbackinstock'}" {if $sort == 'informbackinstock'}selected{/if}>количеству запросов</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <form class="fn_form_list" method="post">
                    <input type="hidden" name="session_id" value="{$smarty.session.id}">

                    <div class="users_wrap okay_list products_list fn_sort_list">
                        <div class="okay_list_head">
                            <div class="okay_list_heading okay_list_check">
                                <input class="hidden_check fn_check_all" type="checkbox" id="check_all_1" name="" value=""/>
                                <label class="okay_ckeckbox" for="check_all_1"></label>
                            </div>
                            <div class="okay_list_heading okay_list_users_name">
                                <span>{$btr->general_name|escape}</span>
                            </div>
                        </div>
                        <div class="okay_list_body">

                            {foreach $products as $product}
                                {$product_id = $product->id}
                                <div class="fn_row okay_list_body_item fn_sort_item">
                                    <div class="okay_list_row ">

                                        <div class="okay_list_boding okay_list_photo">
                                            {$image = $product->images|@first}
                                            {if $image}
                                                <a href="{url module=ProductAdmin id=$product->id return=$smarty.server.REQUEST_URI}">
                                                    <img src="{$image->filename|escape|resize:35:35}"/></a>
                                            {else}
                                                <img height="35" width="35" src="/backend/design/images/no_image.png"/>
                                            {/if}
                                        </div>

                                        <div class="okay_list_boding okay_list_name">
                                            <a href="{url module=ProductAdmin id=$product->id return=$smarty.server.REQUEST_URI}">{$product->name|escape}</a>
                                            <span>{$brands_name[$product->brand_id]->name}</span>
                                        </div>
                                    </div>
                                    <div class="orders_purchases_block">
                                        <div class="purchases_table">
                                            <div class="purchases_head">
                                                <div class="purchases_heading purchases_table_orders_num">№</div>
                                                <div class="purchases_heading purchases_table_orders_name">{$btr->general_name|escape}</div>
                                            </div>
                                            <div class="purchases_body">
                                                {foreach $records.$product_id as $record}
                                                    <div class="purchases_body_items">
                                                        <div class="purchases_body_item">
                                                            <div class="purchases_bodyng purchases_table_orders_num">{$record@iteration}</div>
                                                            <div class="purchases_bodyng purchases_table_orders_name">
                                                                <a href="mailto:{$record->email|escape}" class="email_record">{$record->email|escape}</a> <b>({$record->name|escape})</b>
                                                            </div>
                                                            <div class="okay_list_boding okay_list_close">
                                                                <button data-hint="{$btr->general_delete|escape}" name="delete_record" value="{$record->id}" class="btn_close hint-bottom-right-t-info-s-small-mobile  hint-anim"">
                                                                    {include file='svg_icon.tpl' svgId='delete'}
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                {/foreach}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            {/foreach}
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-lg-12 col-md-12 col-sm 12 txt_center">
                {include file='pagination.tpl'}
            </div>
        </div>
    {else}
        <div class="heading_box mt-1">
            <div class="text_grey">{$btr->inform_no_ticket|escape}</div>
        </div>
    {/if}
</div>