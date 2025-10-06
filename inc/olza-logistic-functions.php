<?php

/**
 * Adding Additional Map Container checkout
 */

function change_woocommerce_field_markup($field, $key, $args, $value)
{
    $field = str_replace('form-row', '', $field);

    // Add pickup hidden fields with unique IDs
    if ($key === 'billing_country') {
        // Change the IDs here to be unique for each context
        $field .= '<input type="hidden" name="olza_pickup_option" id="olza_pickup_option" value="" />';
        $field .= '<input type="hidden" name="olza_pickup_option_wedobox" id="olza_pickup_option_wedobox" value="" />';
        $field .= '<input type="hidden" name="delivery_point_id" id="delivery_point_id" value="" />';
        $field .= '<input type="hidden" name="delivery_courier_id" id="delivery_courier_id" value="" />';
    }
    return $field;
}


add_filter("woocommerce_form_field", "change_woocommerce_field_markup", 10, 4);


/**
 * Validate pickup field
 */

add_action('woocommerce_checkout_process', 'olza_pickup_field_validation');

function olza_pickup_field_validation()

{
    $chosen_methods = olza_get_chosen_shipping_methods();

    if (olza_is_pickup_shipping_selected($chosen_methods)) {
        if (empty($_POST['olza_pickup_option'])) {
            wc_add_notice(__('Please select a pickup point.', 'olza-logistic-woo'), 'error');
        }
    }
}

/**
 * Update Pickup Points
 */


add_action('woocommerce_checkout_update_order_meta', 'olza_logistic_update_pickup_point', 10, 2);

function olza_logistic_update_pickup_point($order_id, $data)
{
    $chosen_methods = olza_get_chosen_shipping_methods();

    if (olza_is_pickup_shipping_selected($chosen_methods)) {
        // Check the pickup type from POST data
        $pickup_type = isset($_POST['pickup_type']) ? sanitize_text_field($_POST['pickup_type']) : '';

        // Save the pickup option based on the selected pickup type
        if (!empty($_POST['olza_pickup_option'])) {
            if ($pickup_type === 'ppl-ps') {
                update_post_meta($order_id, 'Pickup Point (PPL-PS)', sanitize_text_field($_POST['olza_pickup_option']));
            } elseif ($pickup_type === 'wedo-box') {
                update_post_meta($order_id, 'Pickup Point (WEDO-BOX)', sanitize_text_field($_POST['olza_pickup_option']));
            }
        }

        // Save additional data for delivery point and courier ID
        if (!empty($_POST['delivery_point_id'])) {
            update_post_meta($order_id, 'delivery_point_id', sanitize_text_field($_POST['delivery_point_id']));
        }
        if (!empty($_POST['delivery_courier_id'])) {
            update_post_meta($order_id, 'delivery_courier_id', sanitize_text_field($_POST['delivery_courier_id']));
        }
    }
}
add_action('woocommerce_checkout_create_order', 'olza_update_pickup_order_meta');
function olza_update_pickup_order_meta($order) {
    $chosen_methods = olza_get_chosen_shipping_methods();

    // Check if the chosen shipping method is either olza_pickup or olza_pickup_wedobox
    if (!empty($chosen_methods)) {
        $first_method = reset($chosen_methods);
    } else {
        $first_method = '';
    }

    if (is_string($first_method) && (strpos($first_method, 'olza_pickup') !== false || strpos($first_method, 'olza_pickup_wedobox') !== false)) {

        // Add meta data to order if available
        if (isset($_POST['olza_pickup_option']) && !empty($_POST['olza_pickup_option'])) {
            $order->update_meta_data('Pickup Point', sanitize_text_field($_POST['olza_pickup_option']));
        }
        if (isset($_POST['delivery_point_id']) && !empty($_POST['delivery_point_id'])) {
            $order->update_meta_data('Delivery Point ID', sanitize_text_field($_POST['delivery_point_id']));
        }
        if (isset($_POST['delivery_courier_id']) && !empty($_POST['delivery_courier_id'])) {
            $order->update_meta_data('Delivery Courier', sanitize_text_field($_POST['delivery_courier_id']));
        }

        // Dynamically set shipping method title with country and courier ID
        $pickup_address = isset($_POST['olza_pickup_option']) ? sanitize_text_field($_POST['olza_pickup_option']) : '';
        $delivery_courier_id = isset($_POST['delivery_courier_id']) ? sanitize_text_field($_POST['delivery_courier_id']) : '';
        $country_name = isset($_POST['country_name']) && !empty($_POST['country_name']) ? sanitize_text_field($_POST['country_name']) : 'CZ';

        // Set new shipping title
        $new_shipping_title = $country_name . '-' . $delivery_courier_id;

        // Update the shipping method title in the order
        foreach ($order->get_items('shipping') as $item_id => $shipping_item) {
            if (strpos($shipping_item->get_method_id(), 'olza_pickup') !== false) {
                $shipping_item->set_name($new_shipping_title); // Set new title
                $shipping_item->save(); // Save the changes
            }
        }
    }
}

// add_action('woocommerce_checkout_create_order', 'olza_update_pickup_order_meta');
// function olza_update_pickup_order_meta($order) {

//     $chosen_methods = WC()->session->get('chosen_shipping_methods');
//     if (strpos($chosen_methods[0], 'olza_pickup') !== false) {

