<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (isset($client)) {
    $contacts = $this->clients_model->get_contacts($client->userid, ['active' => 1]);
    $meta_contact_id = get_customer_meta($client->userid, 'stripe_sepa_auto_payment_contactid');
?>
<h4 class="customer-profile-group-heading"><?php echo _l('stripe_sepa_setting'); ?>
</h4>
<?php echo form_open(site_url('stripe_sepa/gateways/stripe_sepa/automatic_payment/' . $client->userid)); ?>
<div class="form-group">
        <label for="contact_id" class="control-label"> <?php echo _l('stripe_sepa_please_select_contact'); ?></label>
		<br><br>
        <select name="contact_id" id="contact_id" class="selectpicker" data-width="100%">
            <option value=""><?php echo _l('stripe_sepa_select_a_contact'); ?></option>
            <?php
                if (isset($contacts)) {
                    foreach ($contacts as $client_contact) {
                ?>
            <option value="<?= $client_contact['id'];  ?>"
                <?= ($client_contact['id'] == $meta_contact_id) ? 'selected' : '' ?>>
                <?= $client_contact['firstname'] . ' ' . $client_contact['lastname'] ?>
            </option>
            <?php
                    }
                } ?>
        </select>
    <hr />
    <a href="<?php echo admin_url('clients/client/' . $client->userid . '?group=contacts'); ?>"
        class="btn btn-info new-contact"><?php echo _l('stripe_sepa_contact_preview'); ?></a>
    <button type="submit" class="btn btn-info " style="float:right"><?php echo _l('save'); ?></button>
</div>
<?php echo form_close(); ?>
<?php } ?>