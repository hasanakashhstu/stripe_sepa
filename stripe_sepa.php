<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Stripe SEPA Payments
Description: Receive SEPA Payments in Perfex CRM
Version: 1.0.1
Requires at least: 2.3.*
Author: Themesic Interactive
Author URI: https://codecanyon.net/user/themesic/portfolio
Module URI: https://codecanyon.net/item/stripe-sepa-direct-debit-payment-gateway-for-perfex/46175176
*/
require_once __DIR__.'/vendor/autoload.php';
define('STRIPE_SEPA_MODULE_NAME', 'stripe_sepa');
register_payment_gateway('stripe_sepa_gateway', 'stripe_sepa');
get_instance()->load->helper('stripe_sepa/stripe_sepa');

hooks()->add_action('after_invoice_added', 'stripe_sepa_callback_after_invoice_create_or_updated');
hooks()->add_action('after_invoice_updated', 'stripe_sepa_callback_after_invoice_create_or_updated');
hooks()->add_action('admin_init', 'stripe_sepa_module_init_menu_items');
hooks()->add_action('app_admin_head', 'stripe_sepa_admin_head_data');
hooks()->add_action('admin_init', 'stripe_sepa_module_check_auto_payment');
function stripe_sepa_module_check_auto_payment()
{

    if (option_exists('paymentmethod_as_mollie_sepa_auto_payment') && get_option('paymentmethod_as_mollie_sepa_auto_payment') == 1 && get_option('paymentmethod_stripe_sepa_auto_payment') == 1) {
        update_option('paymentmethod_as_mollie_sepa_auto_payment', 0);
    }
}
/**
 * Stripe sepa admin data for Alerts
 * 
 * @var string $alertclass
 * @return void
 */
function stripe_sepa_admin_head_data()
{
    $CI = &get_instance();
    $alertclass = "";
	echo '<link href="' . module_dir_url('stripe_sepa', 'assets/style.css') . '?v=' . $CI->app_scripts->core_version() . '"  rel="stylesheet" type="text/css" />';
    if ($CI->session->flashdata('stripe-message-success')) {
        $alertclass = "success";
    } else if ($CI->session->flashdata('stripe-message-warning')) {
        $alertclass = "warning";
    } else if ($CI->session->flashdata('stripe-message-info')) {
        $alertclass = "info";
    } else if ($CI->session->flashdata('stripe-message-danger')) {
        $alertclass = "danger";
    }
    if ($CI->session->flashdata('stripe-message-' . $alertclass)) {
?>
        <div class="alert alert-<?php echo $alertclass; ?> alert-dismissible show" role="alert" style="margin-bottom: 0px;">
            <?php
            echo $CI->session->flashdata('stripe-message-' . $alertclass);
            ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
<?php }
}
/**
 * Add Stripe sepa menu item
 * @return void
 */
function stripe_sepa_module_init_menu_items()
{
    $CI = &get_instance();
    $CI->app_tabs->add_customer_profile_tab('stripe_sepa', [
        'name'     => _l('stripe_sepa'),
        'icon'     => 'fa fa-money-bill',
        'view'     => 'stripe_sepa/clients/groups/stripe_sepa',
        'visible'  => (has_permission('stripe_sepa', '', 'view') || has_permission('stripe_sepa', '', 'view_own')),
        'position' => 100,
        'badge'    => [],
    ]);
}
/**
 * Add Stripe sepa permissions
 * @return void
 */
function stripe_sepa_permissions()
{
    $capabilities = [];
    $capabilities['capabilities'] = [
        'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];
    register_staff_capabilities('stripe_sepa', $capabilities, _l('stripe_sepa'));
}
/*
 * Register  Stripe Sepa  language files
 */
register_language_files(STRIPE_SEPA_MODULE_NAME, [STRIPE_SEPA_MODULE_NAME]);
/**
 * Register activation module hook
 */
register_activation_hook(STRIPE_SEPA_MODULE_NAME, 'stripe_sepa_activation_hook');
/**
 * Stripe sepa activation hook
 */
function stripe_sepa_activation_hook()
{
    require_once(__DIR__ . '/install.php');
}
hooks()->add_action('app_init', STRIPE_SEPA_MODULE_NAME.'_actLib');
function stripe_sepa_actLib()
{
    $CI = &get_instance();
    $CI->load->library(STRIPE_SEPA_MODULE_NAME.'/Stripe_sepa_aeiou');
    $envato_res = $CI->stripe_sepa_aeiou->validatePurchase(STRIPE_SEPA_MODULE_NAME);
    if (!$envato_res) {
        set_alert('danger', 'One of your modules failed its verification and got deactivated. Please reactivate or contact support.');
    }
}

hooks()->add_action('pre_activate_module', STRIPE_SEPA_MODULE_NAME.'_sidecheck');
function stripe_sepa_sidecheck($module_name)
{
    if (STRIPE_SEPA_MODULE_NAME == $module_name['system_name']) {
        modules\stripe_sepa\core\Apiinit::activate($module_name);
    }
}

hooks()->add_action('pre_deactivate_module', STRIPE_SEPA_MODULE_NAME.'_deregister');
function stripe_sepa_deregister($module_name)
{
    if (STRIPE_SEPA_MODULE_NAME == $module_name['system_name']) {
        delete_option(STRIPE_SEPA_MODULE_NAME.'_verification_id');
        delete_option(STRIPE_SEPA_MODULE_NAME.'_last_verification');
        delete_option(STRIPE_SEPA_MODULE_NAME.'_product_token');
        delete_option(STRIPE_SEPA_MODULE_NAME.'_heartbeat');
    }
}