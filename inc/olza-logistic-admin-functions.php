<?php
/**
 * Get Pickup Points files
 */

add_action('wp_ajax_olza_get_pickup_point_files', 'olza_get_pickup_point_files_callback');

function olza_get_pickup_point_files_callback()
{
    global $olza_options;
    $olza_options = get_option('olza_options');

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'olza_load_files')) {
        echo json_encode(array('success' => false, 'message' => __('Security verification failed.', 'olza-logistic-woo')));
        wp_die();
    }

    $api_url = isset($olza_options['api_url']) && !empty($olza_options['api_url']) ? $olza_options['api_url'] : '';
    $access_token = isset($olza_options['access_token']) && !empty($olza_options['access_token']) ? $olza_options['access_token'] : '';

    if (empty($api_url) || empty($access_token)) {
        echo json_encode(
            array(
                'success' => false,
                'message' => __('Please verify APP URL & Access Token.', 'olza-logistic-woo')
            )
        );
        wp_die();
    }

    $countries_raw = '';
    if (isset($_POST['countries'])) {
        $countries_raw = wp_unslash($_POST['countries']);
    } elseif (isset($olza_options['country_codes'])) {
        $countries_raw = $olza_options['country_codes'];
    }

    $country_arr = olza_sanitize_codes_list($countries_raw);

    if (empty($country_arr)) {
        echo json_encode(
            array(
                'success' => false,
                'message' => __('Please enter at least one country code before syncing.', 'olza-logistic-woo')
            )
        );
        wp_die();
    }

    $speditions_raw = '';
    if (isset($_POST['speditions'])) {
        $speditions_raw = wp_unslash($_POST['speditions']);
    } elseif (isset($olza_options['spedition_codes'])) {
        $speditions_raw = $olza_options['spedition_codes'];
    }

    $spedition_filter = olza_sanitize_codes_list($speditions_raw);

    $clear_param = isset($_POST['clear_before_sync']) ? wp_unslash($_POST['clear_before_sync']) : (isset($olza_options['clear_before_sync']) ? $olza_options['clear_before_sync'] : 'no');
    $clear_param = strtolower((string) $clear_param);
    $clear_before_sync = in_array($clear_param, array('1', 'yes', 'true'), true);

    $data_dir = trailingslashit(OLZA_LOGISTIC_PLUGIN_PATH) . 'data/';

    if (!file_exists($data_dir)) {
        wp_mkdir_p($data_dir);
    }

    $is_writable = is_dir($data_dir);

    if ($is_writable) {
        if (function_exists('wp_is_writable')) {
            $is_writable = wp_is_writable($data_dir);
        } else {
            $is_writable = is_writable($data_dir);
        }
    }

    if (!$is_writable) {
        echo json_encode(
            array(
                'success' => false,
                'message' => __('Unable to write pickup data files. Please check the plugin data directory permissions.', 'olza-logistic-woo')
            )
        );
        wp_die();
    }

    $messages = array();
    $errors = array();

    if ($clear_before_sync) {
        olza_clear_pickup_point_files($data_dir);
        $messages[] = __('Existing pickup data cleared.', 'olza-logistic-woo');
    }

    foreach ($country_arr as $country) {

        $api_endpoint = olza_validate_url($api_url . '/config');

        $config_api_url = add_query_arg(array(
            'access_token' => $access_token,
            'country' => $country,
        ), $api_endpoint);

        $args = array(
            'timeout'   => 30,
            'headers'   => array(
                'Content-Type'  => 'application/json'
            )
        );

        $config_response = wp_remote_get($config_api_url, $args);

        if (is_wp_error($config_response)) {

            $errors[] = sprintf(
                __('%1$s: %2$s', 'olza-logistic-woo'),
                strtoupper($country),
                $config_response->get_error_message()
            );
            continue;
        }

        $country_data = wp_remote_retrieve_body($config_response);

        $country_file = $data_dir . $country . '.json';

        if (false === file_put_contents($country_file, $country_data)) {
            $errors[] = sprintf(
                __('%s: Unable to save configuration file.', 'olza-logistic-woo'),
                strtoupper($country)
            );
            continue;
        }

        $messages[] = sprintf(__('Configuration for %s downloaded.', 'olza-logistic-woo'), strtoupper($country));

        $country_json = json_decode($country_data);
        $available_speditions = array();

        if (is_object($country_json) && isset($country_json->data) && isset($country_json->data->speditions) && is_array($country_json->data->speditions)) {
            foreach ($country_json->data->speditions as $speditions_obj) {
                if (isset($speditions_obj->code)) {
                    $available_speditions[] = olza_normalize_code($speditions_obj->code);
                }
            }
        }

        if (empty($available_speditions)) {
            $messages[] = sprintf(__('No pickup providers returned for %s.', 'olza-logistic-woo'), strtoupper($country));
            continue;
        }

        if (!empty($spedition_filter)) {
            $target_speditions = array_values(array_intersect($available_speditions, $spedition_filter));

            if (empty($target_speditions)) {
                $messages[] = sprintf(__('No matching providers found for %s based on the selected filters.', 'olza-logistic-woo'), strtoupper($country));
                continue;
            }
        } else {
            $target_speditions = $available_speditions;
        }

        $find_api_endpoint = olza_validate_url($api_url . '/find');

        foreach ($target_speditions as $sped_value) {

            $find_api_url = add_query_arg(array(
                'access_token' => $access_token,
                'country' => $country,
                'spedition' => $sped_value,
            ), $find_api_endpoint);

            $find_args = array(
                'timeout'   => 300,
                'headers'   => array(
                    'Content-Type'  => 'application/json'
                )
            );

            $find_response = wp_remote_get($find_api_url, $find_args);

            if (is_wp_error($find_response)) {

                $errors[] = sprintf(
                    __('%1$s / %2$s: %3$s', 'olza-logistic-woo'),
                    strtoupper($country),
                    strtoupper($sped_value),
                    $find_response->get_error_message()
                );
                continue;
            }

            $find_data = wp_remote_retrieve_body($find_response);

            $sped_file_name = $country . '_' . $sped_value;
            $file_path = $data_dir . $sped_file_name . '.json';

            if (false === file_put_contents($file_path, $find_data)) {
                $errors[] = sprintf(
                    __('%1$s / %2$s: Unable to save pickup points file.', 'olza-logistic-woo'),
                    strtoupper($country),
                    strtoupper($sped_value)
                );
                continue;
            }

            $messages[] = sprintf(__('Pickup points for %1$s / %2$s downloaded.', 'olza-logistic-woo'), strtoupper($country), strtoupper($sped_value));
        }
    }

    $message_text = implode("\n", array_filter(array_merge($messages, $errors)));

    if (empty($message_text)) {
        $message_text = __('No pickup data updated.', 'olza-logistic-woo');
    }

    $response = array(
        'success' => empty($errors),
        'message' => $message_text
    );

    echo json_encode($response);
    wp_die();
}