//         // Add meta data to order
//         if (isset($_POST['olza_pickup_option']) && !empty($_POST['olza_pickup_option'])) {
//             $order->update_meta_data('Pickup Point', $_POST['olza_pickup_option']);
//         }
//         if (isset($_POST['delivery_point_id']) && !empty($_POST['delivery_point_id'])) {
//             $order->update_meta_data('Delivery Point ID', $_POST['delivery_point_id']);
//         }
//         if (isset($_POST['delivery_courier_id']) && !empty($_POST['delivery_courier_id'])) {
//             $order->update_meta_data('Delivery Courier', $_POST['delivery_courier_id']);
//         }

//         // Dynamically set shipping method title
//         $pickup_address = isset($_POST['olza_pickup_option']) ? $_POST['olza_pickup_option'] : '';
//         $delivery_courier_id = isset($_POST['delivery_courier_id']) ? $_POST['delivery_courier_id'] : '';
//         $country_name = isset($_POST['country_name']) && $_POST['country_name'] != '' ? $_POST['country_name'] : 'CZ';
        
//         // Update the shipping title with country
//         $new_shipping_title = $country_name . '-' . $delivery_courier_id;

//         // Update shipping method title
//         foreach ($order->get_items('shipping') as $item_id => $shipping_item) {
//             if ($shipping_item->get_method_id() == 'olza_pickup') {
//                 $shipping_item->set_name($new_shipping_title); // Set new title
//                 $shipping_item->save(); // Save the changes
//             }
//         }
//     }
// }

add_action('woocommerce_admin_order_data_after_billing_address', 'olza_display_pickup_in_admin_orders', 10, 1);
function olza_display_pickup_in_admin_orders($order)
{

    $pickup_field_value = $order->get_meta('Pickup Point');

    if (!empty($pickup_field_value)) {
        echo '<p><strong>' . __('Pickup Point', 'olza-logistic-woo') . ':</strong> ' . $pickup_field_value . '</p>';
    }
}

add_action('woocommerce_order_details_after_order_table_items', 'olza_display_pickup_at_order_details');

function olza_display_pickup_at_order_details($order)
{
    $chosen_methods = olza_get_chosen_shipping_methods();
    $is_pickup_method = olza_is_pickup_shipping_selected($chosen_methods);

    if (!$is_pickup_method) {
        foreach ($order->get_shipping_methods() as $shipping_item) {
            if (strpos($shipping_item->get_method_id(), 'olza_pickup') !== false) {
                $is_pickup_method = true;
                break;
            }
        }
    }

    if (!$is_pickup_method) {
        return;
    }

    $pickup_point = $order->get_meta('Pickup Point');

    if ($pickup_point) :
?>
        <tr>
            <th scope="row"><?php echo __('Pickup Point', 'olza-logistic-woo'); ?> </th>
            <td><?php echo esc_html($pickup_point); ?></td>

        </tr>
    <?php
    endif;
}


/**
 * APP Url Validation
 */

function olza_validate_url($url)
{

    if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
        return false;
    }

    if (strpos($url, '://') !== false) {
        list($protocol, $rest_of_url) = explode('://', $url, 2);

        $rest_of_url = str_replace('//', '/', $rest_of_url);

        return $protocol . '://' . $rest_of_url;
    } else {
        return str_replace('//', '/', $url);
    }
}


/**
 * Get Pickup Points
 */

add_action('wp_ajax_olza_get_pickup_points', 'olza_get_pickup_points_callback');
add_action('wp_ajax_nopriv_olza_get_pickup_points', 'olza_get_pickup_points_callback');

