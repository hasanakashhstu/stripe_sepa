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
// /**
//  * Check Contact For Mollie SEPA Automatic Payment Detection in Customer
//  * @param  int $id invoiceid
//  * @return void
//  */
// function stripe_sepa_callback_after_invoice_create_or_updated($id)
// {
//     if (!empty($id) && get_option('paymentmethod_stripe_sepa_auto_payment') == 1) {
//         $invoiceData = stripe_sepa_invoice_data($id);
//         if (!is_null($invoiceData->allowed_payment_modes)) {
//             $allowed_payment_modes = unserialize($invoiceData->allowed_payment_modes);
//             if (in_array('stripe_sepa', $allowed_payment_modes)) {
//                 $meta_contact_id = get_customer_meta($invoiceData->clientid, 'stripe_sepa_auto_payment_contactid');
//                 if (!empty($meta_contact_id)) {
//                     if ($invoiceData->status == 1) {
//                         stripe_sepa_finish_payment($invoiceData);
//                     }
//                 } else {
//                     get_instance()->session->set_flashdata('stripe-message-warning', 'Please Select Contact in Customer-->Stripe SEPA');
//                 }
//             }
//         }
//     }
// }

//MATT CHANGES
function stripe_sepa_callback_after_invoice_create_or_updated($id) {
    if (!empty($id) && get_option('paymentmethod_stripe_sepa_auto_payment') == 1) {
        $invoiceData = stripe_sepa_invoice_data($id);
		if(!empty($invoiceData->contract_id)){
			return;
		}
        // Vérifier si le tag "Attente SEPA" est présent sur la facture
        $currentTags = get_tags_in($id, 'invoice');
        if (in_array('Attente SEPA', $currentTags)) {
            get_instance()->session->set_flashdata('stripe-message-warning', 'Une tentative de paiement est déjà en cours pour cette facture.');
            return;
        }

        if (!is_null($invoiceData->allowed_payment_modes) && in_array('stripe_sepa', unserialize($invoiceData->allowed_payment_modes))) {
            stripe_sepa_finish_payment($invoiceData);
        } 
        // else {
        //     get_instance()->session->set_flashdata('stripe-message-warning', 'Please Select Contact in Customer-->Stripe SEPA');
        // }
    }
}
//Jubayer CHANGES
function stripe_sepa_callback_after_invoice_create_or_updated_for_procontract($id) {
    if (!empty($id) && get_option('paymentmethod_stripe_sepa_auto_payment') == 1) {
        $invoiceData = stripe_sepa_invoice_data($id);
        // Vérifier si le tag "Attente SEPA" est présent sur la facture
        $currentTags = get_tags_in($id, 'invoice');
        if (in_array('Attente SEPA', $currentTags)) {
            get_instance()->session->set_flashdata('stripe-message-warning', 'Une tentative de paiement est déjà en cours pour cette facture.');
            return;
        }

        if (!is_null($invoiceData->allowed_payment_modes) && in_array('stripe_sepa', unserialize($invoiceData->allowed_payment_modes))) {
            stripe_sepa_finish_payment($invoiceData);
        } 
        // else {
        //     get_instance()->session->set_flashdata('stripe-message-warning', 'Please Select Contact in Customer-->Stripe SEPA');
        // }
    }
}
// MATT CHANGE END

// /**
//  * Automatic Payment work successfull/not successfull
//  * @param object $invoiceData Invoice data
//  * @return void
//  */
// function stripe_sepa_finish_payment($invoiceData)
// {
//     // JUBAYER CHANGE
//     // $CI = &get_instance();
//     // $CI->db->where('userid', $invoiceData->clientid);
//     // $CI->db->where('is_primary', 1);
//     // $query = $CI->db->get('tblcontacts');
//     // $result = $query->result();

//     // $meta_contact_id = get_customer_meta($result[0]->id, 'stripe_sepa_auto_payment_contactid');
//     // END OF JUBAYER CHANGE
//     //ORIGINAL CODE
//     $meta_contact_id = get_customer_meta($invoiceData->clientid, 'stripe_sepa_auto_payment_contactid');
//     //ORIGINAL CODE

//     if (!empty($meta_contact_id)) {
//         $CI = &get_instance();
//         $CI->load->library('stripe_sepa_gateway');
//         $stripe_sepa_customer_id =  get_contact_meta($meta_contact_id, 'contact_stripe_sepa_customer_id');
//         $stripe_sepa_source_id = get_contact_meta($meta_contact_id, 'contact_stripe_sepa_source_id');
//         $stripe_sepa_source_id = $CI->stripe_sepa_gateway->checkSource($stripe_sepa_source_id);
//         if (!empty($stripe_sepa_source_id)) {
//             check_invoice_restrictions($invoiceData->id, $invoiceData->hash);
//             $invoice = $CI->invoices_model->get($invoiceData->id);
//             $data = [
//                 'amount' => get_invoice_total_left_to_pay($invoice->id),
//                 'currency' => $invoice->currency_name,
//                 'stripe_sepa_customer_id' => $stripe_sepa_customer_id,
//                 'stripe_sepa_source_id' => $stripe_sepa_source_id,
//                 'metadata' => ['invoice_id' => $invoice->id, 'invoice_hash' => $invoice->hash]
//             ];
//             try {
//                 $charge        = $CI->stripe_sepa_gateway->createPayment($data);

