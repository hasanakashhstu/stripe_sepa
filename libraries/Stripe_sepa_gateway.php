<?php

defined('BASEPATH') or exit('No direct script access allowed');

// require APP_MODULES_PATH . '/stripe_sepa/vendor/autoload.php';
/**
 * This is a basic Payment Process library to create and check payments
 * CodeIgniter Libraries
 *
 * @package         CodeIgniter
 * @category        Library
 * @author          Themesic Interactive
 */
class Stripe_sepa_gateway extends App_gateway
{
    public $stripe_sepa_api_secret_key = '';
    public function __construct()
    {
        /**
         * Call App_gateway __construct function
         */
        parent::__construct();
        $this->ci->load->library('stripe_core');
        $this->stripe_sepa_api_secret_key =  get_instance()->encryption->decrypt(get_option('paymentmethod_stripe_sepa_api_secret_key'));
        /**
         * REQUIRED
         * Gateway unique id
         * The ID must be alpha/alphanumeric
         */
        $this->setId('stripe_sepa');
        /**
         * REQUIRED
         * Gateway name
         */
        $this->setName('Stripe SEPA');
        if (get_option('paymentmethod_stripe_sepa_currencies') != 'EUR') {
            update_option('paymentmethod_stripe_sepa_currencies', 'EUR');
        }
        /**
         * Add gateway settings
         */
        $this->setSettings([
            [
                'name'      => 'api_secret_key',
                'encrypted' => true,
                'label'     => 'settings_paymentmethod_stripe_api_secret_key',
            ],
            [
                'name'          => 'description_dashboard',
                'label'         => 'settings_paymentmethod_description',
                'type'          => 'textarea',
                'default_value' => 'Payment for Invoice {invoice_number}',
            ],
            [
                'name'          => 'currencies',
                'label'         => 'settings_paymentmethod_currencies',
                'default_value' => 'EUR',
                'field_attributes' => ['disabled' => true],
            ],
            [
                'name'          => 'auto_payment',
                'type'          => 'yes_no',
                'default_value' => 0,
                'label'         => 'settings_paymentmethod_stripe_sepa_auto_payment',
            ]
        ]);
    }
    /**
     * Process the payment
     *
     * @param  array $data Client Data
     * @return void
     */
    public function process_payment($data)
    {
        $redirectGatewayURI = 'stripe_sepa/gateways/stripe_sepa/make_payment';

        $redirectPath = $redirectGatewayURI . '?invoiceid='
            . $data['invoiceid']
            . '&total='
            . $data['amount']
            . '&hash='
            . $data['invoice']->hash;

        redirect(site_url($redirectPath));
    }
    /**
     * Create Payment
     * 
     * @param  array  $data Client Data
     * @return object Payment data
     */
    public function createPayment($data)
    {

        // $webhookKey    = app_generate_hash();
        $invoiceNumber = format_invoice_number($data['metadata']['invoice_id']);
        $description   = str_replace('{invoice_number}', $invoiceNumber, $this->getSetting('description_dashboard'));

        $contact_stripe_sepa_hook_id = get_option('contact_stripe_sepa_hook_id');


        if (empty($contact_stripe_sepa_hook_id)) {
            $this->createHook();
        }

        $stripe = new \Stripe\StripeClient(
            $this->stripe_sepa_api_secret_key
        );
        $webhookFor = 'stripe_sepa';
        $theamount = $data['amount'];
        $payment =  $stripe->charges->create([
            'amount' =>  $theamount * 100,
            'currency' => strtolower($data['currency']),
            'customer' => $data['stripe_sepa_customer_id'],
            'source' => $data['stripe_sepa_source_id'],
            "description" =>  $description,
            "metadata" => [
                'order_id'   => $data['metadata']['invoice_id'],
                'webhookFor' => $webhookFor
            ],
        ]);


        //MATT CHANGES

        // Chemin du fichier de log dans le dossier du module
        $logFile = __DIR__ . '/logs/custom_debug.log';

        // Assurez-vous que le répertoire des logs existe
        if (!file_exists(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }

        // Message à logger
        $logMessage = "[" . date("Y-m-d H:i:s") . "] Début de createPayment pour la facture ID: " . $data['metadata']['invoice_id'] . "\n";


        // Écrire dans le fichier de log
        file_put_contents($logFile, 'Fonction CreatePayment.' . "\n", FILE_APPEND);
        file_put_contents($logFile, $logMessage, FILE_APPEND);

        // Log du statut de payment
        $logStatusMessage = "[" . date("Y-m-d H:i:s") . "] Statut de createPayment pour la facture ID: " . $data['metadata']['invoice_id'] . " est: " . $payment->status . "\n";
        file_put_contents($logFile, $logStatusMessage, FILE_APPEND);

        // Après la création du paiement, vérifiez si le statut est 'pending'
        if ($payment->status == 'pending') {
            // Obtenez l'instance CI pour accéder aux modèles et bibliothèques de Perfex CRM
            $CI = &get_instance();

            // Récupérer tous les tags actuels de la facture
            $currentTags = get_tags_in($data['metadata']['invoice_id'], 'invoice');

            file_put_contents($logFile, 'Tags :'. implode(', ',$currentTags) . "\n", FILE_APPEND);

            // Ajouter le tag "Attente SEPA" si ce n'est pas déjà fait
            if (!in_array('Attente SEPA', $currentTags)) {
                $currentTags[] = 'Attente SEPA'; // Ajoute le tag au tableau des tags

                // Convertir le tableau des tags en chaîne de caractères séparée par des virgules pour la sauvegarde
                $tagsAsString = implode(',', $currentTags);
                file_put_contents($logFile, 'Tags AS String :'. $tagsAsString . "\n", FILE_APPEND);
                // Sauvegarder les tags mis à jour sur la facture
                handle_tags_save($tagsAsString, $data['metadata']['invoice_id'], 'invoice');
            }

            // Vous pouvez choisir de logger le succès de l'ajout du tag ou de traiter d'autres logiques spécifiques ici
        }

        //FIN MATT CHANGES

        return $payment;
    }
    /**
     * Check Payment work flow by using Stripe Sepa Payment ID
     * 
     * @param  string $contact_stripe_sepa_payment_id StripeSepaPaymentID
     * @return object Payment data
     */
    public function checkPayment($contact_stripe_sepa_payment_id)
    {

        $stripe = new \Stripe\StripeClient(
            $this->stripe_sepa_api_secret_key
        );
        $payment = $stripe->charges->retrieve(
            $contact_stripe_sepa_payment_id,
            []
        );

        return $payment;
    }
    /**
     * Create CustomerID by using customerData
     * @param  object $customerData Customer Data
     * @return string CustomerID
     */
    public function createCustomer($customerData, $contact_stripe_sepa_source_id)
    {
        $contact_stripe_sepa_customer_id = get_contact_meta(get_contact_user_id(), 'contact_stripe_sepa_customer_id');
        try {
            $stripe = new \Stripe\StripeClient(
                $this->stripe_sepa_api_secret_key
            );
            if (empty($contact_stripe_sepa_customer_id)) {
                $customer =  $stripe->customers->create([
                    "name" => $customerData->firstname . ' ' . $customerData->lastname,
                    "email" => $customerData->email,
                    'source' =>  $contact_stripe_sepa_source_id,
                ]);
            } else {

                $customer = $stripe->customers->update(
                    $contact_stripe_sepa_customer_id,
                    ['source' =>  $contact_stripe_sepa_source_id]
                );
            }
            update_contact_meta(get_contact_user_id(), 'contact_stripe_sepa_customer_id', $customer->id);
            return $customer->id;
        } catch (Exception $e) {
            return  $e->getMessage();
        }
    }
    /**
     * Check CustomerID by using Stripe Sepa Customer ID
     * @param  string $contact_stripe_sepa_customer_id CustomerID
     * @return string CustomerID
     */
    public function checkCustomer($contact_stripe_sepa_customer_id)
    {
        try {

            $stripe = new \Stripe\StripeClient(
                $this->stripe_sepa_api_secret_key
            );
            $customer =  $stripe->customers->retrieve(
                $contact_stripe_sepa_customer_id,
                []
            );

            if (isset($customer->id)) {
                return $customer->id;
            }
            return null;
        } catch (Exception $e) {

            return null;
        }
    }
    /**
     * Create SourceID 
     * @return string SourceID
     */
    public function createSource()
    {
        try {

            $contacts_stripe_consumer_name = get_custom_field_value(get_contact_user_id(), 'contacts_stripe_sepa_consumer_name', 'contacts');
            $contacts_stripe_consumer_email = get_custom_field_value(get_contact_user_id(), 'contacts_stripe_sepa_consumer_email', 'contacts');
            $contacts_stripe_consumer_iban = get_custom_field_value(get_contact_user_id(), 'contacts_stripe_sepa_consumer_iban', 'contacts');


            if (!empty($contacts_stripe_consumer_name) && !empty($contacts_stripe_consumer_email) && !empty($contacts_stripe_consumer_iban)) {
                $stripe = new \Stripe\StripeClient($this->stripe_sepa_api_secret_key);

                $stripeSource =  $stripe->sources->create(
                    [
                        'type' => 'sepa_debit',
                        'sepa_debit' => ['iban' => $contacts_stripe_consumer_iban],
                        'currency' => 'eur',
                        'owner' => [
                            'name' => $contacts_stripe_consumer_name,
                            'email' => $contacts_stripe_consumer_email,
                        ],
                    ]
                );

                if ($stripeSource->status == "chargeable") {
                    update_contact_meta(get_contact_user_id(), 'contact_stripe_sepa_source_id', $stripeSource->id);
                    return $stripeSource->id;
                }
            }
            return null;
        } catch (Exception $e) {


            print_r($e->getMessage());
            exit;
            return  $e->getMessage();
        }
    }
    /**
     * Check SourceID by using Stripe Sepa Source ID
     * @param  string $contact_stripe_sepa_source_id SourceID
     * @return string SourceID
     */
    public function checkSource($contact_stripe_sepa_source_id)
    {
        try {

            $stripe = new \Stripe\StripeClient(
                $this->stripe_sepa_api_secret_key
            );
            $stripeSource = $stripe->sources->retrieve(
                $contact_stripe_sepa_source_id,
                []
            );
            if ($stripeSource->status == "chargeable") {
                return $stripeSource->id;
            }
            return null;
        } catch (Exception $e) {

            return null;
        }
    }
    /**
     * Create Hook 
     * @return string HookID
     */
    public function createHook()
    {
        try {
            $webhookUrl    = site_url('stripe_sepa/gateways/stripe_sepa/webhook/stripe_sepa');
            $stripe = new \Stripe\StripeClient(
                $this->stripe_sepa_api_secret_key
            );
            $hook = $stripe->webhookEndpoints->create([
                'url' => $webhookUrl,
                'enabled_events' => [
                    'charge.failed',
                    'charge.succeeded',
                ]
            ]);
            update_option('contact_stripe_sepa_hook_id',  $hook->id);
            return $hook->id;
        } catch (Exception $e) {
            print_r($e->getMessage());
            exit;
            return  $e->getMessage();
        }
    }
    /**
     * Check HookID by using Stripe Sepa Hook ID
     * @param  string $contact_stripe_sepa_hook_id HookID
     * @return string HookID
     */
    public function checkHook($contact_stripe_sepa_hook_id)
    {
        try {

            $stripe = new \Stripe\StripeClient(
                $this->stripe_sepa_api_secret_key
            );
            $hook = $stripe->webhookEndpoints->retrieve(
                $contact_stripe_sepa_hook_id,
                []
            );

            if (isset($hook->id) && $hook->status == "enabled") {
                return $hook->id;
            }
            return null;
        } catch (Exception $e) {

            return null;
        }
    }





    //MATT CHANGES

    /**
     * Create a Stripe SEPA source for a specific contact
     * @param  int $contactUserId User ID of the contact
     * @return string|null Stripe SEPA source ID or null if creation fails
     */
    function createSourceFromAdmin($contactUserId)
    {
        $CI = &get_instance();
        $CI->load->library('stripe_sepa_gateway');

        // Retrieve necessary contact information using the provided contact user ID
        $contacts_stripe_consumer_name = get_custom_field_value($contactUserId, 'contacts_stripe_sepa_consumer_name', 'contacts');
        $contacts_stripe_consumer_email = get_custom_field_value($contactUserId, 'contacts_stripe_sepa_consumer_email', 'contacts');
        $contacts_stripe_consumer_iban = get_custom_field_value($contactUserId, 'contacts_stripe_sepa_consumer_iban', 'contacts');

        // Check if all required information is available
        if (!empty($contacts_stripe_consumer_name) && !empty($contacts_stripe_consumer_email) && !empty($contacts_stripe_consumer_iban)) {
            try {
                // Initialize Stripe client with the secret key
                $stripe = new \Stripe\StripeClient(
                    $this->stripe_sepa_api_secret_key
                );


                // Create a new Stripe SEPA source with the contact's details
                $stripeSource = $stripe->sources->create([
                    'type' => 'sepa_debit',
                    'sepa_debit' => ['iban' => $contacts_stripe_consumer_iban],
                    'currency' => 'eur',
                    'owner' => [
                        'name' => $contacts_stripe_consumer_name,
                        'email' => $contacts_stripe_consumer_email,
                    ],
                ]);

                // If the source is created successfully and is chargeable, save its ID and return it
                if ($stripeSource->status == "chargeable") {
                    update_contact_meta($contactUserId, 'contact_stripe_sepa_source_id', $stripeSource->id);
                    return $stripeSource->id;
                }
            } catch (Exception $e) {
                // Handle exceptions and log any errors
                log_message('error', 'Stripe SEPA Source creation failed: ' . $e->getMessage());
            }
        }

        // Return null if the source creation fails or required information is missing
        return null;
    }


    /**
     * Create or update a Stripe SEPA customer for a specific contact
     * @param int $contactUserId User ID of the contact
     * @param string $contact_stripe_sepa_source_id Stripe SEPA source ID
     * @return string|null Stripe SEPA customer ID or null if operation fails
     */
    function createCustomerFromAdmin($contactUserId, $contact_stripe_sepa_source_id)
    {
        $CI = &get_instance();
        $CI->load->library('stripe_sepa_gateway');

        // Récupérer l'ID client Stripe SEPA existant pour le contact
        $contact_stripe_sepa_customer_id = get_contact_meta($contactUserId, 'contact_stripe_sepa_customer_id');

        try {
            // Initialiser le client Stripe avec la clé secrète de l'API
            $stripe = new \Stripe\StripeClient($CI->stripe_sepa_gateway->stripe_sepa_api_secret_key);

            // Récupérer les données du contact
            $customerData = $CI->clients_model->get_contact($contactUserId);
            if (!$customerData) {
                throw new Exception('Contact data not found for user ID: ' . $contactUserId);
            }

            // Créer ou mettre à jour le client Stripe SEPA
            if (empty($contact_stripe_sepa_customer_id)) {
                $customer = $stripe->customers->create([
                    "name" => $customerData->firstname . ' ' . $customerData->lastname,
                    "email" => $customerData->email,
                    'source' => $contact_stripe_sepa_source_id,
                ]);
            } else {
                $customer = $stripe->customers->update(
                    $contact_stripe_sepa_customer_id,
                    ['source' => $contact_stripe_sepa_source_id]
                );
            }

            // Mettre à jour l'ID client Stripe SEPA dans les métadonnées du contact
            update_contact_meta($contactUserId, 'contact_stripe_sepa_customer_id', $customer->id);

            return $customer->id;
        } catch (Exception $e) {
            log_message('error', 'Stripe SEPA Customer creation or update failed: ' . $e->getMessage());
            return null;
        }
    }
}