function olza_get_pickup_points_callback()
{
    global $olza_options;
    $olza_options = get_option('olza_options');

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'olza_checkout')) {
        echo json_encode(array('success' => false, 'message' => __('Security verification failed.', 'olza-logistic-woo')));
        wp_die();
    }

    $country = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';
    $country_name = isset($_POST['country_name']) ? sanitize_text_field(wp_unslash($_POST['country_name'])) : '';

    if (empty($country)) {
        echo json_encode(array('success' => false, 'message' => __('Please choose a country before loading pickup points.', 'olza-logistic-woo')));
        wp_die();
    }

    $country = strtolower($country);

    $api_url = isset($olza_options['api_url']) && !empty($olza_options['api_url']) ? $olza_options['api_url'] : '';
    $access_token = isset($olza_options['access_token']) && !empty($olza_options['access_token']) ? $olza_options['access_token'] : '';

    $selected_provider = '';

    if (isset($_POST['provider_code']) && !empty($_POST['provider_code'])) {
        $selected_provider = olza_normalize_code(wp_unslash($_POST['provider_code']));
    }

    if (empty($selected_provider) && isset($_POST['spedition']) && !empty($_POST['spedition'])) {
        $rate_identifier = sanitize_text_field(wp_unslash($_POST['spedition']));

        if (strpos($rate_identifier, ':') !== false) {
            $parts = explode(':', $rate_identifier);
            $selected_provider = olza_normalize_code(end($parts));
        } else {
            $selected_provider = olza_normalize_code($rate_identifier);
        }
    }

    $lat = 0;
    $lng = 0;

    $data_dir = trailingslashit(OLZA_LOGISTIC_PLUGIN_PATH) . 'data/';
    $country_file_path = $data_dir . $country . '.json';

    if (!file_exists($country_file_path)) {
        echo json_encode(array('success' => false, 'message' => __('Country file not exists.', 'olza-logistic-woo')));
        wp_die();
    }

    $config_response = file_get_contents($country_file_path);
    $config_json = json_decode($config_response);

    if (!is_object($config_json) || empty($config_json->success)) {
        echo json_encode(array('success' => false, 'message' => (is_object($config_json) && isset($config_json->message)) ? $config_json->code . ' - ' . $config_json->message : __('Unable to read pickup configuration.', 'olza-logistic-woo')));
        wp_die();
    }

    $country_speditions = olza_extract_speditions_from_config($config_json);
    $available_speditions = array_keys($country_speditions);

    $spedition_dropdown_arr = array();
    $spedition_dropdown_arr[] = array('id' => 'all', 'text' => __('ALL', 'olza-logistic-woo'));

    foreach ($country_speditions as $code => $info) {
        $label = isset($info['label']) ? $info['label'] : strtoupper($code);
        $spedition_dropdown_arr[] = array(
            'id' => $code,
            'text' => $label,
        );
    }

    if (!in_array($selected_provider, $available_speditions, true)) {
        $selected_provider = !empty($available_speditions) ? reset($available_speditions) : '';
    }

    if (empty($selected_provider)) {
        echo json_encode(array('success' => false, 'message' => __('No pickup providers available for the selected country.', 'olza-logistic-woo')));
        wp_die();
    }

    $provider_file_path = $data_dir . $country . '_' . $selected_provider . '.json';

    if (!file_exists($provider_file_path)) {
        echo json_encode(array('success' => false, 'message' => __('Pickup point file is missing or invalid.', 'olza-logistic-woo')));
        wp_die();
    }

    $find_response = file_get_contents($provider_file_path);
    $find_json = json_decode($find_response);

    if (!is_object($find_json) || empty($find_json->success)) {
        echo json_encode(array('success' => false, 'message' => isset($find_json->message) ? $find_json->message : __('Pickup point file is missing or invalid.', 'olza-logistic-woo')));
        wp_die();
    }

    $find_data_arr = isset($find_json->data) ? $find_json->data : array();
    $pickup_full_list = array();
    $centerpoint = array();

    if (!empty($find_data_arr) && !empty($find_data_arr->items)) {
        foreach ($find_data_arr->items as $pickup_obj) {
            $pickup_full_list[] = array(
                'type' => 'Feature',
                'properties' => array(
                    'title' => html_entity_decode($pickup_obj->address->full),
                    'pointid' => html_entity_decode($pickup_obj->id),
                    'spedition' => html_entity_decode($pickup_obj->spedition),
                ),
                'geometry' => array(
                    'type' => 'Point',
                    'coordinates' => array($pickup_obj->location->longitude, $pickup_obj->location->latitude),
                ),
            );

            if (empty($centerpoint)) {
                $centerpoint = array($pickup_obj->location->longitude, $pickup_obj->location->latitude);
                $lat = $pickup_obj->location->latitude;
                $lng = $pickup_obj->location->longitude;
            }
        }
    }

    if (empty($pickup_full_list)) {
        echo json_encode(array('success' => true, 'dropdown' => $spedition_dropdown_arr, 'data' => array(), 'listings' => '<ul><li>' . esc_html(sprintf(__('There are no pickup points in %s.', 'olza-logistic-woo'), $country_name)) . '</li></ul>', 'center' => array(), 'message' => sprintf(__('There are no pickup points in %s.', 'olza-logistic-woo'), $country_name)));
        wp_die();
    }

    if (empty($api_url) || empty($access_token)) {
        echo json_encode(array('success' => false, 'message' => __('Please verify APP URL & Acess Token.', 'olza-logistic-woo')));
        wp_die();
    }

    $args = array(
        'timeout' => 30,
        'headers' => array('Content-Type' => 'application/json'),
    );

    $nearby_api_endpoint = olza_validate_url($api_url . '/nearby');
    $nearby_params = array(
        'access_token' => $access_token,
        'country' => $country,
        'spedition' => $selected_provider,
    );

    if (!empty($lat) && !empty($lng)) {
        $nearby_params['location'] = $lat . ',' . $lng;
    }

    $nearby_api_url = add_query_arg($nearby_params, $nearby_api_endpoint);
    $nearby_response = wp_remote_get($nearby_api_url, $args);

    $item_listings = '<ul>';

    if (is_wp_error($nearby_response)) {
        $item_listings .= '<li>' . esc_html($nearby_response->get_error_message()) . '</li>';
    } else {
        $nearby_data = wp_remote_retrieve_body($nearby_response);
        $nearby_json = json_decode($nearby_data);
        $nearby_items = (is_object($nearby_json) && isset($nearby_json->data) && isset($nearby_json->data->items)) ? $nearby_json->data->items : array();

        if (!empty($nearby_items)) {
            foreach ($nearby_items as $nearby_obj) {
                $item_listings .= '<li><a class="olza-flyto" href="javascript:void(0)" pointid="' . esc_attr($nearby_obj->id) . '" spedition="' . esc_attr($nearby_obj->spedition) . '" lat="' . esc_attr($nearby_obj->location->latitude) . '" long="' . esc_attr($nearby_obj->location->longitude) . '" address="' . esc_attr(html_entity_decode($nearby_obj->address->full)) . '"><p class="ad-name">' . esc_html(html_entity_decode($nearby_obj->names[0])) . '</p><p class="ad-full">' . esc_html(html_entity_decode($nearby_obj->address->full)) . '</p><p class="ad-dis">' . esc_html($nearby_obj->location->distance) . ' m</p></a></li>';
            }
        } else {
            $item_listings .= '<li>' . __('No places found', 'olza-logistic-woo') . '</li>';
        }
    }

    $item_listings .= '</ul>';

    echo json_encode(array(
        'success' => true,
        'dropdown' => $spedition_dropdown_arr,
        'listings' => $item_listings,
        'center' => $centerpoint,
        'data' => $pickup_full_list,
        'message' => __('Pick Points Loaded Successfully.', 'olza-logistic-woo'),
        'provider' => $selected_provider,
    ));
    wp_die();
}
function olza_get_all_provider_details()
{
    $options = get_option('olza_options');

    if (!isset($options['available_speditions']) || !is_array($options['available_speditions'])) {
        return array();
    }

    return $options['available_speditions'];
}