//                 //MATT CHANGES
//                 // Ajoutez le tag ici, indiquant que la facture est Attente SEPA
//                 // Ajouter le tag si le statut du paiement est 'pending'
//                 if ($charge->status == 'pending') {

//                     // Récupérer tous les tags actuels de l'invoice
//                     $currentTags = get_tags_in($invoiceData->id, 'invoice');

//                     // Ajouter le tag "Attente SEPA" si ce n'est pas déjà fait
//                     if (!in_array('Attente SEPA', $currentTags)) {
//                         $currentTags[] = 'Attente SEPA';
//                     }

//                     // Sauvegarder les tags mis à jour
//                     handle_tags_save(implode(',', $currentTags), $invoiceData->id, 'invoice');
//                     // handle_tags_save('Attente SEPA', $invoiceData->id, 'invoice');
//                 }
//                 //MATT END


//                 get_instance()->session->set_flashdata('stripe-message-success', _l($charge->status == 'pending' ? 'online_payment_recorded_success' : 'online_payment_recorded_success_fail_database'));
//             } catch (Exception $e) {
//                 get_instance()->session->set_flashdata('stripe-message-error', $e->getMessage());
//             }
//         } else {
//             get_instance()->session->set_flashdata('stripe-message-warning', 'Auto-Payment Not Done! Source Not Found!');
//         }
//     } else {
//         get_instance()->session->set_flashdata('stripe-message-warning', 'Please Select Contact in Customer-->Stripe SEPA');
//     }
// }

// MATT CHANGE
function stripe_sepa_finish_payment($invoiceData)
{
    $CI = &get_instance();
    $CI->load->library('stripe_sepa_gateway');

    // Récupérer l'ID de contact associé à la facture
    $meta_contact_id = get_customer_meta($invoiceData->clientid, 'stripe_sepa_auto_payment_contactid');

    if (!empty($meta_contact_id)) {
        // Récupérer les ID de client et de source Stripe SEPA existants
        $stripe_sepa_customer_id = get_contact_meta($meta_contact_id, 'contact_stripe_sepa_customer_id');
        $stripe_sepa_source_id = get_contact_meta($meta_contact_id, 'contact_stripe_sepa_source_id');

        // Vérifier ou créer la source Stripe SEPA
        if (empty($stripe_sepa_source_id)) {
            $stripe_sepa_source_id = $CI->stripe_sepa_gateway->createSourceFromAdmin($meta_contact_id);
        } else {
            $stripe_sepa_source_id = $CI->stripe_sepa_gateway->checkSource($stripe_sepa_source_id);
        }

        // Vérifier ou créer le client Stripe SEPA
        if (empty($stripe_sepa_customer_id) && !empty($stripe_sepa_source_id)) {
            $stripe_sepa_customer_id = $CI->stripe_sepa_gateway->createCustomerFromAdmin($meta_contact_id, $stripe_sepa_source_id);
        }

        // Si la source et le client Stripe SEPA sont valides, procéder au paiement
        if (!empty($stripe_sepa_source_id) && !empty($stripe_sepa_customer_id)) {
            check_invoice_restrictions($invoiceData->id, $invoiceData->hash);
            $invoice = $CI->invoices_model->get($invoiceData->id);

            // Préparer les données pour le paiement Stripe SEPA
            $data = [
                'amount' => get_invoice_total_left_to_pay($invoice->id),
                'currency' => $invoice->currency_name,
                'stripe_sepa_customer_id' => $stripe_sepa_customer_id,
                'stripe_sepa_source_id' => $stripe_sepa_source_id,
                'metadata' => ['invoice_id' => $invoice->id, 'invoice_hash' => $invoice->hash]
            ];

            try {
                // Tenter de créer le paiement
                $charge = $CI->stripe_sepa_gateway->createPayment($data);

                // Ajouter un tag si le statut du paiement est 'pending'
                if ($charge->status == 'pending') {
                    // Ajouter le tag "Attente SEPA"
                    // handle_tags_save('Attente SEPA', $invoiceData->id, 'invoice');
                
                    // Récupérer tous les tags actuels de l'invoice
                    $currentTags = get_tags_in($invoiceData->id, 'invoice');

                    // Ajouter le tag "Attente SEPA" si ce n'est pas déjà fait
                    if (!in_array('Attente SEPA', $currentTags)) {
                        $currentTags[] = 'Attente SEPA';
                    }

                    // Sauvegarder les tags mis à jour
                    handle_tags_save(implode(',', $currentTags), $invoiceData->id, 'invoice');
                    // handle_tags_save('Attente SEPA', $invoiceData->id, 'invoice');
                }

                // Afficher un message de succès ou d'échec
                $CI->session->set_flashdata('stripe-message-success', _l($charge->status == 'pending' ? 'online_payment_recorded_success' : 'online_payment_recorded_success_fail_database'));
            } catch (Exception $e) {
                // Gérer les erreurs et afficher un message d'erreur
                $CI->session->set_flashdata('stripe-message-error', $e->getMessage());
            }
        } else {
            // Afficher un message d'erreur si la source ou le client Stripe SEPA n'est pas valide
            $CI->session->set_flashdata('stripe-message-warning', 'Stripe SEPA Source or Customer not valid or not found.');
        }
    } else {
        // Afficher un message d'erreur si l'ID de contact n'est pas trouvé
        $CI->session->set_flashdata('stripe-message-warning', 'Contact metadata for Stripe SEPA payment not found.');
    }
}


