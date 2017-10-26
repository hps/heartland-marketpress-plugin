<?php
/*
  MarketPress SecureSubmit Gateway Plugin
  Author: Heartland Payment Systems
 */

class MP_Gateway_Securesubmit extends MP_Gateway_API {

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
     * Runs when your class is instantiated. Use to setup your plugin instead of __construct()
     */
    function on_creation() {
        global $mp;
        $settings = get_option('mp_settings');

        //set names here to be able to translate
        $this->admin_name = __('SecureSubmit', 'mp');
        $this->public_name = __('Credit Card', 'mp');

        $this->method_img_url = $mp->plugin_url . 'images/credit_card.png';
        $this->method_button_img_url = $mp->plugin_url . 'images/cc-button.png';

        if (isset($settings['gateways']['securesubmit']['public_key'])) {
            $this->public_key = $settings['gateways']['securesubmit']['public_key'];
            $this->secret_key = $settings['gateways']['securesubmit']['secret_key'];
        }
        $this->force_ssl = (bool) ( isset($settings['gateways']['securesubmit']['is_ssl']) && $settings['gateways']['securesubmit']['is_ssl'] );
        $this->currency = isset($settings['gateways']['securesubmit']['currency']) ? $settings['gateways']['securesubmit']['currency'] : 'USD';

        add_action('wp_enqueue_scripts', array(&$this, 'enqueue_scripts'));
    }

    function enqueue_scripts() {
        global $mp;

        if (get_query_var('pagename') == 'cart' && get_query_var('checkoutstep') == 'checkout') {

            wp_enqueue_script('js-securesubmit', $mp->plugin_url . 'plugins-gateway/securesubmit-files/secure.submit-1.0.2.js', array('jquery'));
            wp_enqueue_script('securesubmit-token', $mp->plugin_url . 'plugins-gateway/securesubmit-files/securesubmit.js', array('js-securesubmit', 'jquery'));
            wp_localize_script('securesubmit-token', 'securesubmit_token', array('public_key' => $this->public_key));
        }
    }

