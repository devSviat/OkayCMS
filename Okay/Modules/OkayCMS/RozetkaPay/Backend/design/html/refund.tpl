{if $payment_method->name === 'RozetkaPay' && $order->paid}
    <div class="box_btn_heading" style="margin-left: 10px !important;">
        <button type="button" class="btn btn_small btn-info fn_rozetkapay_refund"
                data-order="{$order->id|escape}"
                data-session="{$smarty.session.id|escape}">
            <span>{$btr->rozetka_pay_refund|escape}</span>
        </button>
    </div>
    {literal}
    <script>
        // Повернення грошей — мутація, тож іде POST-ом із CSRF-токеном: на GET
        // гард у backend/index.php не спрацьовує за побудовою, і повернення
        // виконував будь-який сторонній запит у браузері менеджера.
        //
        // Форма будується в JS і кладеться в кінець body з двох причин: цей
        // блок вставляється всередину форми замовлення, а вкладену форму
        // парсер викидає; кнопка-сабміт тут теж не годиться — вона стала б
        // першою в тій формі, тобто кнопкою за замовчуванням, і Enter у
        // будь-якому полі замовлення робив би повернення.
        document.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('.fn_rozetkapay_refund') : null;
            if (!button) {
                return;
            }

            var form = document.createElement('form');
            form.method = 'post';
            form.action = 'index.php?controller=OkayCMS.RozetkaPay.RefundAdmin@execute';

            var addField = function (name, value) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            };

            addField('order', button.getAttribute('data-order'));
            addField('session_id', button.getAttribute('data-session'));

            document.body.appendChild(form);
            form.submit();
        });
    </script>
    {/literal}
{/if}
