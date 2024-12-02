<div class="footer__title menu_eventer" id="working-hours-trigger">
    <span data-language="working_hours_title">{$lang->working_hours_title}</span>
</div>

<div class="working-hours-list" id="working-hours-list">
    {foreach $allWorkingHours as $day}
        <div class="working-day">
            <span>{$daysTranslation[$day->day]}</span>
            {if $day->closed}
                <span class="closed-text">{$lang->working_hours__status_closed}</span>
            {else}
                {$day->opening_time} - {$day->closing_time}
            {/if}
        </div>
    {/foreach}
</div>


<div class="footer__content footer__menu footer__hidden">
    <div class="footer_time">
        <b>{$status}</b> - {$message}
        {if $showClosedMessage}
            <div class="subscribe__title">
                {$lang->working_hours__message_not_open}
            </div>
        {/if}
    </div>
</div>