function olza_get_shipping_provider_choices($selected = '')
{
    $provider_map = olza_get_all_provider_details();
    $choices = array();

    foreach ($provider_map as $country_code => $providers) {
        if (!is_array($providers)) {
            continue;
        }

        foreach ($providers as $code => $info) {
            $provider_code = '';
            $label = '';

            if (is_array($info)) {
                $provider_code = isset($info['code']) ? $info['code'] : $code;
                $label = isset($info['label']) ? $info['label'] : '';
            } elseif (is_object($info)) {
                $provider_code = isset($info->code) ? $info->code : $code;
                $label = isset($info->label) ? $info->label : '';
            } else {
                $provider_code = $code;
            }

            $provider_code = olza_normalize_code($provider_code);

            if (empty($provider_code)) {
                continue;
            }

            if (empty($label)) {
                $label = strtoupper($provider_code);
            }

            $display_label = $label;

            if (!empty($country_code)) {
                $display_label .= ' – ' . strtoupper($country_code);
            }

            if (isset($choices[$provider_code])) {
                if (!empty($country_code)) {
                    $choices[$provider_code] .= ', ' . strtoupper($country_code);
                }
            } else {
                $choices[$provider_code] = $display_label;
            }
        }
    }

    $selected = olza_normalize_code($selected);

    if (!empty($selected) && !isset($choices[$selected])) {
        $choices[$selected] = strtoupper($selected);
    }

    return $choices;
}

function olza_get_provider_details($provider_code)
{
    $provider_code = olza_normalize_code($provider_code);

    $details = array(
        'code' => $provider_code,
        'label' => $provider_code ? strtoupper($provider_code) : '',
        'countries' => array(),
    );

    if (empty($provider_code)) {
        return $details;
    }

    $provider_map = olza_get_all_provider_details();

    foreach ($provider_map as $country_code => $providers) {
        if (!is_array($providers)) {
            continue;
        }

        foreach ($providers as $code => $info) {
            $matched_code = '';
            $label = '';

            if (is_array($info)) {
                $matched_code = isset($info['code']) ? olza_normalize_code($info['code']) : olza_normalize_code($code);
                $label = isset($info['label']) ? $info['label'] : '';
            } elseif (is_object($info)) {
                $matched_code = isset($info->code) ? olza_normalize_code($info->code) : olza_normalize_code($code);
                $label = isset($info->label) ? $info->label : '';
            } else {
                $matched_code = olza_normalize_code($code);
            }

            if ($matched_code !== $provider_code) {
                continue;
            }

            if (!empty($label)) {
                $details['label'] = $label;
            }

            $details['countries'][] = strtoupper($country_code);
        }
    }

    return $details;
}


add_filter('woocommerce_shipping_methods', 'register_tyche_method');

/**
 * Register Shipping Method
 *
 * @param [type] $methods
 * @return void
 */
function register_tyche_method($methods)
{
    $methods['olza_pickup'] = 'WC_Shipping_Olza_Pickup';
    $methods['olza_pickup_wedobox'] = 'WC_Shipping_Olza_Pickup_wedobox'; // Updated ID
    return $methods;
}

abstract class WC_Shipping_Olza_Pickup_Base extends WC_Shipping_Method
{
    protected $default_method_title = '';
    protected $default_method_description = '';
    protected $default_provider_code = '';
    protected $provider_code = '';
    protected $provider_label = '';
    protected $provider_countries = array();

    public function __construct($instance_id = 0)
    {
        $this->instance_id = absint($instance_id);
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );

        $this->init_form_fields();

        $this->enabled = $this->get_option('enabled', 'yes');
        $this->title = $this->get_option('title', $this->default_method_title);

