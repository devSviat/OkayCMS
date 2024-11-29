<div class="footer__title">
    <span data-language="working_hours_title">{$lang->working_hours_title}</span>
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
