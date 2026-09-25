<?php

/** Minimal WordPress function surface required by class-wpdb.php in tests. */

if (!isset($GLOBALS['plasma_test_filters'])) {
    $GLOBALS['plasma_test_filters'] = [];
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $callback, $priority = 10, $acceptedArgs = 1)
    {
        $GLOBALS['plasma_test_filters'][$tag][$priority][] = [$callback, $acceptedArgs];
        return true;
    }
}

if (!function_exists('has_filter')) {
    function has_filter($tag, $callback = false)
    {
        if (empty($GLOBALS['plasma_test_filters'][$tag])) {
            return false;
        }
        foreach ($GLOBALS['plasma_test_filters'][$tag] as $priority => $callbacks) {
            foreach ($callbacks as $entry) {
                if ($callback === false || $entry[0] === $callback) {
                    return $priority;
                }
            }
        }
        return false;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args)
    {
        if (empty($GLOBALS['plasma_test_filters'][$tag])) {
            return $value;
        }
        ksort($GLOBALS['plasma_test_filters'][$tag]);
        foreach ($GLOBALS['plasma_test_filters'][$tag] as $callbacks) {
            foreach ($callbacks as $entry) {
                $parameters = array_merge([$value], $args);
                $value = call_user_func_array(
                    $entry[0],
                    array_slice($parameters, 0, $entry[1])
                );
            }
        }
        return $value;
    }
}

if (!function_exists('absint')) {
    function absint($value) { return abs((int) $value); }
}
if (!function_exists('__')) {
    function __($text) { return $text; }
}
if (!function_exists('wp_load_translations_early')) {
    function wp_load_translations_early() {}
}
if (!function_exists('_deprecated_function')) {
    function _deprecated_function() {}
}
if (!function_exists('is_multisite')) {
    function is_multisite() { return false; }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) { return false; }
}
if (!function_exists('wp_die')) {
    function wp_die($message = '') { throw new RuntimeException((string) $message); }
}
if (!function_exists('mbstring_binary_safe_encoding')) {
    function mbstring_binary_safe_encoding($reset = false) {}
}
if (!function_exists('reset_mbstring_encoding')) {
    function reset_mbstring_encoding() {}
}
