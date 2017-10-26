<?php
/*
  MarketPress SecureSubmit Gateway Plugin
  Author: Heartland Payment Systems
 */

class MP_Gateway_Securesubmit extends MP_Gateway_API
{

    //the current build version
    var $build = 2;
    //private gateway slug. Lowercase alpha (a-z) and dashes (-) only please!
    var $plugin_name = 'securesubmit';
    //name of your gateway, for the admin side.
    var $admin_name = '';
    //public name of your gateway, for lists and such.
    var $public_name = '';
    //url for an image for your checkout method. Displayed on checkout form if set
    var $method_img_url = '';
    //url for an submit button image for your checkout method. Displayed on checkout form if set
    var $method_button_img_url = '';
    //whether or not ssl is needed for checkout page
    var $force_ssl;
    //always contains the url to send payment notifications to if needed by your gateway. Populated by the parent class
    var $ipn_url;
    //whether if this is the only enabled gateway it can skip the payment_form step
    var $skip_form = false;
    //api vars
    var $public_key, $secret_key, $currency;

    /**
     * Refers to the gateways currencies
     *
     * @since 3.0
     * @access public
     * @var array
     */
    var $currencies = array(
        "USD" => 'USD - U.S. Dollar'
    );

    /**
     * Runs when your class is instantiated. Use to setup your plugin instead of __construct()
     */
    function on_creation()
    {
        $settings = get_option('mp_settings');

        //set names here to be able to translate
        $this->admin_name = __('SecureSubmit', 'mp');
        $this->public_name = __('Credit Card', 'mp');

        $this->method_img_url = mp_plugin_url('ui/images/credit_card.png');
        $this->method_button_img_url = mp_plugin_url('ui/images/cc-button.png');

        if (isset($settings['gateways']['securesubmit']['api_credentials']['public_key'])) {
            $this->public_key = $this->get_setting('api_credentials->public_key');
            $this->secret_key = $this->get_setting('api_credentials->secret_key');
        }
        $this->force_ssl = (!empty($settings['gateways']['securesubmit']['is_ssl']));
        $this->currency = isset($settings['gateways']['securesubmit']['currency']) ? $settings['gateways']['securesubmit']['currency'] : 'USD';

        add_action('wp_enqueue_scripts', array(&$this, 'enqueue_scripts'));
    }

    function enqueue_scripts()
    {
        $plugin_url_includes = mp_plugin_url('includes/common/payment-gateways/');

        if (mp_is_shop_page('checkout')) {
            //wp_enqueue_script('js-securesubmit', $plugin_url_includes . 'securesubmit-files/secure.submit-1.0.2.js', array('jquery'));
            wp_enqueue_script('js-securesubmit', 'https://api2.heartlandportico.com/SecureSubmit.v1/token/2.1/securesubmit.js', array('jquery'), null);
            wp_enqueue_script('securesubmit-token', mp_plugin_url('includes/common/payment-gateways/securesubmit-files/securesubmit.js'), array('js-securesubmit', 'jquery-ui-core'));
            wp_localize_script('securesubmit-token', 'securesubmit_token', array('public_key' => $this->public_key));
        }
    }

    /**
     * Updates the gateway settings
     *
     * @since 3.0
     * @access public
     * @param array $settings
     * @return array
     */
    function update($settings)
    {
        return $settings;
    }