        $this->load_provider_settings();

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    protected function init_form_fields()
    {
        $provider_choices = olza_get_shipping_provider_choices($this->default_provider_code);

        $provider_field = array(
            'title' => __('Pickup provider', 'olza-logistic-woo'),
            'description' => __('Choose which provider this shipping method should use. Sync pickup data to refresh this list.', 'olza-logistic-woo'),
            'desc_tip' => true,
        );

        if (!empty($provider_choices)) {
            $provider_field['type'] = 'select';
            $provider_field['options'] = array('' => __('— Select a provider —', 'olza-logistic-woo')) + $provider_choices;
            $provider_field['default'] = $this->default_provider_code;
        } else {
            $provider_field['type'] = 'text';
            $provider_field['description'] = __('Enter the pickup provider code (e.g. ppl-ps). Sync pickup data to populate this list.', 'olza-logistic-woo');
            $provider_field['desc_tip'] = false;
        }

        $this->instance_form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'olza-logistic-woo'),
                'type' => 'checkbox',
                'label' => __('Enable this shipping method', 'olza-logistic-woo'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Method Title', 'olza-logistic-woo'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'olza-logistic-woo'),
                'default' => $this->default_method_title,
                'desc_tip' => true,
            ),
            'provider_code' => $provider_field,
        );
    }

    protected function load_provider_settings()
    {
        $provider_code = $this->get_option('provider_code', $this->default_provider_code);
        $provider_code = olza_normalize_code($provider_code);
        $this->provider_code = $provider_code;

        $details = olza_get_provider_details($provider_code);
        $this->provider_label = !empty($details['label']) ? $details['label'] : ($provider_code ? strtoupper($provider_code) : '');
        $this->provider_countries = !empty($details['countries']) ? $details['countries'] : array();
    }

    public function calculate_shipping($package = array())
    {
        $this->load_provider_settings();

        if (empty($this->provider_code)) {
            return;
        }

        $label = $this->title;

        if (!empty($this->provider_label)) {
            $label .= ' (' . $this->provider_label . ')';
        }

        $rate_id = $this->id . '_' . $this->instance_id . ':' . $this->provider_code;

        $this->add_rate(array(
            'id' => $rate_id,
            'label' => $label,
            'meta_data' => array(
                'provider_code' => $this->provider_code,
                'provider_label' => $this->provider_label,
                'provider_countries' => $this->provider_countries,
            ),
        ));
    }

    public function process_admin_options()
    {
        parent::process_admin_options();

        $provider_key = $this->get_field_key('provider_code');

        if (isset($_POST[$provider_key])) {
            $provider_code = wc_clean(wp_unslash($_POST[$provider_key]));
            $provider_code = olza_normalize_code($provider_code);
            $this->update_option('provider_code', $provider_code);
        }

        $this->load_provider_settings();
    }
}

class WC_Shipping_Olza_Pickup extends WC_Shipping_Olza_Pickup_Base
{
    public function __construct($instance_id = 0)
    {
        $this->id = 'olza_pickup';
        $this->default_method_title = __('PickUp Points', 'olza-logistic-woo');
        $this->method_title = $this->default_method_title;
        $this->default_method_description = __('PickUp Points Shipping method.', 'olza-logistic-woo');
        $this->method_description = $this->default_method_description;
        $this->default_provider_code = 'ppl-ps';

        parent::__construct($instance_id);
    }
}

class WC_Shipping_Olza_Pickup_wedobox extends WC_Shipping_Olza_Pickup_Base
{
    public function __construct($instance_id = 0)
    {
        $this->id = 'olza_pickup_wedobox';
        $this->default_method_title = __('Wedo Box', 'olza-logistic-woo');
        $this->method_title = $this->default_method_title;
        $this->default_method_description = __('Wedo Box Shipping method.', 'olza-logistic-woo');
        $this->method_description = $this->default_method_description;
        $this->default_provider_code = 'wedo-box';

        parent::__construct($instance_id);
    }
}


function olza_get_rate_meta_value($method, $key, $default = '')
{
    if (!is_object($method) || !method_exists($method, 'get_meta_data')) {
        return $default;
    }

    $meta_data = $method->get_meta_data();

    if (empty($meta_data)) {
        return $default;
    }

    foreach ($meta_data as $meta_item) {
        if (is_object($meta_item)) {
            if (isset($meta_item->key) && $meta_item->key === $key) {
                return $meta_item->value;
            }

            if (method_exists($meta_item, 'get_data')) {
                $meta_array = $meta_item->get_data();

                if (is_array($meta_array) && isset($meta_array['key']) && $meta_array['key'] === $key) {
                    return isset($meta_array['value']) ? $meta_array['value'] : $default;
                }
            }
        } elseif (is_array($meta_item) && isset($meta_item['key']) && $meta_item['key'] === $key) {
            return isset($meta_item['value']) ? $meta_item['value'] : $default;
        }
    }

    return $default;
}

add_action('woocommerce_after_shipping_rate', 'add_link_to_custom_shipping_method', 10, 2);

function add_link_to_custom_shipping_method($method, $index)
{
    if (strpos($method->method_id, 'olza_pickup') !== 0) {
        return;
    }

    $provider_code = olza_get_rate_meta_value($method, 'provider_code', '');
    $provider_label = olza_get_rate_meta_value($method, 'provider_label', '');

    $provider_attr = $provider_code ? ' data-provider="' . esc_attr($provider_code) . '"' : '';
    $label_attr = $provider_label ? ' data-provider-label="' . esc_attr($provider_label) . '"' : '';

    echo '<div class="oloz-pickup-selection" style="display:none;"' . $provider_attr . $label_attr . '><p><span></span></p></div>';
    echo '<a href="javascript:void(0)" class="olza-load-map" style="display:none;"' . $provider_attr . $label_attr . '>' . __('Choose Pickup', 'olza-logistic-woo') . '</a>';
}

add_action('wp_footer', 'olz_logistic_load_moadal_map_pickups');