    /**
     * Return fields you need to add to the top of the payment screen, like your credit card info fields
     *
     * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
     * @param array $shipping_info. Contains shipping info and email in case you need it
     */
    function payment_form($cart, $shipping_info) {
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
						<input size="35" name="email" type="text" value="' . esc_attr($email) . '" />
					</td>
				</tr>
				<tr>
					<td align="right">' . __('Full Name:', 'mp') . '*</td>
					<td>
						<input size="35" name="name" type="text" value="' . esc_attr($name) . '" />
					</td>
				</tr>
				<tr>
					<td align="right">' . __('Address:', 'mp') . '*</td>
					<td>
						<input size="45" name="address1" type="text" value="' . esc_attr($address1) . '" /><br />
						<small><em>' . __('Street address, P.O. box, company name, c/o', 'mp') . '</em></small>
					</td>
				</tr>
				<tr>
					<td align="right">' . __('Address 2:', 'mp') . '*</td>
					<td>
						<input size="45" name="address2" type="text" value="' . esc_attr($address2) . '" /><br />
						<small><em>' . __('Apartment, suite, unit, building, floor, etc.', 'mp') . '</em></small>
					</td>
				</tr>
				<tr>
					<td align="right">' . __('City:', 'mp') . '*</td>
					<td>
						<input size="25" name="city" type="text" value="' . esc_attr($city) . '" />
					</td>
				</tr>
				<tr>
					<td align="right">' . __('State:', 'mp') . '*</td>
					<td>
						' . apply_filters('mp_checkout_error_state', '') . '
						<input size="15" name="state" type="text" value="' . esc_attr($state) . '" />
					</td>
				</tr>
				<tr>
					<td align="right">' . __('Zip:', 'mp') . '*</td>
					<td>
						' . apply_filters('mp_checkout_error_zip', '') . '
						<input size="15" name="zip" type="text" value="' . esc_attr($zip) . '" />
					</td>
				</tr>
				<tr>
					<td align="right">' . __('Country:', 'mp') . '*</td>
					<td>
						' . apply_filters('mp_checkout_error_country', '') . '
						<select id="mp_" name="country">';

        foreach ((array) $settings['shipping']['allowed_countries'] as $code) {
            $content .= '<option value="' . $code . '"' . selected($country, $code, false) . '>' . esc_attr($mp->countries[$code]) . '</option>';
        }

        $content .= '</select>
					</td>
				</tr>
				<tr>
					<td align="right">' . __('Phone:', 'mp') . '*</td>
					<td>
						<input size="20" name="phone" type="text" value="' . esc_attr($phone) . '" />
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
        $content .= '<input type="text" size="30" autocomplete="off" id="cc_number"/>';
        $content .= '</td>';
        $content .= '</tr>';
        $content .= '<tr>';
        $content .= '<td>';
        $content .= __('Expiration:', 'mp');
        $content .= '</td>';
        $content .= '<td>';
        $content .= '<select id="cc_month">';
        $content .= $this->_print_month_dropdown();
        $content .= '</select>';
        $content .= '<span> / </span>';
        $content .= '<select id="cc_year">';
        $content .= $this->_print_year_dropdown('', true);
        $content .= '</select>';
        $content .= '</td>';
        $content .= '</tr>';
        $content .= '<tr>';
        $content .= '<td>';
        $content .= __('CVC:', 'mp');
        $content .= '</td>';
        $content .= '<td>';
        $content .= '<input type="text" size="4" autocomplete="off" id="cc_cvv2" />';
        $content .= '</td>';
        $content .= '</tr>';
        $content .= '</table>';
        $content .= '<span id="securesubmit_processing" style="display: none;float: right;"><img src="' . $mp->plugin_url . 'images/loading.gif" /> ' . __('Processing...', 'psts') . '</span>';
        return $content;
    }

    /**
     * Return the chosen payment details here for final confirmation. You probably don't need
     *  to post anything in the form as it should be in your $_SESSION var already.
     *
     * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
     * @param array $shipping_info. Contains shipping info and email in case you need it
     */
    function confirm_payment_form($cart, $shipping_info) {
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

        if (!isset($_SESSION['securesubmitToken'])) {
            $mp->cart_checkout_error(__('Your SecureSubmit Token has Expired. Please run this transaction again.', 'mp'));
            return false;
        }

        $content = '';

        $content .= '<table class="mp_cart_billing">';
        $content .= '<thead><tr>';
        $content .= '<th>' . __('Billing Information:', 'mp') . '</th>';
        $content .= '<th align="right"><a href="' . mp_checkout_step_url('checkout') . '">' . __('&laquo; Edit', 'mp') . '</a></th>';
        $content .= '</tr></thead>';
        $content .= '<tbody>';
        $content .= '<tr>';
        $content .= '<td align="right">' . __('Email:', 'mp') . '</td><td>';
        $content .= esc_attr($email) . '</td>';
        $content .= '</tr>';
        $content .= '<tr>';
        $content .= '<td align="right">' . __('Full Name:', 'mp') . '</td><td>';
        $content .= esc_attr($name) . '</td>';
        $content .= '</tr>';
        $content .= '<tr>';
        $content .= '<td align="right">' . __('Address:', 'mp') . '</td>';
        $content .= '<td>' . esc_attr($address1) . '</td>';
        $content .= '</tr>';

        if ($address2) {
            $content .= '<tr>';
            $content .= '<td align="right">' . __('Address 2:', 'mp') . '</td>';
            $content .= '<td>' . esc_attr($address2) . '</td>';
            $content .= '</tr>';
        }

        $content .= '<tr>';
        $content .= '<td align="right">' . __('City:', 'mp') . '</td>';
        $content .= '<td>' . esc_attr($city) . '</td>';
        $content .= '</tr>';

        if ($state) {
            $content .= '<tr>';
            $content .= '<td align="right">' . __('State:', 'mp') . '</td>';
            $content .= '<td>' . esc_attr($state) . '</td>';
            $content .= '</tr>';
        }

        $content .= '<tr>';
        $content .= '<td align="right">' . __('Zip Code:', 'mp') . '</td>';
        $content .= '<td>' . esc_attr($zip) . '</td>';
        $content .= '</tr>';
        $content .= '<tr>';
        $content .= '<td align="right">' . __('Country:', 'mp') . '</td>';
        $content .= '<td>' . $mp->countries[$country] . '</td>';
        $content .= '</tr>';

        if ($phone) {
            $content .= '<tr>';
            $content .= '<td align="right">' . __('Phone Number:', 'mp') . '</td>';
            $content .= '<td>' . esc_attr($phone) . '</td>';
            $content .= '</tr>';
        }

        $content .= '</tbody>';
        $content .= '</table>';
        return $content;
    }