    /**
     * Return fields you need to add to the top of the payment screen, like your credit card info fields
     *
     * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
     * @param array $shipping_info. Contains shipping info and email in case you need it
     */
    function payment_form($cart, $shipping_info)
    {
        global $mp, $current_user;
        $settings = get_option('mp_settings');
        $meta = get_user_meta($current_user->ID, 'mp_billing_info', true);

        $email = (!empty($_SESSION['mp_billing_info']['email'])) ? $_SESSION['mp_billing_info']['email'] : (!empty($meta['email']) ? $meta['email'] : $_SESSION['mp_shipping_info']['email']);
        $name = (!empty($_SESSION['mp_billing_info']['name'])) ? $_SESSION['mp_billing_info']['name'] : (!empty($meta['name']) ? $meta['name'] : $_SESSION['mp_shipping_info']['name']);
        $address1 = (!empty($_SESSION['mp_billing_info']['address1'])) ? $_SESSION['mp_billing_info']['address1'] : (!empty($meta['address1']) ? $meta['address1'] : $_SESSION['mp_shipping_info']['address1']);
        $address2 = (!empty($_SESSION['mp_billing_info']['address2'])) ? $_SESSION['mp_billing_info']['address2'] : (!empty($meta['address2']) ? $meta['address2'] : $_SESSION['mp_shipping_info']['address2']);
        $city = (!empty($_SESSION['mp_billing_info']['city'])) ? $_SESSION['mp_billing_info']['city'] : (!empty($meta['city']) ? $meta['city'] : $_SESSION['mp_shipping_info']['city']);
        $state = (!empty($_SESSION['mp_billing_info']['state'])) ? $_SESSION['mp_billing_info']['state'] : (!empty($meta['state']) ? $meta['state'] : $_SESSION['mp_shipping_info']['state']);
        $zip = (!empty($_SESSION['mp_billing_info']['zip'])) ? $_SESSION['mp_billing_info']['zip'] : (!empty($meta['zip']) ? $meta['zip'] : $_SESSION['mp_shipping_info']['zip']);
        $country = (!empty($_SESSION['mp_billing_info']['country'])) ? $_SESSION['mp_billing_info']['country'] : (!empty($meta['country']) ? $meta['country'] : $_SESSION['mp_shipping_info']['country']);

        if (!$country)
            $country = $settings['base_country'];

        $phone = (!empty($_SESSION['mp_billing_info']['phone'])) ? $_SESSION['mp_billing_info']['phone'] : (!empty($meta['phone']) ? $meta['phone'] : $_SESSION['mp_shipping_info']['phone']);

        $content = '';

        $content .= '<div id="securesubmit_checkout_errors"></div>';

        $content .= '<table class="mp_cart_billing">
			<thead>
				<tr>
					<th colspan="2">' . __('Enter Your Billing Information:', 'mp') . '</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td align="right">' . __('Email:', 'mp') . '*</td>
					<td>
						' . apply_filters('mp_checkout_error_email', '') . '
						<input data-rule-required="true" size="35" name="email" type="text" value="' . esc_attr($email) . '" />
					</td>
				</tr>
				<tr>
					<td align="right">' . __('Full Name:', 'mp') . '*</td>
					<td>
						<input data-rule-required="true" size="35" name="name" type="text" value="' . esc_attr($name) . '" />
					</td>
				</tr>
				<tr>
					<td align="right">' . __('Address:', 'mp') . '*</td>
					<td>
						<input data-rule-required="true" size="45" name="address1" type="text" value="' . esc_attr($address1) . '" /><br />
						<small><em>' . __('Street address, P.O. box, company name, c/o', 'mp') . '</em></small>
					</td>
				</tr>
				<tr>
					<td align="right">' . __('Address 2:', 'mp') . '*</td>
					<td>
						<input data-rule-required="true" size="45" name="address2" type="text" value="' . esc_attr($address2) . '" /><br />
						<small><em>' . __('Apartment, suite, unit, building, floor, etc.', 'mp') . '</em></small>
					</td>
				</tr>
				<tr>
					<td align="right">' . __('City:', 'mp') . '*</td>
					<td>
						<input data-rule-required="true" size="25" name="city" type="text" value="' . esc_attr($city) . '" />
					</td>
				</tr>
				<tr>
					<td align="right">' . __('State:', 'mp') . '*</td>
					<td>
						' . apply_filters('mp_checkout_error_state', '') . '
						<input data-rule-required="true" size="15" name="state" type="text" value="' . esc_attr($state) . '" />
					</td>
				</tr>
				<tr>
					<td align="right">' . __('Zip:', 'mp') . '*</td>
					<td>
						' . apply_filters('mp_checkout_error_zip', '') . '
						<input data-rule-required="true" size="15" name="zip" type="text" value="' . esc_attr($zip) . '" />
					</td>
				</tr>
				<tr>
					<td align="right">' . __('Country:', 'mp') . '*</td>
					<td>
						' . apply_filters('mp_checkout_error_country', '') . '
						<select id="mp_" name="country">';

        $allowed_countries = explode(',', $settings['shipping']['allowed_countries']);
        if (sizeof($allowed_countries) == 1 && $allowed_countries[0] == 'all_countries') {
            $allowed_countries = $mp->countries;
        }
        foreach ($allowed_countries as $code => $countryName) {
            $content .= '<option value="' . $code . '"' . selected($country, $code, false) . '>' . esc_attr($countryName) . '</option>';
        }

        $content .= '</select>
					</td>
				</tr>
				<tr>
					<td align="right">' . __('Phone:', 'mp') . '*</td>
					<td>
						<input data-rule-required="true" size="20" name="phone" type="text" value="' . esc_attr($phone) . '" />
					</td>
				</tr>
			</tbody>
		</table>';

        $content .= '<table class="mp_cart_billing">
        <thead><tr>
          <th colspan="2">' . __('Enter Your Credit Card Information:', 'mp') . '</th>
        </tr></thead>
        <tbody>';
        $content .= '<tr>';
        $content .= '<td>';
        $content .= __('Card Number', 'mp');
        $content .= '</td>';
        $content .= '<td>';
        $content .= '<input id="cc_number" type="text" pattern="\d*" autocomplete="cc-number" 
class="mp_form_input mp_form_input-cc-num mp-input-cc-num" data-rule-required="true" 
data-rule-cc-num="true" placeholder="•••• •••• •••• ••••" size="30">';
        $content .= '</td>';
        $content .= '</tr>';
        $content .= '<tr>';
        $content .= '<td>';
        $content .= __('Expiration:', 'mp');
        $content .= '</td>';
        $content .= '<td>';
        $content .= '<select data-rule-required="true" id="cc_month">';
        $content .= $this->_print_month_dropdown();
        $content .= '</select>';
        $content .= '<span> / </span>';
        $content .= '<select data-rule-required="true" id="cc_year">';
        $content .= $this->_print_year_dropdown('', true);
        $content .= '</select>';
        $content .= '</td>';
        $content .= '</tr>';
        $content .= '<tr>';
        $content .= '<td>';
        $content .= __('CVC:', 'mp');
        $content .= '</td>';
        $content .= '<td>';
        $content .= '<input data-rule-required="true" type="text" size="4" autocomplete="off" id="cc_cvv2" />';
        $content .= '</td>';
        $content .= '</tr>';
        $content .= '</table>';
        $content .= '<span id="securesubmit_processing" style="display: none;float: right;"><img src="' . mp_plugin_url('ui/images/loading.gif') . '" /> ' . __('Processing...', 'psts') . '</span>';
        
        return $content;
    }

