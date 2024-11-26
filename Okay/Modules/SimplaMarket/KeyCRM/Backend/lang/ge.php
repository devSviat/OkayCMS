<?php

$lang['left_keycrm_title'] = 'KeyCRM';
$lang['left_keycrm_menu'] = 'Sending orders to KeyCRM';
$lang['keycrm_title'] = 'KeyCRM';
$lang['keycrm_key'] = 'API Key';
$lang['keycrm_function'] = 'KeyCRM function';
$lang['keycrm_sources'] = 'Sources';
$lang['keycrm_send_on'] = 'Send to CRM';
$lang['keycrm_send_off'] = 'Do not send to CRM';
$lang['keycrm__count_error_orders'] = 'Mistakes';
$lang['keycrm__count_all_orders'] = 'Total';
$lang['keycrm_key_source'] = 'Source';
$lang['keycrm__slect_assotiation'] = 'Select an association';
$lang['keycrm__send_already_to_CRM'] = 'Send to CRM';
$lang['keycrm__sent_already_to_CRM'] = 'Sent to KeyCRM';
$lang['keycrm__send_to_CRM'] = 'Send order to KeyCRM';
$lang['keycrm__error_crm'] = 'Last error while importing into KeyCRM';
$lang['keycrm__errors_crm'] = 'KeyCRM error';
$lang['keycrm_work_orders_ok'] = 'Successful order batching';
$lang['keycrm_work_orders_time'] = 'Operation execution time, s';
$lang['keycrm_work_orders'] = 'Orders have been processed';
$lang['keycrm_work_orders2'] = 'unit';
$lang['keycrm__send_to_CRM_placehold'] = 'If the checkbox is checked, this order will be sent or updated to KeyCRM when saved';
$lang['keycrm__count_send_orders'] = 'Number of unsent orders';
$lang['keycrm__amount_paket_orders'] = 'Select the number of orders in one shipment package';
$lang['keycrm__amount_paket_orders_no_select'] = 'Send all orders without limits';
$lang['keycrm_key_all_order'] = 'Starting sending a batch of orders to CRM';
$lang['keycrm_key_send_all_order_through_cron'] = 'Allow all orders to be sent to CRM via Cron scheduler';
$lang['keycrm_key_send_all_order_through_cron_placehold'] = 'With this setting you can easily activate/deactivate the functionality of sending orders through Cron';
$lang['keycrm_key_description1'] = '1) You need to enter a key.';
$lang['keycrm_key_description2'] = '2) Under what statuses to send an order';
$lang['keycrm_key_description3'] = '3) Payment to shipping ratio';
$lang['used_add_timer_block__date_before'] = 'Enable order burst limit and transfer starting from ';
$lang['used_add_timer_block__date_before_placehold'] = 'If you enable this feature, you will be able to restrict and transfer orders starting from the selected date. This will be useful if you do NOT want to transfer all old orders.';
$lang['keycrm__checkbox_disable_send_delivery_price'] = 'The functionality disables the transfer of shipping costs to CRM when paid shipping exceeds the total cost of the order and falls into the category of free shipping within the framework of the online store.<strong>By default, shipping costs are also sent included in the discount</strong>';
$lang['keycrm__checkbox_disable_send_separate_delivery_price'] = 'After activating the functionality which, when paying separately for delivery, the delivery cost will be sent to CRM, includes adding this amount to the discount, and is also displayed in the delivery cost field. Has an informational character. <strong>Not passed by default</strong>';
$lang['keycrm__cron_title'] = 'Cron';
$lang['keycrm__send_status_when_update'] = 'Enable sending order status when updating data in CRM';
$lang['keycrm__send_status_when_update_info'] = 'When the order data is already in CRM, then in the admin panel, when saving the order (if the checkbox is active, send to CRM), the current status of the order on the site will also be sent to the admin. panels in the order card';
$lang['keycrm__cron_description1'] = 'If there is a need to send new orders using the Cron scheduler, then this can be done';
$lang['keycrm__cron_description4'] = 'the way to start it is to add an individual script to the Cron scheduler to send orders, the code is as follows:';
$lang['keycrm__cron_description5'] = 'Also, in the settings above on this page, you can disable sending orders via Cron.';
$lang['keycrm__activate_debug'] = 'Enable debug mode';
$lang['keycrm__activate_debug_info'] = 'When debug mode is activated, data is written to a file inside the module for a more detailed analysis of supplied data and responses from the service';
$lang['keycrm_key_description_title'] = 'Step 1. Add a source to KeyCRM';
$lang['keycrm_key_description_title2'] = 'Step 2: Get the KeyCRM API Key';
$lang['keycrm_key_description_title3'] = 'Step 3. Set up the module in Okay CMS';
$lang['keycrm_key_description_delivery_title'] = 'Delivery Methods';
$lang['keycrm_key_description_payment_method_title'] = 'Payment Methods';
$lang['keycrm_key_description_order_statuses'] = 'Order statuses';
$lang['keycrm_key_description_attention_title'] = 'Attention!';
$lang['keycrm_key_description_attention_description'] = 'In order to correctly send orders to KeyCRM, you must select all the corresponding order statuses, delivery methods and payment methods. To ensure that orders are correctly transferred to CRM. ';
$lang['keycrm__actualize_send_orders'] = 'Update the details of already submitted orders';
$lang['kkeycrm__actualize_send_orders_info'] = 'When the functionality is enabled, for all orders that have already been sent to CRM, the payment status and some order data will be updated with the status if there is an associated status id from CRM for the status from sata';
$lang['keycrm_status_info'] = 'For the right column to correctly display status names coming from CRM. You need to save all status names in your account in KeyCRM.';
$lang['keycrm_key_description4'] = 'Go to Settings → Sources → and click the \'Add Source\' button. In the window that appears:
Specify the name of the source;
Select the type of source - "Okay CMS";
Enter the URL of your store;
If necessary, select a manager for whom orders will be received;
Specify source currency;
On the \'User Access\' tab, specify who will have access to orders from this source.';
$lang['keycrm_key_description5'] = 'Go to Settings → General and copy the key: ';
$lang['keycrm_key_description6'] = 'In the module settings, add the copied KeyCRM API key and click Apply.';
$lang['keycrm_key_description7'] = 'After this:
In the "Source" field (1), select the source that you created in KeyCRM;
Set from which statuses (2) orders will be sent to KeyCRM;
Set up correspondence of payment methods (3) and delivery (4) in Okay CMS with methods in KeyCRM;
If you want to immediately send all orders that are already in Okay CMS to KeyCRM, then check the box “All order send in CRM” (5);

When everything is set up, click "Apply". Orders will start coming to KeyCRM.
';
$lang['keycrm_key_description8'] = 'Data is sent to CRM during checkout on the page when the order is opened. A rule has been added to not ship if an order is placed more than 48 hours before.';
$lang['keycrm_key_description9'] = 'But when an order changes to paid, the data is sent to CRM.';
$lang['keycrm_installation_title'] = 'Installation and Update';
$lang['keycrm_installation_description_1'] = 'The module is integrated automatically into the order page in the user area of the site.';
$lang['keycrm_installation_description_2'] = 'Also in the admin panel in the order card there is information and control of sending the order to CRM.';
$lang['keycrm_installation_description_3'] = 'Please note. When updating the module to version 1.2.0, it is necessary to reconfigure the order status association block and settings for sending orders with certain statuses';

