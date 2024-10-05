<?php
defined('BASEPATH') or exit('No direct script access allowed');
/**
 * This is a basic Payment Management controller to make and verify payments
 * CodeIgniter API Controller
 *
 * @package         CodeIgniter
 * @category        Controller
 * @author          Themesic Interactive
 */
class Stripe_sepa extends App_Controller
{
    /**
     * Show the message whether Bank Details updated successfully.
     * @var mixed $data having an array data
     * @var mixed $invoice_data Get invoice data by invoiceid.
     * @var mixed $client Get client by clientid.
     * @return void
     */
    public function update_bank_details()
    {
        check_invoice_restrictions($this->input->get('invoiceid'), $this->input->get('hash'));
        if (is_client_logged_in()) {
            // $data['contact'] = $this->clients_model->get_contact(get_contact_user_id());
            $contact = $this->clients_model->get_contact(get_contact_user_id());
            if (!$contact) {
                set_alert('warning', 'Contact information could not be retrieved.');
                redirect(site_url('stripe_sepa/gateways/stripe_sepa/make_payment?invoiceid=' . $this->input->get('invoiceid') . '&total=' . $this->input->get('total') . '&hash=' . $this->input->get('hash')));
            }
            $data['contact'] = $contact;
        } else {
            redirect(site_url('authentication'));
        }
        if ($this->input->post()) {
            $data = $this->input->post();
            $this->load->model('invoices_model');
            $invoice_data = $this->invoices_model->get($this->input->get('invoiceid'));
            $client = get_client($invoice_data->clientid);
            $custom_fields = $data['custom_fields'];
            handle_custom_fields_post(get_contact_user_id(), $custom_fields);
            $stripe_sepa_payment_method_id = $this->stripe_sepa_gateway->createPaymentMethod();
            if (empty($stripe_sepa_payment_method_id)) {
                set_alert('warning', 'Field Missing or Invalid Info');
                redirect(site_url('stripe_sepa/gateways/stripe_sepa/make_payment?invoiceid=' . $this->input->get('invoiceid') . '&total=' . $this->input->get('total') . '&hash=' . $this->input->get('hash')));
            } else {
                $this->stripe_sepa_gateway->createCustomer($data['contact'], $stripe_sepa_payment_method_id);
            }
            set_alert('success', 'Details updated successfully');
            redirect(site_url('stripe_sepa/gateways/stripe_sepa/make_payment?invoiceid=' . $this->input->get('invoiceid') . '&total=' . $this->input->get('total') . '&hash=' . $this->input->get('hash')));
        } else {
            set_alert('danger', 'Sorry something went wrong');
            redirect(site_url('stripe_sepa/gateways/stripe_sepa/make_payment?invoiceid=' . $this->input->get('invoiceid') . '&total=' . $this->input->get('total') . '&hash=' . $this->input->get('hash')));
        }
    }
	