    /**
     * Runs before page load incase you need to run any scripts before loading the success message page
     */
    function order_confirmation($order)
    {
        
    }

    /**
     * Print the years
     */
    function _print_year_dropdown($sel = '', $pfp = false)
    {
        $localDate = getdate();
        $minYear = $localDate["year"];
        $maxYear = $minYear + 15;

        $output = "<option value=''>--</option>";
        for ($i = $minYear; $i < $maxYear; $i++) {
            if ($pfp) {
                $output .= "<option value='" . substr($i, 0, 4) . "'" . ($sel == (substr($i, 0, 4)) ? ' selected' : '') .
                    ">" . $i . "</option>";
            } else {
                $output .= "<option value='" . substr($i, 2, 2) . "'" . ($sel == (substr($i, 2, 2)) ? ' selected' : '') .
                    ">" . $i . "</option>";
            }
        }
        return($output);
    }

    /**
     * Print the months
     */
    function _print_month_dropdown($sel = '')
    {
        $output = "<option value=''>--</option>";
        $output .= "<option " . ($sel == 1 ? ' selected' : '') . " value='01'>01 - Jan</option>";
        $output .= "<option " . ($sel == 2 ? ' selected' : '') . "  value='02'>02 - Feb</option>";
        $output .= "<option " . ($sel == 3 ? ' selected' : '') . "  value='03'>03 - Mar</option>";
        $output .= "<option " . ($sel == 4 ? ' selected' : '') . "  value='04'>04 - Apr</option>";
        $output .= "<option " . ($sel == 5 ? ' selected' : '') . "  value='05'>05 - May</option>";
        $output .= "<option " . ($sel == 6 ? ' selected' : '') . "  value='06'>06 - Jun</option>";
        $output .= "<option " . ($sel == 7 ? ' selected' : '') . "  value='07'>07 - Jul</option>";
        $output .= "<option " . ($sel == 8 ? ' selected' : '') . "  value='08'>08 - Aug</option>";
        $output .= "<option " . ($sel == 9 ? ' selected' : '') . "  value='09'>09 - Sep</option>";
        $output .= "<option " . ($sel == 10 ? ' selected' : '') . "  value='10'>10 - Oct</option>";
        $output .= "<option " . ($sel == 11 ? ' selected' : '') . "  value='11'>11 - Nov</option>";
        $output .= "<option " . ($sel == 12 ? ' selected' : '') . "  value='12'>12 - Dec</option>";

        return($output);
    }

