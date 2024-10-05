<?php

defined('BASEPATH') or exit('No direct script access allowed');
hooks()->add_filter('before_update_contact', '_remove_stripe_sepa_token_on_contact_update', 10, 2);
/**
 * Remove and format some common used data for the sales feature eq invoice,estimates etc..
 * @param  array $data $_POST data
 * @return array
 */
function _remove_stripe_sepa_token_on_contact_update($data, $id = null)
{
    delete_contact_meta($id, 'contact_stripe_sepa_customer_id');
    delete_contact_meta($id, 'contact_stripe_sepa_source_id');
    return $data;
}
// Invoices Functions 
/**
 * Get invoice Data by ID
 * @param int $id invoiceid
 * @return mixed Invoice data
 */
function stripe_sepa_invoice_data($id)
{
    $CI   = &get_instance();
    $CI->load->model('invoices_model');
    return $CI->invoices_model->get($id);
}
/**
 * Check Contact For Mollie SEPA Automatic Payment Detection in Customer
 * @param  int $id invoiceid
 * @return void
 */
function stripe_sepa_callback_after_invoice_create_or_updated($id)
{
    if (!empty($id) && get_option('paymentmethod_stripe_sepa_auto_payment') == 1) {
        $invoiceData = stripe_sepa_invoice_data($id);
        if (!is_null($invoiceData->allowed_payment_modes)) {
            $allowed_payment_modes = unserialize($invoiceData->allowed_payment_modes);
            if (in_array('stripe_sepa', $allowed_payment_modes)) {
                $meta_contact_id = get_customer_meta($invoiceData->clientid, 'stripe_sepa_auto_payment_contactid');
                if (!empty($meta_contact_id)) {
                    if ($invoiceData->status == 1) {
                        stripe_sepa_finish_payment($invoiceData);
                    }
                } else {
                    get_instance()->session->set_flashdata('stripe-message-warning', 'Please Select Contact in Customer-->Stripe SEPA');
                }
            }
        }
    }
}
/**
 * Automatic Payment work successfull/not successfull
 * @param object $invoiceData Invoice data
 * @return void
 */
function stripe_sepa_finish_payment($invoiceData)
{
    $meta_contact_id = get_customer_meta($invoiceData->clientid, 'stripe_sepa_auto_payment_contactid');
    if (!empty($meta_contact_id)) {
        $CI = &get_instance();
        $CI->load->library('stripe_sepa_gateway');
        $stripe_sepa_customer_id =  get_contact_meta($meta_contact_id, 'contact_stripe_sepa_customer_id');
        $payment_method_id = get_contact_meta($meta_contact_id, 'contact_stripe_sepa_payment_method_id');
        $payment_method_id = $CI->stripe_sepa_gateway->checkPaymentMethod($payment_method_id);
        if (!empty($payment_method_id)) {
            check_invoice_restrictions($invoiceData->id, $invoiceData->hash);
            $invoice = $CI->invoices_model->get($invoiceData->id);
            $data = [
                'amount' => get_invoice_total_left_to_pay($invoice->id),
                'currency' => $invoice->currency_name,
                'stripe_sepa_customer_id' => $stripe_sepa_customer_id,
                'payment_method' => $payment_method_id,
                'metadata' => ['invoice_id' => $invoice->id, 'invoice_hash' => $invoice->hash],
            ];
            try {
                $paymentIntent = $CI->stripe_sepa_gateway->createPayment($data);

                if ($paymentIntent->status == 'succeeded' || $paymentIntent->status == 'requires_action') {
                    get_instance()->session->set_flashdata('stripe-message-success', _l('online_payment_recorded_success'));
                } else {
                    get_instance()->session->set_flashdata('stripe-message-success', _l('online_payment_recorded_success_fail_database'));
                }
            } catch (Exception $e) {
                get_instance()->session->set_flashdata('stripe-message-error', $e->getMessage());
            }
        } else {
            $errorMessage = 'Auto-Payment Not Done! Payment Method Not Found! Payment Method ID: ' . $payment_method_id;
            get_instance()->session->set_flashdata('stripe-message-warning', $errorMessage);
        }
    } else {
        get_instance()->session->set_flashdata('stripe-message-warning', 'Please Select Contact in Customer-->Stripe SEPA');
    }
}