function olz_logistic_load_moadal_map_pickups()
{

    if (class_exists('WooCommerce') && (is_checkout() || is_cart())) {

        ?>
        <div id="custom-modal" class="custom-modal">
            <div class="custom-modal-dialog">
                <div class="custom-modal-content">
                    <a href="javascript:void(0)" class="olza-close-modal">X</a>
                    <div class="custom-modal-body">
                        <div class="custom-modal-inner">
                            <div class="olza-map-dialog">

                                <div class="olza-map-filters">
                                    <div class="olza-filters-wrap">
                                        <span class="olza-loader-overlay"></span>
<!--                                         <div class="olza-spedition-wrap">
                                            <select id="olza-spedition-dropdown">
                                                <option value="all"><?php //echo __('ALL', 'olza-logistic-woo'); ?></option>
                                            </select>
                                        </div> -->
                                        <div class="olza-search-wrap" id="olza-geocoder"></div>
                                    </div>
                                    <div class="olza-point-listings">
                                        <span class="olza-loader-overlay"></span>
                                        <div class="olza-listings-head">
                                            <p><?php echo __('Closest Pickup Points', 'olza-logistic-woo'); ?></p>
                                        </div>
                                        <div class="olza-closest-listings">
                                            <ul>
                                                <li>
                                                    <?php echo __('No Nearby Found', 'olza-pickup-woo'); ?>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="olza-map-data">
                                    <div id="olza-pickup-map"><span class="olza-loader-overlay"></span></div>
                                    <div class="oloz-pickup-selection">
                                        <p><strong><?php echo __('PickUp Selection : ', 'olza-logistic-woo'); ?></strong><span></span></p>
                                    </div>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            jQuery(function($) {
                function olza_update_map_btn() {

//                     if (jQuery('#shipping_method').length > 0) {
//                         var selectedShippingMethod = jQuery('input[name="shipping_method[0]"]:checked').val();

//                         if (selectedShippingMethod.includes('olza_pickup')) {
//                             jQuery('.olza-load-map').show();
//                         } else {
//                             jQuery('.olza-load-map').hide();
//                         }
//                     }
    jQuery(document).ready(function() {

    function olzaToggleControls() {
        var $selected = jQuery('input[name="shipping_method[0]"]:checked');

        jQuery('.olza-load-map').hide();
        jQuery('.oloz-pickup-selection').hide();

        if (!$selected.length) {
            return;
        }

        var $container = $selected.closest('li');
        var $link = $container.find('.olza-load-map');
        var provider = $link.data('provider') || '';
        var rateValue = $selected.val() ? $selected.val().toString() : '';

        if (!provider && rateValue.indexOf(':') !== -1) {
            provider = rateValue.split(':').pop();
        }

        if (provider) {
            $link.data('provider', provider).show();
            $container.find('.oloz-pickup-selection').data('provider', provider).show();
        }
    }

    olzaToggleControls();

    jQuery('input[name="shipping_method[0]"]').change(function() {
        olzaToggleControls();
    });

    // Hide pickup options initially when clicking the close modal button
    jQuery('.olza-close-modal').click(function() {
        jQuery('.olza-load-map').hide();
        jQuery('.oloz-pickup-selection').hide();
        olzaToggleControls();
    });

});




                }

                jQuery(document.body).on('updated_checkout', function() {
                    olza_update_map_btn();
                });

                jQuery(document).ready(function() {
                    olza_update_map_btn();
                });

  // Check if .oloz-pickup-selection has display: block
//   if (jQuery('.oloz-pickup-selection').css('display') === 'block') {
//     // Apply top: 555px to .olza-load-map
//     jQuery('.olza-load-map').css('top', '555px');
//   }

// 				$(document).ready(function() {
//    jQuery('.olza-load-map').click(function() {
//         const selectedMethod = jQuery('input[name="shipping_method[0]"]:checked').val();

//         let dropdownValue = '';
//         if (selectedMethod === 'olza_pickup_wedobox_25') {
//             dropdownValue = 'wedo-box';
//         } else if (selectedMethod === 'olza_pickup_21') {
//             dropdownValue = 'ppl-ps';
//         }

//         // Set the dropdown value if it matches
//         if (dropdownValue) {
//             jQuery('#olza-spedition-dropdown').val(dropdownValue).trigger('change');
//         }
//     });
// });

			
            });

// jQuery(document).on('click', '.olza-load-map', function() {
//     var selectedMethod = jQuery('input[name="shipping_method[0]"]:checked').val();

//     if (selectedMethod) {
//         // Determine the value to select based on the shipping method
//         var selectedValue = '';

//         if (selectedMethod === 'olza_pickup_wedobox_28') {
//             selectedValue = 'wedo-box'; // Value for Wedo Box
// alert(selectedValue);
//         } else if (selectedMethod === 'olza_pickup_29') {
//             selectedValue = 'ppl-ps'; // Value for PPL PS
//         } else {
//             // Handle other cases if necessary
//             selectedValue = ''; // Default selection or another relevant option
//         }

//         // Set the value in the Select2 dropdown
// //        jQuery('#olza-spedition-dropdown').val(selectedValue).trigger('change');
//   jQuery.ajax({
//             url: ajaxurl, // WordPress AJAX URL
//             type: 'POST',
//             data: {
//                 action: 'olza_get_pickup_points', // Your AJAX action
//                 selected_value: selectedValue // Send the selected value
//             },
//             success: function(response) {
//                 // Handle the response from the server
//                 console.log(response); // You can modify this to update the UI or show a message
//             },
//             error: function(xhr, status, error) {
//                 console.error(error); // Handle any errors
//             }
//         });
//         // Show the popup
//         jQuery('#pickupModal').show();
//     } else {
//         alert('Please select a shipping method.');
//     }
// });

// jQuery(document).ready(function() {
//     jQuery('#olza-spedition-dropdown').select2();
// });

        </script>
<style>
	.custom-modal {
    z-index: 999;
}
body .olza-load-map {
    white-space: nowrap;
    max-width: 170px !important;
}
	#shipping_method li {
    position: relative;
}
	
/* body .olza-load-map {
    padding: 10px;
    background: #9c80b7;
    text-decoration: none;
    color: #fff;
    border-radius: 5px;
    margin-top: 5px;
    display: inline-block;
    position: absolute;
    top: 630px;
    right: 55px;
    align-items: center;
    text-align: center;
    max-width: 160px;
    margin: auto;
} */
	body li:last-child .olza-load-map{
bottom: -55px;
}
body .olza-load-map {
    padding: 10px;
    background: #9c80b7;
    text-decoration: none;
    color: #fff;
    border-radius: 5px;
    margin-top: 5px;
    display: inline-block;
    position: absolute;
    top: auto;
    right: 0px;
/* 	bottom: -55px; */
    bottom: -90px; 
    align-items: center;
    text-align: center;
    max-width: 160px;
    margin: auto;
}
#shipping_method li:last-child {
    margin-bottom: 50px !important;
}
	@media only screen and (max-width: 800px) {
    .olza-close-modal {
        position: sticky;
        display: flex
;
        justify-content: center;
        align-items: center;
        margin-left: auto;
    }
		    .olza-map-filters {
        width: 100%;
        padding: 20px;
        padding-top: 0;
    }
		    .custom-modal-dialog {
        height: 81%;
        overflow-y: scroll;
        border-radius: 10px;
        margin-top: 50px;
        z-index: 99999;
    }
}
</style>
<?php

    }
}


