<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('olza_normalize_code')) {
    function olza_normalize_code($code)
    {
        $code = strtolower((string) $code);
        $code = trim($code);
        $code = preg_replace('/[^a-z0-9\-_]/', '', $code);

        return $code;
    }
}

if (!function_exists('olza_sanitize_codes_list')) {
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
}

if (!function_exists('olza_extract_speditions_from_config')) {
    function olza_extract_speditions_from_config($config)
    {
        $result = array();

        if (!is_object($config)) {
            return $result;
        }

        if (!isset($config->data) || !isset($config->data->speditions)) {
            return $result;
        }

        $speditions = $config->data->speditions;

        if (is_object($speditions)) {
            $speditions = get_object_vars($speditions);
        }

        if (!is_array($speditions)) {
            return $result;
        }

        foreach ($speditions as $key => $spedition_data) {
            $code = '';
            $label = '';

            if (is_object($spedition_data)) {
                if (isset($spedition_data->code)) {
                    $code = $spedition_data->code;
                }

                if (isset($spedition_data->name)) {
                    $label = $spedition_data->name;
                } elseif (isset($spedition_data->title)) {
                    $label = $spedition_data->title;
                } elseif (isset($spedition_data->label)) {
                    $label = $spedition_data->label;
                } elseif (isset($spedition_data->names) && is_array($spedition_data->names) && !empty($spedition_data->names)) {
                    $first_name = reset($spedition_data->names);
                    if (is_string($first_name)) {
                        $label = $first_name;
                    }
                }
            } elseif (is_array($spedition_data)) {
                if (isset($spedition_data['code'])) {
                    $code = $spedition_data['code'];
                }
                if (isset($spedition_data['name'])) {
                    $label = $spedition_data['name'];
                } elseif (isset($spedition_data['title'])) {
                    $label = $spedition_data['title'];
                } elseif (isset($spedition_data['label'])) {
                    $label = $spedition_data['label'];
                }
            } elseif (is_string($spedition_data)) {
                $code = $spedition_data;
            }

            if (empty($code) && is_string($key)) {
                $code = $key;
            }

            $code = olza_normalize_code($code);

            if (empty($code)) {
                continue;
            }

            if (empty($label) && is_string($key) && olza_normalize_code($key) !== $code) {
                $label = $key;
            }

            if (empty($label)) {
                $label = strtoupper($code);
            }

            $result[$code] = array(
                'code' => $code,
                'label' => $label,
            );
        }

        return $result;
    }
}
