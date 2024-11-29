{$meta_title = $btr->sviat__working_hours__title|escape scope=global}

{*Назва сторінки*}
<div class="row">
    <div class="col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">
                {$btr->sviat__working_hours__description_title|escape}
            </div>
        </div>
    </div>
    <div class="col-md-12 float-xs-right"></div>
</div>

{*Успішні повідомлення*}
{if $message_success}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--success">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_success == 'saved'}
                            {$btr->general_settings_saved|escape}
                        {/if}
                    </div>
                </div>
                {if $smarty.get.return}
                    <a class="alert__button" href="{$smarty.get.return}">
                        {include file='svg_icon.tpl' svgId='return'}
                        <span>{$btr->general_back|escape}</span>
                    </a>
                {/if}
            </div>
        </div>
    </div>
{/if}

{*Опис*}
<div class="row">
    <div class="col-md-12">
        <div class="alert alert--icon">
            <div class="alert__content">
                <div class="alert__title">{$btr->sviat__working_hours__description_alert_title|escape}</div>
                <p>{$btr->sviat__working_hours__description_text|escape}</p>
            </div>
        </div>
    </div>
</div>

{* Главная форма страницы *}
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="session_id" value="{$smarty.session.id}">

    <div class="row">
        <div class="col-lg-4 pr-0">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box">
                    {$btr->sviat__working_hours__settings_title|escape}
                </div>
                <div class="mt-2 toggle_body_wrap on">
                    <table class="table-working_hours">

                        <thead>
                            <tr>
                                <th>{$btr->sviat__working_hours__day|escape}</th>
                                <th>{$btr->sviat__working_hours__closed|escape}</th>
                                <th>{$btr->sviat__working_hours__opening_time|escape}</th>
                                <th>{$btr->sviat__working_hours__closing_time|escape}</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>{$btr->sviat__working_hours__monday|escape}</td>
                                <td>
                                    <input type="checkbox" id="monday_closed" value="1" name="hours[monday][closed]"
                                        {if $hours.monday.closed}checked{/if} class="hidden_check" />
                                    <label class="working_hours_ckeckbox" for="monday_closed"></label>
                                </td>
                                <td class="with-dash">
                                    <input name="hours[monday][opening_time]" class="form-control" type="time"
                                        value="{$hours.monday.opening_time|escape}"
                                        {if $hours.monday.closed}disabled{/if} />
                                </td>
                                <td>
                                    <input name="hours[monday][closing_time]" class="form-control" type="time"
                                        value="{$hours.monday.closing_time|escape}"
                                        {if $hours.monday.closed}disabled{/if} />
                                </td>
                            </tr>

                            <tr>
                                <td>{$btr->sviat__working_hours__tuesday|escape}</td>
                                <td>
                                    <input type="checkbox" id="tuesday_closed" value="1" name="hours[tuesday][closed]"
                                        {if $hours.tuesday.closed}checked{/if} class="hidden_check" />
                                    <label class="working_hours_ckeckbox" for="tuesday_closed"></label>
                                </td>
                                <td class="with-dash">
                                    <input name="hours[tuesday][opening_time]" class="form-control" type="time"
                                        value="{$hours.tuesday.opening_time|escape}"
                                        {if $hours.tuesday.closed}disabled{/if} />
                                </td>
                                <td>
                                    <input name="hours[tuesday][closing_time]" class="form-control" type="time"
                                        value="{$hours.tuesday.closing_time|escape}"
                                        {if $hours.tuesday.closed}disabled{/if} />
                                </td>
                            </tr>

                            <tr>
                                <td>{$btr->sviat__working_hours__wednesday|escape}</td>
                                <td>
                                    <input type="checkbox" id="wednesday_closed" value="1"
                                        name="hours[wednesday][closed]" {if $hours.wednesday.closed}checked{/if}
                                        class="hidden_check" />
                                    <label class="working_hours_ckeckbox" for="wednesday_closed"></label>
                                </td>
                                <td class="with-dash">
                                    <input name="hours[wednesday][opening_time]" class="form-control" type="time"
                                        value="{$hours.wednesday.opening_time|escape}"
                                        {if $hours.wednesday.closed}disabled{/if} />
                                </td>
                                <td>
                                    <input name="hours[wednesday][closing_time]" class="form-control" type="time"
                                        value="{$hours.wednesday.closing_time|escape}"
                                        {if $hours.wednesday.closed}disabled{/if} />
                                </td>
                            </tr>

                            <tr>
                                <td>{$btr->sviat__working_hours__thursday|escape}</td>
                                <td>
                                    <input type="checkbox" id="thursday_closed" value="1" name="hours[thursday][closed]"
                                        {if $hours.thursday.closed}checked{/if} class="hidden_check" />
                                    <label class="working_hours_ckeckbox" for="thursday_closed"></label>
                                </td>
                                <td class="with-dash">
                                    <input name="hours[thursday][opening_time]" class="form-control" type="time"
                                        value="{$hours.thursday.opening_time|escape}"
                                        {if $hours.thursday.closed}disabled{/if} />
                                </td>
                                <td>
                                    <input name="hours[thursday][closing_time]" class="form-control" type="time"
                                        value="{$hours.thursday.closing_time|escape}"
                                        {if $hours.thursday.closed}disabled{/if} />
                                </td>
                            </tr>

                            <tr>
                                <td>{$btr->sviat__working_hours__friday|escape}</td>
                                <td>
                                    <input type="checkbox" id="friday_closed" value="1" name="hours[friday][closed]"
                                        {if $hours.friday.closed}checked{/if} class="hidden_check" />
                                    <label class="working_hours_ckeckbox" for="friday_closed"></label>
                                </td>
                                <td class="with-dash">
                                    <input name="hours[friday][opening_time]" class="form-control" type="time"
                                        value="{$hours.friday.opening_time|escape}"
                                        {if $hours.friday.closed}disabled{/if} />
                                </td>
                                <td>
                                    <input name="hours[friday][closing_time]" class="form-control" type="time"
                                        value="{$hours.friday.closing_time|escape}"
                                        {if $hours.friday.closed}disabled{/if} />
                                </td>
                            </tr>

                            <tr>
                                <td>{$btr->sviat__working_hours__saturday|escape}</td>
                                <td>
                                    <input type="checkbox" id="saturday_closed" value="1" name="hours[saturday][closed]"
                                        {if $hours.saturday.closed}checked{/if} class="hidden_check" />
                                    <label class="working_hours_ckeckbox" for="saturday_closed"></label>
                                </td>
                                <td class="with-dash">
                                    <input name="hours[saturday][opening_time]" class="form-control" type="time"
                                        value="{$hours.saturday.opening_time|escape}"
                                        {if $hours.saturday.closed}disabled{/if} />
                                </td>
                                <td>
                                    <input name="hours[saturday][closing_time]" class="form-control" type="time"
                                        value="{$hours.saturday.closing_time|escape}"
                                        {if $hours.saturday.closed}disabled{/if} />
                                </td>
                            </tr>

                            <tr>
                                <td>{$btr->sviat__working_hours__sunday|escape}</td>
                                <td>
                                    <input type="checkbox" id="sunday_closed" value="1" name="hours[sunday][closed]"
                                        {if $hours.sunday.closed}checked{/if} class="hidden_check" />
                                    <label class="working_hours_ckeckbox" for="sunday_closed"></label>
                                </td>
                                <td class="with-dash">
                                    <input name="hours[sunday][opening_time]" class="form-control" type="time"
                                        value="{$hours.sunday.opening_time|escape}"
                                        {if $hours.sunday.closed}disabled{/if} />
                                </td>
                                <td>
                                    <input name="hours[sunday][closing_time]" class="form-control" type="time"
                                        value="{$hours.sunday.closing_time|escape}"
                                        {if $hours.sunday.closed}disabled{/if} />
                                </td>
                            </tr>
                        </tbody>

                    </table>

                    <div class="row mt-2">
                        <div class="col-lg-12 col-md-12 ">
                            <button type="submit" class="btn btn_small btn_blue float-md-right">
                                <span>{$btr->general_apply|escape}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box">
                    {$btr->sviat__working_hours__usage_description_title|escape}
                </div>
                <div class="mt-2 toggle_body_wrap on">
                    {$btr->sviat__working_hours__usage_description_text|escape}
                </div>
            </div>
        </div>
    </div>
</form>