function olza_sanitize_codes_list($codes)
{
    $sanitized = array();

    if (empty($codes)) {
        return $sanitized;
    }

    if (is_array($codes)) {
        $codes = implode(',', $codes);
    }

    $codes = strtolower((string) $codes);
    $parts = explode(',', $codes);

    foreach ($parts as $part) {
        $part = olza_normalize_code($part);
        if (!empty($part)) {
            $sanitized[] = $part;
        }
    }

    return array_values(array_unique($sanitized));
}

function olza_normalize_code($code)
{
    $code = strtolower((string) $code);
    $code = trim($code);
    $code = preg_replace('/[^a-z0-9\-_]/', '', $code);

    return $code;
}

function olza_clear_pickup_point_files($data_dir)
{
    if (!is_dir($data_dir)) {
        return;
    }

    $files = glob(trailingslashit($data_dir) . '*.json');

    if (empty($files)) {
        return;
    }

    foreach ($files as $file_path) {
        if (is_file($file_path)) {
            if (function_exists('wp_delete_file')) {
                wp_delete_file($file_path);
            } else {
                @unlink($file_path);
            }

            $response[$country_key][] = array(
                'code' => $code,
                'label' => $label,
            );
        }
    }
}

/**
 * APP Url Validation
 */

if (!function_exists('olza_validate_url')) {
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
}

/**
 * Add woo button field
 */


add_action('woocommerce_admin_field_button', 'olza_woo_add_admin_field_button');

function olza_woo_add_admin_field_button($value)
{
    $option_value = (array) WC_Admin_Settings::get_option($value['id']);
    $description = WC_Admin_Settings::get_field_description($value);

?>
    <style>
        .olza-admin-spinner {
            display: none;
        }
    </style>
    <tr valign="top">
        <th scope="row" class="titledesc">
            <label for="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['title']); ?></label>
            <?php echo $description['tooltip_html']; ?>
        </th>
        <td class="olza-table olza-table-<?php echo sanitize_title($value['type']) ?>">
            <input name="<?php echo esc_attr($value['name']); ?>" id="<?php echo esc_attr($value['id']); ?>" type="submit" style="<?php echo esc_attr($value['css']); ?>" value="<?php echo esc_attr($value['name']); ?>" class="<?php echo esc_attr($value['class']); ?>" />
            <?php echo $description['description']; ?>
            <span class="olza-admin-spinner"><img src="<?php echo OLZA_LOGISTIC_PLUGIN_URL . 'assets/images/spinner.gif'; ?>" alt="<?php echo __('Spinner', 'olza-logistic-woo'); ?>" /></span>
        </td>
    </tr>
<?php
}

