<?php

/**
 * Plugin Name: Form Maker Stats

 * Description: This plugin is a add-on of Form Maker
 * Version: 1.0
 * Author: Peaceful
 */
if (!defined('ABSPATH')) {
    die();
}
define("FORMMAKER_TABLE", $wpdb->prefix . 'formmaker');
define("SUBMITS_TABLE", $wpdb->prefix . 'formmaker_submits');

add_action('wp_enqueue_scripts', 'fms_scripts');
add_shortcode('form-maker-stats', 'form_maker_stats');

function form_maker_stats($atts) {

    $params = shortcode_atts([
        'form-id' => -1,
        'group-by' => '',
        'stats-by' => '',
            ], $atts, 'form-maker-stats');
    $form_id = absint($params['form-id']);
    $group_by = $params['group-by'];
    $stats_by = array_map('trim', explode(',', $params['stats-by']));
    $ret = '';
    $submits = fms_get_submits($form_id, $group_by, $stats_by);
    $grouped = [];
    $counted = [];
    foreach ($submits as $item) {
        $grouped[$item[$group_by]][] = $item['Name'] ?? 'Név';
        foreach ($stats_by as $stat) {
            if (isset($counted[$stat][$item[$stat]])) {
                $counted[$stat][$item[$stat]]++;
            } else {
                $counted[$stat][$item[$stat]] = 1;
            }
        }
    }

    $ret .= '<table class="fms-table"><thead><tr>';
    foreach (array_keys($grouped) as $head) {
        $ret .= '<th>' . $head . '</th>';
    }
    $ret .= '</tr>';
    $line = '</tr></thead><tbody>';
    $number = 1;
    do {
        $ret .= $line;
        $found = false;
        $line = '<tr>';
        foreach (array_keys($grouped) as $head) {
            $name = array_shift($grouped[$head]);
            if (!empty($name)) {
                $found = true;
                $line .= '<td>'.$number.'. ' . $name . '</td>';
            } else {
                $line .= '<td></td>';
            }
        }
        $line .= '</tr>';
        $number++;
    } while ($found);
    $ret .= '</tbody></table>';
    $ret .= '<div class="fms-stats">';
    foreach ($stats_by as $stat) {
        $ret .= '<label>' . $stat . ':</label>';
        foreach ($counted[$stat] AS $key => $value) {
            $ret .= $key . ': ' . $value . ', ';
        }
        $ret .= '<br>';
    }
    $ret .= '</div>';
    return $ret;
}

function fms_scripts() {
    wp_enqueue_style('form-maker-stats-style', plugins_url('form-maker-stats.css', __FILE__));
}

function fms_get_submits($form_id, $group_by, $stats_by) {
    global $wpdb;
    $sql = $wpdb->prepare('SELECT * FROM `' . FORMMAKER_TABLE . '` WHERE `id`=%d;', $form_id);
    $res = $wpdb->get_results($sql);
    if (empty($res)) {
        return [];
    }
    $data_string = $res[0]->label_order;
    $entries = explode('#****#', trim($data_string, '#'));
    $field_ids = [];
    $target_fields = ['Name', $group_by];
    foreach ($stats_by as $stat) {
        $target_fields[] = $stat;
    }
    foreach ($entries as $entry) {
        $parts = explode('#', $entry);
        if (isset($parts[0]) AND isset($parts[2])) {
            $field_id = $parts[0];
            $field_name = $parts[2];
            if (in_array($field_name, $target_fields)) {
                $field_ids[$field_id] = $field_name;
            }
        }
    }
    $ret = [];
    $sql = $wpdb->prepare('SELECT * FROM `' . SUBMITS_TABLE . '` WHERE `form_id`=%d;', $form_id);
    $res = $wpdb->get_results($sql);
    if (empty($res)) {
        return [];
    }
    foreach ($res as $item) {
        if (isset($field_ids[$item->element_label]))
            $ret[$item->group_id][$field_ids[$item->element_label]] = str_replace('@', ' ', $item->element_value);
    }
    return $ret;
}