    /**
     * Invoice View
     * 
     * @var mixed $invoice_data Get invoice data by invoiceid.
     * @var string $language Load language against customer.
     * @var array $data It's an array.put the given argument as a new element on the end of the array.
     * @var mixed $contact_stripe_sepa_customer_id Get Customer id
     * @return void
     */
    public function make_payment()
    {
        $this->load->library('stripe_sepa_gateway');
        check_invoice_restrictions($this->input->get('invoiceid'), $this->input->get('hash'));
        $this->load->model('invoices_model');
        $invoice = $this->invoices_model->get($this->input->get('invoiceid'));
        $language = load_client_language($invoice->clientid);
        $data['locale'] = get_locale_key($language);
        $data['invoice'] = $invoice;
        if (is_client_logged_in()) {
            $data['contact'] = $this->clients_model->get_contact(get_contact_user_id());
        } else {
            redirect(site_url('authentication'));
        }
        $data['payment_method_id'] = null;
        $contact_payment_method_id = get_contact_meta(get_contact_user_id(), 'contact_stripe_sepa_payment_method_id');
        if (!empty($contact_payment_method_id)) {
            $data['payment_method_id'] = $this->stripe_sepa_gateway->checkPaymentMethod($contact_payment_method_id);
        }
        $data['stripe_sepa_customer_id'] = null;
        $contact_stripe_sepa_customer_id = get_contact_meta(get_contact_user_id(), 'contact_stripe_sepa_customer_id');
        if (!empty($contact_stripe_sepa_customer_id)) {
            $data['stripe_sepa_customer_id'] = $this->stripe_sepa_gateway->checkCustomer($contact_stripe_sepa_customer_id);
        }
        if (empty($data['stripe_sepa_customer_id']) && !empty($data['stripe_sepa_source_id'])) {
            $data['stripe_sepa_customer_id'] = $this->stripe_sepa_gateway->createCustomer($data['contact'], $data['stripe_sepa_source_id']);
        }
        $data['total'] = $this->input->get('total');
        echo $this->get_view($data);
    }
    /**
     * Show message to the customer whether the payment is recorded successfully.
     * @var mixed $stripe_sepa_customer_id Get Customer id
     * @var mixed $stripe_sepa_mandate_id Get Mandate id
     * @var mixed $invoice Get invoice data from invoice model by using invoice id
     * @var mixed $charge Return the payment status
     * @var array $data It's an array.put the given argument as a new element on the end of the array.
     * @return void
     */
    public function finish_payment()
    {
        $this->load->library('stripe_sepa_gateway');
        $stripe_sepa_customer_id =  get_contact_meta(get_contact_user_id(), 'contact_stripe_sepa_customer_id');
        $payment_method_id = get_contact_meta(get_contact_user_id(), 'contact_stripe_sepa_payment_method_id');
        check_invoice_restrictions($this->input->get('invoiceid'), $this->input->get('hash'));
        if ($this->input->post()) {
            $_post = $this->input->post();
            $invoice = $this->invoices_model->get($_post['invoice_id']);
            $data = [
                'amount' => get_invoice_total_left_to_pay($_post['invoice_id']),
                'currency' => $invoice->currency_name,
                'stripe_sepa_customer_id' => $stripe_sepa_customer_id,
                'payment_method' => $payment_method_id,
                'metadata' => ['invoice_id' => $_post['invoice_id'], 'invoice_hash' => $invoice->hash],
            ];
            try {
                $paymentIntent = $this->stripe_sepa_gateway->createPayment($data);
                set_alert('success', _l($paymentIntent->status == 'succeeded' ? 'online_payment_recorded_success' : 'online_payment_recorded_success_fail_database'));
                redirect(site_url('invoice/' . $_post['invoice_id'] . '/' . $_post['hash']));
            } catch (Exception $e) {
                set_alert('danger', $e->getMessage());
                redirect(site_url('invoice/' . $_post['invoice_id'] . '/' . $_post['hash']));
            }
        } else {
            set_alert('danger', 'Transaction could not be processed');
        }
    }
    /**
     * Load payment view with invoice data
     * 
     * @param array $data Client Data
     * @return void
     */
    public function get_view($data = [])
    {
        $this->load->view('stripe_payment', $data);
    }
    /**
     * Handle the stripe webhook
     * 
     * @param  string $key
     * @return void
     */
    public function webhook()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $this->load->library('stripe_sepa_gateway');

        if (isset($input['type']) && $input['type'] === 'payment_intent.succeeded') {
            $paymentIntent = $input['data']['object'];

            if (isset($paymentIntent['metadata']['webhookFor']) && $paymentIntent['metadata']['webhookFor'] === 'stripe_sepa') {
                if ($paymentIntent['status'] === 'succeeded') {
                    $this->stripe_sepa_gateway->addPayment(
                        [
                            'amount'        => floatval($paymentIntent['amount_received']) / 100,
                            'invoiceid'     => $paymentIntent['metadata']['invoice_id'],
                            'paymentmethod' => 'sepa_debit',
                            'transactionid' => $paymentIntent['id'],
                        ]
                    );
                }
            }
        }
        http_response_code(200);
    }
    /**
     * Show message to the customer whether the payment is successfully.
     * @param mixed $id invoice id
     * @param string $hash invoice hash
     * @var mixed $paymentResponse Check payment status
     * @return void
     */
    public function verify_payment()
    {
        $invoiceid = $this->input->get('invoiceid');
        $hash      = $this->input->get('hash');
        check_invoice_restrictions($invoiceid, $hash);
        $this->load->library('stripe_sepa_gateway');
        $this->db->where('id', $invoiceid);
        $invoice = $this->db->get(db_prefix() . 'invoices')->row();
        $paymentResponse = $this->stripe_sepa_gateway->checkPayment($invoice->token);
        if (!empty($paymentResponse) && !empty($paymentResponse->status)) {
            // $data = $paymentResponse->getData();
            if ($paymentResponse->status == 'succeeded') {
                set_alert('success', _l('online_payment_recorded_success'));
            } else {
                set_alert('danger', $paymentResponse->last_payment_error->message ?? 'Payment failed');
            }
        } else {
            set_alert('danger', 'Payment verification failed');
        }
        redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
    }
    /**
     * Check Contact For Stripe SEPA Automatic Payment Detection in Customer by using ClientID
     * @param int $client_id ClientID
     * @return void
     */
    public function automatic_payment($client_id)
    {
        $contact_id  = $this->input->post('contact_id');
        update_customer_meta($client_id, 'stripe_sepa_auto_payment_contactid', $contact_id);
        redirect(admin_url('clients/client/' . $client_id . '?group=stripe_sepa'));
    }
}