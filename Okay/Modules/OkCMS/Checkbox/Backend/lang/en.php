<?php

$lang['okcms__checkbox_title'] = 'Checkbox integration';
$lang['okcms__checkbox_settings_saved'] = 'Settings saved';
$lang['okcms__checkbox_login'] = 'Login';
$lang['okcms__checkbox_password'] = 'Password';
$lang['okcms__checkbox_licenseKey'] = 'Cash register license key';
$lang['okcms__checkbox_orderAvailbaleStatus'] = 'Automatic generation and sending of a check when status changes';
$lang['okcms__checkbox_noStatus'] = 'Status not selected';
$lang['okcms__checkbox_receiptText'] = 'Text for check';
$lang['okcms__checkbox_devMode'] = 'Test operation mode';
$lang['okcms__checkbox_cashier_per_manager'] = 'Use a separate cash register for different managers';
$lang['okcms__checkbox_createShift'] = 'Open a cashier shift';
$lang['okcms__checkbox_closeShift'] = 'Close cashier shift';
$lang['okcms__checkbox_openedShift'] = 'Shift is open';
$lang['okcms__checkbox_justCreatedShift'] = 'Shift created but not yet opened';

$lang['okcms__left_checkbox'] = 'Checkbox integration';
$lang['okcms__left_checkbox_settings'] = 'Settings';
$lang['okcms__left_checkbox_taxes'] = 'Tax rates';
$lang['okcms__left_checkbox_receipts'] = 'Deposit/Removal Reports';
$lang['okcms__left_checkbox_shifts'] = 'Shift reports';

$lang['okcms__checkbox_refresh'] = 'Check shift';
$lang['okcms__checkbox_showReport'] = 'Show report';
$lang['okcms__checkbox_shifts_no'] = 'No closed shifts';

$lang['okcms__checkbox_shiftOpenedAt'] = 'Shift open at';
$lang['okcms__checkbox_shiftClosedAt'] = 'Shift closed at';
$lang['okcms__checkbox_shiftStatus'] = 'Status';

$lang['okcms__checkbox_errors_empty_params'] = "The cash register data is not filled in.<br>Working with the cash register is impossible";
$lang['okcms__checkbox_errors_no_shift'] = "A cashier shift has not been created.<br>Working with the cash register is impossible";
$lang['okcms__checkbox_errors_find_order'] = "Order not found. try later";
$lang['okcms__checkbox_errors_find_purchases'] = "No items found in the order. try later";
$lang['okcms__checkbox_errors_empty_receipts_isset'] = "There are unprocessed checks.<br>It is impossible to create a new check";

$lang['okcms__checkbox_order_receipts'] = 'Order receipts';
$lang['okcms__checkbox_order_receipt_create'] = 'Create a check';
$lang['okcms__checkbox_order_receipt_create_return'] = 'Create a refund check';

$lang['okcms__checkbox_order_receipt_date'] = 'Check generation date';
$lang['okcms__checkbox_order_receipt_return'] = 'Return';
$lang['okcms__checkbox_order_receipt_print'] = 'Print';

$lang['okcms__checkbox_orders_receipt_pay'] = 'Payment check';
$lang['okcms__checkbox_orders_receipt_return'] = 'Refund check';

$lang['okcms__checkbox_receipts_service'] = 'Deposit/withdraw funds';
$lang['okcms__checkbox_receipts_service_value'] = 'Enter the deposit/withdrawal value';
$lang['okcms__checkbox_receipts_no'] = 'No deposit/withdrawal operations';
$lang['okcms__checkbox_receipts_title'] = 'Report of deposit/withdrawal transactions';

$lang['okcms__checkbox_receiptServiceCreatedAt'] = 'Check created';
$lang['okcms__checkbox_receiptServiceOperation'] = 'Operation';
$lang['okcms__checkbox_receiptServiceSum'] = 'Sum';
$lang['okcms__checkbox_receiptServiceOperationOut'] = 'Withdrow';
$lang['okcms__checkbox_receiptServiceOperationIn'] = 'Deposit';

$lang['okcms__left_checkbox_taxes_add'] = 'Add tax group';
$lang['okcms__left_checkbox_taxes_code'] = 'Code';
$lang['okcms__left_checkbox_taxes_delete'] = 'Delete tax group';
$lang['okcms__left_checkbox_taxes_no'] = 'No tax groups';
$lang['okcms__left_checkbox_taxes_added'] = 'Tax group added';
$lang['okcms__left_checkbox_taxes_updated'] = 'Tax group updated';
$lang['okcms__left_checkbox_taxes_code'] = 'Code in Checkbox';

$lang['okcms__left_checkbox_taxes_errors_code'] = 'Empty code';
$lang['okcms__left_checkbox_taxes_errors_name'] = 'Emty name';
    $lang['okcms__left_checkbox_taxes_errors_exists'] = 'A tax group with the same code already exists';

$lang['okcms__left_checkbox_taxes_product_tooltip'] = 'A tax group is required to work with the Checkbox service';

$lang['okcms__checkbox_shifts'] = 'Cashier shift report';

$lang['okcms__checkbox_type_cash'] = 'Cash';
$lang['okcms__checkbox_type_cashless'] = 'Cashless payments';

$lang['okcms__checkbox_cron_check_shifts_1'] = 'In order for the site to respond to the opening/closing of a shift, you need to configure one of the following cron tasks in the hosting billing panel with a frequency of 2-5 minutes:';
$lang['okcms__checkbox_cron_check_shifts_2'] = 'OR';
$lang['okcms__checkbox_cron_check_shifts_3'] = "IMPORTANT!!! You need to enable ONLY ONE of the specified cron tasks. When 2 tasks are running, server resources will be uselessly used, because... the tasks are identical.
<br>It is preferable to run the \"php\" command.
<br>On hosting, you may need to specify a different path to the php or wget commands";

$lang['okcms__checkbox_cron_check_receipts_empty_1'] = 'In order for checks to be registered both during open and closed shifts and carried out when the shift opens, you need to configure one of the following cron tasks with a frequency of 2-5 minutes:';
$lang['okcms__checkbox_cron_check_receipts_empty_2'] = 'ИЛИ';
$lang['okcms__checkbox_cron_check_receipts_empty_3'] = "IMPORTANT!!! You need to enable ONLY ONE of the specified cron tasks. When 2 tasks are running, server resources will be uselessly used, because... the tasks are identical.
<br>It is preferable to run the \"php\" command.
<br>On hosting, you may need to specify a different path to the php or wget commands";

$lang['okcms__checkbox_message_howSend'] = 'Send a check to';
$lang['okcms__checkbox_message_notSend'] = 'Do not send';
$lang['okcms__checkbox_message_email'] = 'By Email';
$lang['okcms__checkbox_message_sms'] = 'By SMS';
$lang['okcms__checkbox_message_email_sms'] = 'By Email and SMS';
$lang['okcms__checkbox_message_sms_if_not_email'] = 'Email. SMS if Email is not specified';
$lang['okcms__checkbox_message_email_if_not_sms'] = 'SMS. Email if phone number is not specified';

$lang['okcms__checkbox_order_sended_date'] = 'Message sent';