/**
 * Get Pickup Points
 */

add_action('wp_ajax_olza_get_nearby_points', 'olza_get_nearby_points_callback');
add_action('wp_ajax_nopriv_olza_get_nearby_points', 'olza_get_nearby_points_callback');

function olza_get_nearby_points_callback()
{
    global $olza_options;
    $olza_options = get_option('olza_options');

    if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'olza_checkout')) {

        $lat = isset($_POST['lat']) && $_POST['lat'] != '' ? sanitize_text_field(wp_unslash($_POST['lat'])) : '';
        $lng = isset($_POST['lng']) && $_POST['lng'] != '' ? sanitize_text_field(wp_unslash($_POST['lng'])) : '';
        $cont = isset($_POST['cont']) && !empty($_POST['cont']) ? sanitize_text_field(wp_unslash($_POST['cont'])) : '';

        $sped_codes = array();

        if (isset($_POST['sped']) && !empty($_POST['sped'])) {
            $sped_raw = wp_unslash($_POST['sped']);
            $sped_codes = olza_sanitize_codes_list($sped_raw);
        }

        $sped = !empty($sped_codes) ? implode(',', $sped_codes) : '';

        $api_url = isset($olza_options['api_url']) && !empty($olza_options['api_url']) ? $olza_options['api_url'] : '';
        $access_token = isset($olza_options['access_token']) && !empty($olza_options['access_token']) ? $olza_options['access_token'] : '';

        /**
         * Nearby Places
         */

        $nearby_api_endpoint = olza_validate_url($api_url . '/nearby');

        $nearby_t_args = array(
            'access_token' => $access_token,
            'country' => $cont,
            'spedition' => $sped,
        );

        if ($lat != 0 && $lng != 0) {
            $nearby_t_args['location'] = $lng . ',' . $lat;
        }

        $nearby_api_url = add_query_arg($nearby_t_args, $nearby_api_endpoint);

        $nearby_args = array(
            'timeout'   => 300, // Timeout in seconds
            'headers'   => array(
                'Content-Type'  => 'application/json'
            )
        );

        $nrearby_response = wp_remote_get($nearby_api_url, $nearby_args);

        // Initialize the dropdown options
        $item_listings = '<ul>';
        if (is_wp_error($nrearby_response)) {
            $error_message = $nrearby_response->get_error_message();
            $item_listings .= '<li>' . $error_message . '</li>';
        } else {
            $nearbydata = wp_remote_retrieve_body($nrearby_response);
            $nearbydata_json = json_decode($nearbydata);
            $nearbydata_arr = (is_object($nearbydata_json) && isset($nearbydata_json->data)) ? $nearbydata_json->data : array();

            $allowed_providers = array_map('olza_normalize_code', $sped_codes);

            $nearby_items = array();

            if (is_object($nearbydata_arr) && isset($nearbydata_arr->items)) {
                $nearby_items = $nearbydata_arr->items;
            } elseif (is_array($nearbydata_arr) && isset($nearbydata_arr['items'])) {
                $nearby_items = $nearbydata_arr['items'];
            }

            if (!empty($nearby_items)) {
                foreach ($nearby_items as $key => $nearby_obj) {
                    $spedition = isset($nearby_obj->spedition) ? olza_normalize_code($nearby_obj->spedition) : '';

                    if (!empty($allowed_providers) && !in_array($spedition, $allowed_providers, true)) {
                        continue;
                    }

                    $point_id = isset($nearby_obj->id) ? $nearby_obj->id : '';
                    $spedition_raw = isset($nearby_obj->spedition) ? $nearby_obj->spedition : '';
                    $latitude = '';
                    $longitude = '';
                    $distance = '';

                    if (isset($nearby_obj->location) && is_object($nearby_obj->location)) {
                        $latitude = isset($nearby_obj->location->latitude) ? $nearby_obj->location->latitude : '';
                        $longitude = isset($nearby_obj->location->longitude) ? $nearby_obj->location->longitude : '';
                        $distance = isset($nearby_obj->location->distance) ? $nearby_obj->location->distance : '';
                    }

                    $address_full = '';

                    if (isset($nearby_obj->address) && is_object($nearby_obj->address) && isset($nearby_obj->address->full)) {
                        $address_full = html_entity_decode($nearby_obj->address->full);
                    }

                    $name = '';

                    if (isset($nearby_obj->names) && is_array($nearby_obj->names) && !empty($nearby_obj->names)) {
                        $name = html_entity_decode(reset($nearby_obj->names));
                    }

                    $item_listings .= '<li><a class="olza-flyto" href="javascript:void(0)" pointid="' . esc_attr($point_id) . '" spedition="' . esc_attr($spedition_raw) . '" lat="' . esc_attr($latitude) . '" long="' . esc_attr($longitude) . '" address="' . esc_attr($address_full) . '"><p class="ad-name">' . esc_html($name) . '</p><p class="ad-full">' . esc_html($address_full) . '</p><p class="ad-dis">' . esc_html($distance) . ' m</p></a></li>';
                }
            } else {
                $item_listings .= '<li>' . __('No Nearby Found', 'olza-pickup-woo') . '</li>';
            }
        }

        $item_listings .= '</ul>';

        echo json_encode(array('success' => true, 'listings' => $item_listings, 'message' => __('Nearby Points Loaded Successfully.', 'olza-logistic-woo')));
        wp_die();
    } else {
        echo json_encode(array('success' => false, 'message' => __('Security verification failed.', 'olza-logistic-woo')));
        wp_die();
    }
}