    /**
     * Filters the order confirmation email message body. You may want to append something to
     *  the message. Optional
     *
     * Don't forget to return!
     */
    function order_confirmation_email($msg, $order = null)
    {
        return $msg;
    }

    /**
     * Return any html you want to show on the confirmation screen after checkout. This
     *  should be a payment details box and message.
     *
     * Don't forget to return!
     */
    function order_confirmation_msg($content, $order)
    {
        global $mp;
        if ($order->post_status == 'order_paid')
            $content .= '<p>' . sprintf(__('Your payment for this order totalling %s is complete.', 'mp'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total'])) . '</p>';
        return $content;
    }

    /**
     * Init settings metaboxes
     *
     * @since 3.0
     * @access public
     */
    function init_settings_metabox()
    {
        $metabox = new WPMUDEV_Metabox(array(
            'id' => $this->generate_metabox_id(),
            'page_slugs' => array('store-settings-payments', 'store-settings_page_store-settings-payments'),
            'title' => sprintf(__('%s Settings', 'mp'), $this->admin_name),
            'option_name' => 'mp_settings',
            'desc' => __('Accept Visa, MasterCard, American Express, Discover and JCB cards directly on your site. All credit card data is tokenized and therefore not submitted to your server--this removes your site from PCI Scope.<a href="https://developer.heartlandpaymentsystems.com/SecureSubmit/" target="_blank">More Info &raquo</a>', 'mp'),
            'conditional' => array(
                'name' => 'gateways[allowed][' . $this->plugin_name . ']',
                'value' => 1,
                'action' => 'show',
            ),
        ));

        $metabox->add_field('checkbox', array(
            'name' => $this->get_field_name('is_ssl'),
            'label' => array('text' => __('SecureSubmit Force SSL?', 'mp')),
            'desc' => __('When in live mode we recommends you have an SSL certificate setup for the site where the checkout form will be displayed.', 'mp'),
        ));

        $creds = $metabox->add_field('complex', array(
            'name' => 'gateways[' . $this->plugin_name . '][api_credentials]',
            'label' => array('text' => __('API Credentials', 'mp')),
            'desc' => __('<a target="_blank" href="https://developer.heartlandpaymentsystems.com/SecureSubmit/Account">Get your SecureSubmit credentials &raquo;</a>', 'mp'),
        ));

        if ($creds instanceof WPMUDEV_Field) {
            $creds->add_field('text', array(
                'name' => 'secret_key',
                'label' => array('text' => __('Secret key', 'mp')),
                'validation' => array(
                    'required' => true,
                ),
            ));
            $creds->add_field('text', array(
                'name' => 'public_key',
                'label' => array('text' => __('Public key', 'mp')),
                'validation' => array(
                    'required' => true,
                ),
            ));
        }

        $metabox->add_field('advanced_select', array(
            'name' => 'gateways[' . $this->plugin_name . '][currency]',
            'label' => array('text' => __('Currency', 'mp')),
            'multiple' => false,
            'options' => array_merge(array('' => __('Select One', 'mp')), $this->currencies),
            'width' => 'element',
            'validation' => array(
                'required' => true,
            ),
        ));
    }

    /**
     * Use this to do the final payment. Create the order then process the payment. If
     * you know the payment is successful right away go ahead and change the order status
     * as well.
     *
     * @param MP_Cart $cart. Contains the MP_Cart object.
     * @param array $billing_info. Contains billing info and email in case you need it.
     * @param array $shipping_info. Contains shipping info and email in case you need it
     */
    function process_payment($cart, $billing_info, $shipping_info)
    { 
        global $mp;
        $settings = get_option('mp_settings');

        //save to session
        //if (!$mp->checkout_error) {
        $token = mp_get_post_value('securesubmitToken'); 

        //make sure token is set at this point
        if (empty($token)) {
            mp_checkout()->add_error(__('There was a problem with your SecureSubmit token. Please try again.', 'mp'));
            return false;
        }

        require_once mp_plugin_dir("library/securesubmit-files/SecureSubmit/Hps.php");

        $config = new HpsServicesConfig();

        $config->secretApiKey = $this->secret_key;
        $config->versionNumber = '1518';
        $config->developerId = '002914';

        $chargeService = new HpsCreditService($config);

        $hpsaddress = new HpsAddress();
        $hpsaddress->address = $billing_info['address1'];
        $hpsaddress->city = $billing_info['city'];
        $hpsaddress->state = $billing_info['state'];
        $hpsaddress->zip = $billing_info['zip'];
        $hpsaddress->country = $billing_info['country'];

        $_names = explode(" ", $billing_info['name']);

        if (isset($_names[0])) {
            $first_name = array_shift($_names);
        } else {
            $first_name = "";
        }

        if (isset($_names[0])) {
            $last_name = implode(" ", $_names);
        } else {
            $last_name = "";
        }

        $cardHolder = new HpsCardHolder();
        $cardHolder->firstName = $first_name;
        $cardHolder->lastName = $last_name;
        $cardHolder->phone = preg_replace('/[^0-9]/', '', $billing_info['phone']);
        $cardHolder->email = $billing_info['email'];
        $cardHolder->address = $hpsaddress;

        $hpstoken = new HpsTokenData();
        $hpstoken->tokenValue = $token;

        $total = $cart->total(false);

        //shipping line
        if (($shipping_price = $mp->shipping_price(false)) !== false) {
            $total = $total + $shipping_price;
        }

        //tax line
        if (($tax_price = $mp->tax_price(false)) !== false) {
            $total = $total + $tax_price;
        }

        $order = new MP_Order();
        $order_id = $order->get_id();

        try {
            $response = $chargeService->charge(
                $total, 'usd', $hpstoken, $cardHolder, false, null);

            if (!empty($response->transactionId)) {
                $payment_info = array();
                $payment_info['gateway_public_name'] = $this->public_name;
                $payment_info['gateway_private_name'] = $this->admin_name;
                $payment_info['method'] = __('Tokenized Payment', 'mp');
                $payment_info['transaction_id'] = $response->transactionId;
                $timestamp = time();
                $payment_info['status'][$timestamp] = __('Paid', 'mp');
                $payment_info['total'] = $total;
                $payment_info['currency'] = $this->currency;

                $order->save(array(
                    'cart' => $cart,
                    'payment_info' => $payment_info,
                    'billing_info' => $billing_info,
                    'shipping_info' => $shipping_info
                ));

                //In order to each the mp_order_order_paid action
                $order->change_status('order_paid', true);
                wp_redirect($order->tracking_url(false));
            }
        } catch (Exception $e) {
            mp_checkout()->add_error(sprintf(__('There was an error processing your card: "%s". Please <a href="%s">go back and try again</a>.', 'mp'), $e->getMessage(), mp_checkout_step_url('checkout')));
            return false;
        }
    }

    /**
     * INS and payment return
     */
    function process_ipn_return()
    {
        global $mp;
        $settings = get_option('mp_settings');
    }
}

//register payment gateway plugin
mp_register_gateway_plugin('MP_Gateway_Securesubmit', 'securesubmit', __('Secure Submit', 'mp'));

?>