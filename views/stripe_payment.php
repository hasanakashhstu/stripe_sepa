<?php
echo payment_gateway_head(_l('payment_for_invoice') . ' ' . format_invoice_number($invoice->id)); ?>

<body class="gateway-stripe">
    <div class="container">
        <div class="col-md-8 col-md-offset-2 mtop30">
            <div class="mbot30 text-center">
                <?php echo payment_gateway_logo(); ?>
            </div>
            <div class="row">
                <?php
                $alertclass = "";
                if ($this->session->flashdata('message-success')) {
                    $alertclass = "success";
                } else if ($this->session->flashdata('message-warning')) {
                    $alertclass = "warning";
                } else if ($this->session->flashdata('message-info')) {
                    $alertclass = "info";
                } else if ($this->session->flashdata('message-danger')) {
                    $alertclass = "danger";
                }
                if ($this->session->flashdata('message-' . $alertclass)) { ?>
                <div class="col-lg-12" id="alerts">
                    <div class="text-center alert alert-<?php echo $alertclass; ?>">
                        <?php
                            echo $this->session->flashdata('message-' . $alertclass);
                            ?>
                    </div>
                </div>
                <?php } ?>
            </div>
            <div class="row">
                <div class="panel_s">
                    <div class="panel-body">
                        <h3 class="no-margin">
                            <b><?php echo _l('payment_for_invoice'); ?></b>
                            <a href="<?php echo site_url('invoice/' . $invoice->id . '/' . $invoice->hash); ?>">
                                <b>
                                    <?php echo format_invoice_number($invoice->id); ?>
                                </b>
                            </a>
                        </h3>
                        <h4><?php echo _l('payment_total', app_format_money($total, $invoice->currency_name)); ?></h4>
                        <hr />
                        <?php
                        $bank_details = ['contacts_stripe_sepa_consumer_name', 'contacts_stripe_sepa_consumer_email', 'contacts_stripe_sepa_consumer_iban'];
                        $custom_fields = get_custom_fields('contacts');
                        $user_bank = [];
                        $bank_details_missing = false;
                        foreach ($custom_fields as $field) {
                            if (in_array($field['slug'], $bank_details)) {
                                $user_bank[$field['slug']] = get_custom_field_value(get_contact_user_id(), $field['id'], 'contacts');
                            }
                        }
                        if (empty($user_bank['contacts_stripe_sepa_consumer_name']) || empty($user_bank['contacts_stripe_sepa_consumer_email']) || empty($user_bank['contacts_stripe_sepa_consumer_iban']) || empty($stripe_sepa_source_id) || empty($stripe_sepa_customer_id)) {
                            echo form_open(site_url('stripe_sepa/gateways/stripe_sepa/update_bank_details?invoiceid=' . $invoice->id . '&total=' . $total . '&hash=' . $invoice->hash), array('id' => 'bank_details_submit'));
                            echo render_custom_fields(
                                'contacts',
                                get_contact_user_id(),
                                "(show_on_client_portal = 1 AND slug IN ('contacts_stripe_sepa_consumer_name','contacts_stripe_sepa_consumer_email','contacts_stripe_sepa_consumer_iban'))"
                            );
                            foreach ($custom_fields as $bank_fields) {
                                if (in_array($bank_fields['slug'], $bank_details)) {
                                    ${$bank_fields['slug']} = $bank_fields['id'];
                                }
                            }
                            echo '<div class="col-md-6">';
                            echo '<button type="submit" name="update_bank_details" value="true" class="btn btn-success mbot15">';
                            echo  'update bank details';
                            echo '</button>';
                            echo '</div>';
                            echo form_close();
                        } else { ?>
                        <p><b><?= _l('stripe_consumer_name') ?>: </b>
                            <?= $user_bank['contacts_stripe_sepa_consumer_name']; ?></p>
                        <p><b><?= _l('stripe_consumer_account') ?>: </b>
                            <?= substr($user_bank['contacts_stripe_sepa_consumer_iban'], -4); ?></p>
                        <?php
                            echo form_open(site_url('stripe_sepa/gateways/stripe_sepa/finish_payment?invoiceid=' . $invoice->id . '&total=' . $total . '&hash=' . $invoice->hash), array('id' => 'finish_payment'));
                            echo '<input type="hidden" name="amount" value="' . $total . '">';
                            echo '<input type="hidden" name="invoice_id" value="' . $invoice->id . '">';
                            echo '<input type="hidden" name="hash" value="' . $invoice->hash . '">';
                            echo '<div class="col-md-6">';
                            echo '<button type="submit"  id="finish_payment" value="true" class="btn btn-success mbot15">';
                            echo  'Pay Now';
                            echo '</button>';
                            echo '</div>';
                            echo form_close();
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php echo payment_gateway_scripts(); ?>
        <?php if (!isset($stripe_customer) || (isset($stripe_customer) && empty($stripe_customer->default_source))) { ?>
        <script>
        $(function() {
            $('.stripe-button-el').click();
        });
        </script>
        <?php } ?>
        <?php echo payment_gateway_footer(); ?>
        <script src="https://code.jquery.com/jquery-3.4.1.min.js"
            integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>