/**
 * Adding Cart Fee
 */

add_action('woocommerce_cart_calculate_fees', 'olza_add_cart_fee', 20, 1);
function olza_add_cart_fee($cart)
{
    if (is_admin() && !defined('DOING_AJAX'))
        return;

    global $woocmmerce, $olza_options;
    $chosen_methods = olza_get_chosen_shipping_methods();

    if (olza_is_pickup_shipping_selected($chosen_methods)) {


        $olza_options = get_option('olza_options');
        $basket_fee = isset($olza_options['basket_fee']) && !empty($olza_options['basket_fee']) ? $olza_options['basket_fee'] : '';

        //$total_coast = (int) WC()->cart->get_cart_contents_total();
		$total_coast = (int) $cart->subtotal;

        $fee_amount = olza_calculateBasketFee($total_coast, $basket_fee);

        $fee_text = __("Pickup Fee", "olza-logistic-woo");
        $cart->add_fee($fee_text, $fee_amount, false);
    }
}


function olza_calculateBasketFee($basketAmount, $feeRules)
{
    foreach ($feeRules as $rule) {
        switch ($rule['condition']) {
            case 'less':
                if ($basketAmount < $rule['amount']) {
                    return $rule['fee'];
                }
                break;
            case 'greater_than_equal':
                if ($basketAmount >= $rule['amount']) {
                    return $rule['fee'];
                }
                break;
            case 'equal':
                if ($basketAmount = $rule['amount']) {
                    return $rule['fee'];
                }
                break;
            case 'less_than_equal':
                if ($basketAmount <= $rule['amount']) {
                    return $rule['fee'];
                }
                break;
            case 'greater':
                if ($basketAmount > $rule['amount']) {
                    return $rule['fee'];
                }
                break;
        }
    }
    return 0;
}


add_action( 'woocommerce_checkout_order_processed', 'custom_update_shipping_address', 25, 3 );
//add_action('woocommerce_checkout_update_order_meta', 'custom_update_shipping_address', 10, 2);

function custom_update_shipping_address($order_id, $posted_data, $order)
{
    $order = wc_get_order($order_id);
    $shipping_methods = $order->get_shipping_methods();

    $update_shipping_address = false;

    foreach ($shipping_methods as $shipping_method) {
        if (strpos($shipping_method->get_method_id(), 'olza_pickup') !== false) {
            $update_shipping_address = true;
            break;
        }
    }

    if ($update_shipping_address) {

        $pickup_address = get_post_meta($order_id, 'Pickup Point', true);

        // $new_shipping_address = array();

        // if (!empty($pickup_address)) {
        //     $new_shipping_address['address_1'] = $pickup_address;
        // }

        // if (!empty($new_shipping_address) && sizeof($new_shipping_address) > 0) {
        //     foreach ($new_shipping_address as $key => $value) {
        //         $order->update_meta_data('_shipping_' . $key, $value);
        //     }
        // }

        $order->set_shipping_address_1( $pickup_address );
        $order->save();
    }
}


add_action('woocommerce_admin_order_data_after_shipping_address', 'olza_logistic_update_admin_order_metabox', 10, 1);
function olza_logistic_update_admin_order_metabox($order)
{

    $order_id = $order->get_id();
    $order = wc_get_order($order_id);
    $shipping_methods = $order->get_shipping_methods();

    $update_shipping_address = false;

    foreach ($shipping_methods as $shipping_method) {
        if (strpos($shipping_method->get_method_id(), 'olza_pickup') !== false) {
            $update_shipping_address = true;
            break;
        }
    }

    if ($update_shipping_address) {

        $pickup_address = get_post_meta($order_id, 'Pickup Point', true);
        $delivery_point_id = get_post_meta($order_id, 'delivery_point_id', true);
        $delivery_courier_id = get_post_meta($order_id, 'delivery_courier_id', true);

        echo '<p><strong>' . __('Pickup Points Data', 'olza-logistic-woo') . '</strong></br>';
        echo '<strong>' . __('Pickup Address', 'olza-logistic-woo') . '</strong> : ' . $pickup_address . '</br>';
        echo '<strong>' . __('Pickup Point ID', 'olza-logistic-woo') . '</strong> : ' . $delivery_point_id . '</br>';
        echo '<strong>' . __('Pickup Point Courier', 'olza-logistic-woo') . '</strong> : ' . $delivery_courier_id . ' </p>';
    }
}