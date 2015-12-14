<?php

/*
Plugin Name: WPU Taxo Metas
Plugin URI: http://github.com/Darklg/WPUtilities
Description: Simple admin for taxo metas
Version: 0.9
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUTaxoMetas {
    function __construct($hooks = true) {
        $this->set_options();
        if ($hooks) {
            $this->set_admin_hooks();
        }
    }

    function set_admin_hooks() {

        // Load assets
        add_action('admin_enqueue_scripts', array(&$this,
            'load_assets'
        ));

        $taxonomies = array();

        // Extract taxonomies
        foreach ($this->fields as $id => $field) {
            foreach ($field['taxonomies'] as $taxo) {
                $taxonomies[$taxo] = $taxo;
            }
        }

        // Add hook to edit category
        foreach ($taxonomies as $taxo) {
            $taxonomy = get_taxonomy($taxo);
            if (current_user_can($taxonomy->cap->edit_terms)) {
                add_action($taxo . '_edit_form_fields', array(&$this,
                    'extra_taxo_field'
                ));
                add_action('edited_' . $taxo, array(&$this,
                    'save_extra_taxo_field'
                ));
                add_filter('manage_edit-' . $taxo . '_columns', array(&$this,
                    'column_title'
                ));
                add_filter('manage_' . $taxo . '_custom_column', array(&$this,
                    'column_content'
                ) , 10, 3);
            }
        }
    }

    function load_assets() {
        $screen = get_current_screen();
        if ($screen->base == 'edit-tags') {
            wp_enqueue_media();
            wp_enqueue_script('wputaxometas_scripts', plugins_url('/assets/global.js', __FILE__));
            wp_enqueue_style('wputaxometas_style', plugins_url('assets/style.css', __FILE__));
        }
    }

    function save_extra_taxo_field($t_id) {
        if (isset($_POST['term_meta']) && isset($_POST['taxonomy']) && wp_verify_nonce($_POST['wpu-taxometas-term-' . $t_id], 'wpu-taxometas-term')) {
            $this->update_metas_for_term($t_id, $_POST['taxonomy'], $_POST['term_meta']);
        }
    }

    function update_metas_for_term($t_id, $taxonomy, $metas) {

        // No values sent
        if (empty($metas)) {
            return false;
        }

        // Term does not exists
        if (!term_exists($t_id, $taxonomy)) {
            return false;
        }

        // Get previous values
        $cat_meta = wputax_get_term_metas($t_id);
        $languages = $this->get_languages();

        foreach ($metas as $key => $var) {
            $new_key = $key;
            foreach ($languages as $id_lang => $lang) {
                $new_key = str_replace($id_lang . '__', '', $new_key);
            }

            // Check if field exists, and is in taxonomies
            if (isset($this->fields[$new_key]) && in_array($taxonomy, $this->fields[$new_key]['taxonomies'])) {
                $cat_meta[$key] = $var;
                if (function_exists('update_term_meta')) {
                    update_term_meta($t_id, $key, $var);
                }
            }
        }

        // Save the values in an option
        $update = true;
        if (!function_exists('update_term_meta')) {
            $update = update_option("wpu_taxometas_term_" . $t_id, $cat_meta);
        }
        else {
            delete_option("wpu_taxometas_term_" . $t_id);
        }
        return $update;
    }

    function extra_taxo_field($tag) {
        $t_id = $tag->term_id;
        $languages = $this->get_languages();
        wp_nonce_field('wpu-taxometas-term', 'wpu-taxometas-term-' . $t_id);

        foreach ($this->fields as $id => $field) {
            if (in_array($tag->taxonomy, $field['taxonomies'])) {
                if (!empty($languages) && isset($field['lang']) && $field['lang']) {
                    $field_label = $field['label'];
                    foreach ($languages as $id_lang => $language) {
                        $tmp_id = $id_lang . '__' . $id;
                        $term_meta[$tmp_id] = wputaxometas_get_term_meta($t_id, $tmp_id, 1);
                        $field['label'] = $field_label . ' [' . $id_lang . ']';
                        $this->load_field_content($tmp_id, $field, $term_meta);
                    }
                }
                else {
                    $term_meta[$id] = wputaxometas_get_term_meta($t_id, $id, 1);
                    $this->load_field_content($id, $field, $term_meta);
                }
            }
        }
    }

    function load_field_content($id, $field, $term_meta) {

        // Set value
        $value = '';
        if (isset($term_meta[$id])) {
            $value = stripslashes($term_meta[$id]);
        }
        $field_datas = array(
            __('Yes', 'wputaxometas') ,
            __('No', 'wputaxometas')
        );
        if (isset($field['datas'])) {
            $field_datas = $field['datas'];
        }

        // Set ID / Name
        $htmlname = 'term_meta[' . $id . ']';
        $htmlid = 'term_meta_' . $id;
        $idname = 'name="' . $htmlname . '" id="' . $htmlid . '"';

        echo '<tr class="form-field wpu-taxometas-form"><th scope="row" valign="top"><label for="' . $htmlid . '">' . $field['label'] . '</label></th>';
        echo '<td>';
        switch ($field['type']) {
            case 'attachment':
                $img = '';
                $btn_label = __('Add a picture', 'wputaxometas');
                $btn_base_label = $btn_label;
                $btn_edit_label = __('Change this picture', 'wputaxometas');
                if (is_numeric($value)) {
                    $image = wp_get_attachment_image_src($value, 'big');
                    if (isset($image[0])) {
                        $img = '<img class="wpu-taxometas-upload-preview" src="' . $image[0] . '" alt="" /><span data-for="' . $htmlid . '" class="x">&times;</span>';
                        $btn_label = $btn_edit_label;
                    }
                }
                echo '<div data-baselabel="' . esc_attr($btn_base_label) . '" data-label="' . esc_attr($btn_edit_label) . '" class="wpu-taxometas-upload-wrap" id="preview-' . $htmlid . '">' . $img . '</div>';
                echo '<a href="#" data-for="' . $htmlid . '" class="button button-small wputaxometas_add_media">' . $btn_label . '</a>';
                echo '<input type="hidden" ' . $idname . ' value="' . $value . '" />';
            break;
            case 'editor':
                wp_editor($value, $htmlid, array(
                    'textarea_name' => $htmlname,
                    'textarea_rows' => 5
                ));
            break;
            case 'select':
                echo '<select ' . $idname . '>';
                echo '<option value="" disabled selected style="display:none;">' . __('Select a value', 'wputaxometas') . '</option>';
                foreach ($field_datas as $key => $var) {
                    echo '<option value="' . $key . '" ' . ((string)$key === (string)$value ? 'selected="selected"' : '') . '>' . $var . '</option>';
                }
                echo '</select>';
            break;
            case 'textarea':
                echo '<textarea rows="5" cols="50" ' . $idname . '>' . esc_textarea($value) . '</textarea>';
            break;
            case 'color':
            case 'date':
            case 'email':
            case 'number':
            case 'url':
                echo '<input type="' . $field['type'] . '" ' . $idname . ' value="' . esc_attr($value) . '">';
            break;
            default:
                echo '<input type="text" ' . $idname . ' value="' . esc_attr($value) . '">';
        }
        if (isset($field['description'])) {
            echo '<br /><span class="description">' . esc_html($field['description']) . '</span>';
        }
        echo '</td></tr>';
    }

    function column_title($columns) {
        $screen = get_current_screen();
        foreach ($this->fields as $id => $field) {
            if (in_array($screen->taxonomy, $field['taxonomies']) && $field['column']) {
                $columns[$id] = $field['label'];
            }
        }
        return $columns;
    }

    function column_content($deprecated, $column_name, $term_id) {
        $screen = get_current_screen();
        foreach ($this->fields as $id => $field) {
            if (in_array($screen->taxonomy, $field['taxonomies']) && $column_name == $id && $field['column']) {
                $value = wputaxometas_get_term_meta($term_id, $column_name, 1);
                switch ($field['type']) {
                    case 'textarea':
                        if (strlen($value) > 53) {
                            $value = substr($value, 0, 50) . '...';
                        }
                        echo strip_tags($value);
                        return;
                    break;
                    default:
                        echo strip_tags($value);
                        return;
                }
            }
        }
    }


    function set_options() {

        // Get Fields
        $this->fields = apply_filters('wputaxometas_fields', array());

        load_plugin_textdomain('wputaxometas', false, dirname(plugin_basename(__FILE__)) . '/lang/');

        // Fix Fields
        foreach ($this->fields as $id => $field) {

            // Set field type
            if (!is_array($field)) {
                $this->fields[$id] = array();
            }

            // Set default taxonomies
            if (!isset($field['taxonomies'])) {
                $this->fields[$id]['taxonomies'] = array(
                    'category'
                );
            }

            // Set column visibility
            if (!isset($field['column'])) {
                $this->fields[$id]['column'] = false;
            }

            // Set default label
            if (!isset($field['label'])) {
                $this->fields[$id]['label'] = ucwords($id);
            }

            // Set default type
            if (!isset($field['type'])) {
                $this->fields[$id]['type'] = 'text';
            }
        }
    }

    private function get_languages() {
        global $q_config, $polylang;
        $languages = array();

        // Obtaining from Qtranslate
        if (isset($q_config['enabled_languages'])) {
            foreach ($q_config['enabled_languages'] as $lang) {
                if (!in_array($lang, $languages) && isset($q_config['language_name'][$lang])) {
                    $languages[$lang] = $q_config['language_name'][$lang];
                }
            }
        }

        // Obtaining from Polylang
        if (function_exists('pll_the_languages') && is_object($polylang)) {
            $poly_langs = $polylang->model->get_languages_list();
            foreach ($poly_langs as $lang) {
                $languages[$lang->slug] = $lang->name;
            }
        }
        return $languages;
    }
}

add_action('init', 'init_WPUTaxoMetas');
function init_WPUTaxoMetas() {
    $WPUTaxoMetas = new WPUTaxoMetas();
}

function get_taxonomy_metas($t_id) {
    return wputax_get_term_metas($t_id);
}

function wputax_get_term_metas($t_id) {
    $metas = get_option("wpu_taxometas_term_" . $t_id);
    if (!is_array($metas)) {
        $metas = array();
    }
    return $metas;
}

function wputaxometas_get_term_meta($t_id, $key, $single) {
    $return = '';
    if (function_exists('get_term_meta')) {
        $return = get_term_meta($t_id, $key, $single);
    }
    if (!$return) {
        $metas = wputax_get_term_metas($t_id);
        $return = isset($metas[$key]) ? $metas[$key] : false;
    }
    return $return;
}