/**
 * Add woo button field
 */


add_action('woocommerce_admin_field_repeater', 'olza_woo_add_admin_field_repeater');

function olza_woo_add_admin_field_repeater($field)
{
    $option_value = (array) WC_Admin_Settings::get_option($field['id']);
    $description = WC_Admin_Settings::get_field_description($field);
    $olza_options = get_option('olza_options');

?>
    <style>
        .olza-rep-sett input[type="number"] {
            width: 20% !important;
            min-height: 30px !important;
        }

        .olza-rep-sett select {
            width: 30% !important;
        }

        .olza-rep-item {
            margin: 10px 0;
        }
    </style>
    <tr valign="top" class="olza-rep-sett">
        <th scope="row" class="titledesc">
            <label for="<?php echo esc_attr($field['id']); ?>"><?php echo esc_html($field['title']); ?></label>
            <?php echo $description['tooltip_html']; ?>
        </th>
        <td class="olza-table olza-table-<?php echo sanitize_title($field['type']) ?>">
            <?php
            if (isset($olza_options[$field['key_val']]) && !empty($olza_options[$field['key_val']]) && is_array($olza_options[$field['key_val']])) {
            ?>
                <div class="olzrepeater">
                    <div data-repeater-list="<?php echo esc_attr($field['id']); ?>">
                        <?php
                        foreach ($olza_options[$field['key_val']] as $key => $backet_data) {
                            $cond_val = isset($backet_data['condition']) ? $backet_data['condition'] : '';
                        ?>
                            <div data-repeater-item class="olza-rep-item">
                                <input type="number" placeholder="<?php echo __('Basket Amount', 'olza-logistic-woo'); ?>" name="<?php echo esc_attr($field['id']); ?>[<?php echo $key; ?>][amount]" value="<?php echo isset($backet_data['amount']) ? $backet_data['amount'] : ''; ?>" />
                                <select name="<?php echo esc_attr($field['id']); ?>[<?php echo $key; ?>][condition]">
                                    <option value="equal" <?php selected($cond_val, 'equal', true); ?>><?php echo __('Equal', 'olza-logistic-woo'); ?></option>
                                    <option value="less" <?php selected($cond_val, 'less', true); ?>><?php echo __('Less', 'olza-logistic-woo'); ?></option>
                                    <option value="less_than_equal" <?php selected($cond_val, 'less_than_equal', true); ?>><?php echo __('Less than Equal', 'olza-logistic-woo'); ?></option>
                                    <option value="greater" <?php selected($cond_val, 'greater', true); ?>><?php echo __('Greater', 'olza-logistic-woo'); ?></option>
                                    <option value="greater_than_equal" <?php selected($cond_val, 'greater_than_equal', true); ?>><?php echo __('Greater than Equal', 'olza-logistic-woo'); ?></option>
                                </select>
                                <input type="number" placeholder="<?php echo __('Fee', 'olza-logistic-woo'); ?>" name="<?php echo esc_attr($field['id']); ?>[<?php echo $key; ?>][fee]" value="<?php echo isset($backet_data['fee']) ? $backet_data['fee'] : ''; ?>" />
                                <input data-repeater-delete type="button" value="<?php echo __('Delete', 'olza-logistic-woo'); ?>" class="button-secondary" />
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                    <input data-repeater-create type="button" value="<?php echo __('Add', 'olza-logistic-woo'); ?>" class="button-secondary" />
                </div>
            <?php
            } else {
            ?>
                <div class="olzrepeater">
                    <div data-repeater-list="<?php echo esc_attr($field['id']); ?>">
                        <div data-repeater-item>
                            <input type="number" name="amount" value="" placeholder="<?php echo __('Amount', 'olza-logistic-woo'); ?>" />
                            <select name="condition">
                                <option value="equal"><?php echo __('Equal', 'olza-logistic-woo'); ?></option>
                                <option value="less"><?php echo __('Less', 'olza-logistic-woo'); ?></option>
                                <option value="less_than_equal"><?php echo __('Less than Equal', 'olza-logistic-woo'); ?></option>
                                <option value="greater"><?php echo __('Greater', 'olza-logistic-woo'); ?></option>
                                <option value="greater_than_equal"><?php echo __('Greater than Equal', 'olza-logistic-woo'); ?></option>
                            </select>
                            <input type="number" name="fee" value="" placeholder="<?php echo __('Fee', 'olza-logistic-woo'); ?>" />
                            <input data-repeater-delete type="button" value="Delete" />
                        </div>
                    </div>
                    <input data-repeater-create type="button" value="Add" />
                </div>

            <?php
            }
            ?>
        </td>
    </tr>
<?php
}