    /**
     * Runs before page load incase you need to run any scripts before loading the success message page
     */
    function order_confirmation($order) {
        
    }

    /**
     * Print the years
     */
    function _print_year_dropdown($sel = '', $pfp = false) {
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
    function _print_month_dropdown($sel = '') {
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
     * Use this to process any fields you added. Use the $_POST global,
     * and be sure to save it to both the $_SESSION and usermeta if logged in.
     * DO NOT save credit card details to usermeta as it's not PCI compliant.
     * Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
     * it will redirect to the next step.
     *
     * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
     * @param array $shipping_info. Contains shipping info and email in case you need it
     */
    function process_payment_form($cart, $shipping_info) {
        global $mp;
        $settings = get_option('mp_settings');

        if (!isset($_POST['securesubmitToken']))
            $mp->cart_checkout_error(__('There was an error with your SecureSubmit Token. Please try again.', 'mp'));

        if (!is_email($_POST['email']))
            $mp->cart_checkout_error('Please enter a valid Email Address.', 'email');

        if (empty($_POST['address1']))
            $mp->cart_checkout_error('Please enter your Street Address.', 'address1');

        if (empty($_POST['city']))
            $mp->cart_checkout_error('Please enter your City.', 'city');

        if (($_POST['country'] == 'US' || $_POST['country'] == 'CA') && empty($_POST['state']))
            $mp->cart_checkout_error('Please enter your State.', 'state');

        if (empty($_POST['zip']))
            $mp->cart_checkout_error('Please enter your Zip/Postal Code.', 'zip');

        if (empty($_POST['country']) || strlen($_POST['country']) != 2)
            $mp->cart_checkout_error('Please enter your Country.', 'country');

        //save to session
        if (!$mp->checkout_error) {
            $_SESSION['securesubmitToken'] = $_POST['securesubmitToken'];

            global $current_user;
            $meta = get_user_meta($current_user->ID, 'mp_billing_info', true);
            $_SESSION['mp_billing_info']['email'] = ($_POST['email']) ? trim(stripslashes($_POST['email'])) : $current_user->user_email;
            $_SESSION['mp_billing_info']['name'] = ($_POST['name']) ? trim(stripslashes($_POST['name'])) : $current_user->user_firstname;
            $_SESSION['mp_billing_info']['address1'] = ($_POST['address1']) ? trim(stripslashes($_POST['address1'])) : $meta['address1'];
            $_SESSION['mp_billing_info']['address2'] = ($_POST['address2']) ? trim(stripslashes($_POST['address2'])) : $meta['address2'];
            $_SESSION['mp_billing_info']['city'] = ($_POST['city']) ? trim(stripslashes($_POST['city'])) : $meta['city'];
            $_SESSION['mp_billing_info']['state'] = ($_POST['state']) ? trim(stripslashes($_POST['state'])) : $meta['state'];
            $_SESSION['mp_billing_info']['zip'] = ($_POST['zip']) ? trim(stripslashes($_POST['zip'])) : $meta['zip'];
            $_SESSION['mp_billing_info']['country'] = ($_POST['country']) ? trim($_POST['country']) : $meta['country'];
            $_SESSION['mp_billing_info']['phone'] = ($_POST['phone']) ? preg_replace('/[^0-9-\(\) ]/', '', trim($_POST['phone'])) : $meta['phone'];
        }
    }

    /**
     * Filters the order confirmation email message body. You may want to append something to
     *  the message. Optional
     *
     * Don't forget to return!
     */
    function order_confirmation_email($msg, $order = null) {
        return $msg;
    }

    /**
     * Return any html you want to show on the confirmation screen after checkout. This
     *  should be a payment details box and message.
     *
     * Don't forget to return!
     */
    function order_confirmation_msg($content, $order) {
        global $mp;
        if ($order->post_status == 'order_paid')
            $content .= '<p>' . sprintf(__('Your payment for this order totalling %s is complete.', 'mp'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total'])) . '</p>';
        return $content;
    }

    /**
     * Echo a settings meta box with whatever settings you need for you gateway.
     *  Form field names should be prefixed with mp[gateways][plugin_name], like "mp[gateways][plugin_name][mysetting]".
     *  You can access saved settings via $settings array.
     */
    function gateway_settings_box($settings) {
        global $mp;
        ?>
        <div class="postbox">
            <h3 class='hndle'><span><?php _e('SecureSubmit', 'mp') ?></span> - <span class="description"><?php _e('SecureSubmit Payment Gateway.', 'mp'); ?></span></h3>
            <div class="inside">
                <p class="description"><?php _e("Accept Visa, MasterCard, American Express, Discover and JCB cards directly on your site. All credit card data is tokenized and therefore not submitted to your server--this removes your site from PCI Scope.", 'mp'); ?> <a href="https://developer.heartlandpaymentsystems.com/SecureSubmit/" target="_blank"><?php _e('More Info &raquo;', 'mp') ?></a></p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e('SecureSubmit Force SSL', 'mp') ?></th>
                        <td>
                            <span class="description"><?php _e('Your payments website should be behind SSL unless you are testing.', 'mp'); ?></span><br/>
                            <select name="mp[gateways][securesubmit][is_ssl]">
                                <option value="1"<?php selected($settings['gateways']['securesubmit']['is_ssl'], 1); ?>><?php _e('Force SSL (Live Site)', 'mp') ?></option>
                                <option value="0"<?php selected($settings['gateways']['securesubmit']['is_ssl'], 0); ?>><?php _e('No SSL (Testing)', 'mp') ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('SecureSubmit Credentials', 'mp') ?></th>
                        <td>
                            <span class="description"><?php _e('<a target="_blank" href="https://developer.heartlandpaymentsystems.com/SecureSubmit/Account">Get your SecureSubmit credentials</a>.', 'mp') ?></span>
                            <p><label><?php _e('Secret key', 'mp') ?><br />
                                    <input value="<?php echo esc_attr($settings['gateways']['securesubmit']['secret_key']); ?>" size="70" name="mp[gateways][securesubmit][secret_key]" type="text" />
                                </label></p>
                            <p><label><?php _e('Public key', 'mp') ?><br />
                                    <input value="<?php echo esc_attr($settings['gateways']['securesubmit']['public_key']); ?>" size="70" name="mp[gateways][securesubmit][public_key]" type="text" />
                                </label></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e('Currency', 'mp') ?></th>
                        <td>
                            <span class="description"><?php _e('Selecting a currency other than that used for your store may cause problems at checkout.', 'mp'); ?></span><br />
                            <select name="mp[gateways][securesubmit][currency]">
        <?php
        $sel_currency = isset($settings['gateways']['securesubmit']['currency']) ? $settings['gateways']['securesubmit']['currency'] : $settings['currency'];
        $currencies = array(
            "USD" => 'USD - U.S. Dollar'
        );

        foreach ($currencies as $k => $v) {
            echo '		<option value="' . $k . '"' . ($k == $sel_currency ? ' selected' : '') . '>' . wp_specialchars($v, true) . '</option>' . "\n";
        }
        ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Filters posted data from your settings form. Do anything you need to the $settings['gateways']['plugin_name']
     *  array. Don't forget to return!
     */
    function process_gateway_settings($settings) {
        return $settings;
    }

    /**
     * Use this to do the final payment. Create the order then process the payment. If
     *  you know the payment is successful right away go ahead and change the order status
     *  as well.
     *  Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
     *  it will redirect to the next step.
     *
     * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
     * @param array $shipping_info. Contains shipping info and email in case you need it
     */
    function process_payment($cart, $shipping_info) {
        global $mp;
        $settings = get_option('mp_settings');
        $billing_info = $_SESSION['mp_billing_info'];

        //make sure token is set at this point
        if (!isset($_SESSION['securesubmitToken'])) {
            $mp->cart_checkout_error(__('There was a problem with your SecureSubmit token. Please try again.', 'mp'));
            return false;
        }
		$plugin_dir = plugin_dir_path($mp->plugin_file);
        require_once $plugin_dir . "library/securesubmit-files/SecureSubmit/Hps.php";

        $config = new HpsServicesConfig();

        $config->secretApiKey = $this->secret_key;
        $config->versionNumber = '1518';
        $config->developerId = '002914';

        $chargeService = new HpsCreditService($config);

        $hpsaddress = new HpsAddress();
        $hpsaddress->address = $order->$billing_info['address1'];
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
        $hpstoken->tokenValue = $_SESSION['securesubmitToken'];

        $totals = array();

        foreach ($cart as $product_id => $variations) {
            foreach ($variations as $variation => $data) {
                $totals[] = $mp->before_tax_price($data['price'], $product_id) * $data['quantity'];
            }
        }

        $total = array_sum($totals);

        //coupon line
        if ($coupon = $mp->coupon_value($mp->get_coupon_code(), $total)) {
            $total = $coupon['new_total'];
        }

        //shipping line
        if ($shipping_price = $mp->shipping_price()) {
            $total += $shipping_price;
        }

        //tax line
        if ($tax_price = $mp->tax_price()) {
            $total += $tax_price;
        }

        $order_id = $mp->generate_order_id();

        try {
            $response = $chargeService->charge(
                    $total, 'usd', $hpstoken, $cardHolder, false, null);

            $payment_info = array();
            $payment_info['gateway_public_name'] = $this->public_name;
            $payment_info['gateway_private_name'] = $this->admin_name;
            $payment_info['method'] = __('Tokenized Payment', 'mp');
            $payment_info['transaction_id'] = $response->transactionId;
            $timestamp = time();
            $payment_info['status'][$timestamp] = __('Paid', 'mp');
            $payment_info['total'] = $total;
            $payment_info['currency'] = $this->currency;

            $order = $mp->create_order($order_id, $cart, $_SESSION['mp_shipping_info'], $payment_info, true);
            unset($_SESSION['securesubmitToken']);
            $mp->set_cart_cookie(Array());
        } catch (Exception $e) {
            unset($_SESSION['securesubmitToken']);
            $mp->cart_checkout_error(sprintf(__('There was an error processing your card: "%s". Please <a href="%s">go back and try again</a>.', 'mp'), $e->getMessage(), mp_checkout_step_url('checkout')));
            return false;
        }
    }

    /**
     * INS and payment return
     */
    function process_ipn_return() {
        global $mp;
        $settings = get_option('mp_settings');
    }

}

//register payment gateway plugin
mp_register_gateway_plugin('MP_Gateway_Securesubmit', 'securesubmit', __('Secure Submit', 'mp'));
?>