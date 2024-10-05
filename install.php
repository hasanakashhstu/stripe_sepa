<?php
defined('BASEPATH') or exit('No direct script access allowed');
$CI = &get_instance();
$CI->load->model('custom_fields_model');
if (total_rows(db_prefix() . 'customfields', ['slug' => 'contacts_stripe_sepa_consumer_name']) == 0) {
    $CI->custom_fields_model->add([
        'show_on_client_portal' => 1,
        'field_order' => '',
        'fieldto' => 'contacts',
        'name' => 'Stripe SEPA Consumer Name',
        'type' => 'input',
        'bs_column' => 6
    ]);
}
if (total_rows(db_prefix() . 'customfields', ['slug' => 'contacts_stripe_sepa_consumer_email']) == 0) {
    $CI->custom_fields_model->add([
        'show_on_client_portal' => 1,
        'field_order' => '',
        'fieldto' => 'contacts',
        'name' => 'Stripe SEPA Consumer Email',
        'type' => 'input',
        'bs_column' => 6
    ]);
}
if (total_rows(db_prefix() . 'customfields', ['slug' => 'contacts_stripe_sepa_consumer_iban']) == 0) {
    $CI->custom_fields_model->add([
        'show_on_client_portal' => 1,
        'field_order' => '',
        'fieldto' => 'contacts',
        'name' => 'Stripe SEPA Consumer IBAN',
        'type' => 'input',
        'bs_column' => 6
    ]);
}


