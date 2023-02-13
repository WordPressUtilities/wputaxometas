<?php

/*
Plugin Name: WPU Taxo Metas
Plugin URI: https://github.com/WordPressUtilities/wputaxometas
Update URI: https://github.com/WordPressUtilities/wputaxometas
Description: Simple admin for taxo metas
Version: 0.22.0
Author: Darklg
Author URI: https://darklg.me/
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

defined('ABSPATH') or die(':(');

class WPUTaxoMetas {
    public $plugin_version = '0.22.0';
    public $qtranslate = false;
    public $qtranslatex = false;
    public $fields = array();
    public $polylang = false;
    public $wpml = false;

    private $plugin_description;
    public $settings_update;
    public $taxonomies;

    public function __construct($hooks = true) {
        $this->set_options();
        $this->set_hooks();
        if ($hooks) {
            $this->set_admin_hooks();
        }
    }

    public function set_hooks() {

        /* Auto-updater */
        include dirname(__FILE__) . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
        $this->settings_update = new \wputaxometas\WPUBaseUpdate(
            'WordPressUtilities',
            'wputaxometas',
            $this->plugin_version);
    }

    public function set_admin_hooks() {

        // Load assets
        add_action('admin_enqueue_scripts', array(&$this,
            'load_assets'
        ));
        add_action('qtranslate_add_admin_footer_js', array(&$this,
            'load_assets_qtranslatex'
        ));

        $this->taxonomies = array();

        // Extract taxonomies
        foreach ($this->fields as $id => $field) {
            foreach ($field['taxonomies'] as $taxo) {
                $this->taxonomies[$taxo] = $taxo;
            }
        }

        // Add hook to edit category
        foreach ($this->taxonomies as $taxo) {
            $taxonomy = get_taxonomy($taxo);
            if (!is_object($taxonomy)) {
                continue;
            }

            add_filter('manage_edit-' . $taxo . '_columns', array(&$this,
                'column_title'
            ));
            add_filter('manage_edit-' . $taxo . '_sortable_columns', array(&$this,
                'column_title'
            ), 10, 3);
            add_filter('manage_' . $taxo . '_custom_column', array(&$this,
                'column_content'
            ), 10, 3);

            if (current_user_can($taxonomy->cap->edit_terms)) {
                add_action($taxo . '_edit_form_fields', array(&$this,
                    'extra_taxo_field_edit'
                ));
                add_action($taxo . '_add_form_fields', array(&$this,
                    'extra_taxo_field_add'
                ));
                add_action('created_term', array(&$this,
                    'default_values'
                ), 10, 3);
                add_action('edited_' . $taxo, array(&$this,
                    'save_extra_taxo_field'
                ));
                add_action('create_' . $taxo, array(&$this,
                    'save_extra_taxo_field'
                ));
            }
        }
    }

    public function default_values($term_id, $tt_id, $taxonomy) {
        foreach ($this->fields as $id => $field) {
            if (!isset($field['default'])) {
                continue;
            }
            foreach ($field['taxonomies'] as $taxo) {
                if ($taxo == $taxonomy) {
                    add_term_meta($term_id, $id, $field['default']);
                }
            }
        }
    }

    public function load_assets_qtranslatex() {
        wp_enqueue_script('wputaxometas_qtranslatex', plugins_url('/assets/qtranslatex.js', __FILE__), array(), $this->plugin_version, 1);
    }

    public function load_assets() {
        $screen = get_current_screen();
        if ($screen->base == 'edit-tags' || $screen->base == 'term') {
            wp_enqueue_media();
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_script('wputaxometas_scripts', plugins_url('/assets/global.js', __FILE__), array(), $this->plugin_version);
        }
        wp_enqueue_style('wputaxometas_style', plugins_url('assets/style.css', __FILE__));
    }

    public function save_extra_taxo_field($t_id) {
        if (!isset($_POST['term_meta']) || !isset($_POST['taxonomy'])) {
            return;
        }
        $nonce_key = isset($_POST['wpu-taxometas-term-' . $t_id]) ? $_POST['wpu-taxometas-term-' . $t_id] : '';
        $nonce_key = isset($_POST['wpu-taxometas-term-default']) ? $_POST['wpu-taxometas-term-default'] : $nonce_key;

        if (empty($nonce_key)) {
            return;
        }

        if (wp_verify_nonce($nonce_key, 'wpu-taxometas-term')) {
            $this->update_metas_for_term($t_id, $_POST['taxonomy'], $_POST['term_meta']);
        }

    }

    public function update_metas_for_term($t_id, $taxonomy, $metas) {

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

            // Check if field exists, and is in taxonomy
            if (isset($this->fields[$new_key]) && in_array($taxonomy, $this->fields[$new_key]['taxonomies'])) {
                $cat_meta[$key] = $this->validate_field($this->fields[$new_key], $var);
                if (function_exists('update_term_meta')) {
                    update_term_meta($t_id, $key, $cat_meta[$key]);
                }
            }
        }

        // Save the values in an option
        $update = true;
        if (!function_exists('update_term_meta')) {
            $update = update_option("wpu_taxometas_term_" . $t_id, $cat_meta);
        } else {
            delete_option("wpu_taxometas_term_" . $t_id);
        }
        return $update;
    }

    public function validate_field($field, $value) {
        $zeroone = array(
            '0',
            '1'
        );

        reset($field['datas']);
        $first_key = key($field['datas']);

        switch ($field['type']) {
        case 'attachment':
        case 'taxonomy':
        case 'post':
            return !is_numeric($value) ? false : $value;
            break;
        case 'number':
            return !is_numeric($value) ? 0 : $value;
            break;
        case 'email':
            return !filter_var($value, FILTER_VALIDATE_EMAIL) ? '' : $value;
            break;
        case 'url':
            return !filter_var($value, FILTER_VALIDATE_URL) ? '' : $value;
            break;
        case 'checkbox':
            return !in_array($value, $zeroone) ? '0' : $value;
            break;
        case 'radio':
        case 'select':
            if ($field['type'] == 'select' && $field['multiple']) {
                $array_value = array();

                # Old textual value
                if (!is_array($value) && array_key_exists($value, $field['datas'])) {
                    return array($value);
                }

                # New value
                if (is_array($value)) {
                    $array_value = array();
                    foreach ($value as $subvalue) {
                        if (array_key_exists($subvalue, $field['datas'])) {
                            $array_value[] = $subvalue;
                        }
                    }
                }

                # Defaults to base value
                if (empty($array_value)) {
                    return array($first_key);
                }

                return $array_value;

            } else {
                return !array_key_exists($value, $field['datas']) ? $first_key : $value;
            }
            break;
        case 'color':
            return !preg_match('/^#[A-Fa-f0-9]{6}$/i', $value) ? '#000000' : $value;
            break;
        }

        return $value;
    }

    public function extra_taxo_field_add($tax_name) {
        $languages = $this->get_languages();
        wp_nonce_field('wpu-taxometas-term', 'wpu-taxometas-term-default');
        foreach ($this->fields as $id => $field) {
            if (!in_array($tax_name, $field['taxonomies'])) {
                continue;
            }
            if (!$field['display_addform']) {
                continue;
            }

            $term_meta = array();
            if (!empty($languages) && isset($field['lang']) && $field['lang']) {
                $field_label = $field['label'];
                foreach ($languages as $id_lang => $language) {
                    $tmp_id = $id_lang . '__' . $id;
                    $field['label'] = $field_label;
                    if (!$this->qtranslatex) {
                        $field['label'] .= ' [' . $id_lang . ']';
                    }
                    $this->load_field_content($tmp_id, $field, $term_meta, $id_lang, 'add');
                }
            } else {
                $this->load_field_content($id, $field, $term_meta, false, 'add');
            }

        }
    }

    public function extra_taxo_field_edit($tax) {
        $t_id = $tax->term_id;
        $languages = $this->get_languages();
        echo '</table>';
        wp_nonce_field('wpu-taxometas-term', 'wpu-taxometas-term-' . $t_id);
        echo '<table class="form-table">';

        foreach ($this->fields as $id => $field) {
            if (in_array($tax->taxonomy, $field['taxonomies'])) {
                if (!empty($languages) && isset($field['lang']) && $field['lang']) {
                    $field_label = $field['label'];
                    foreach ($languages as $id_lang => $language) {
                        $tmp_id = $id_lang . '__' . $id;
                        $term_meta[$tmp_id] = wputaxometas_get_term_meta($t_id, $tmp_id, 1);
                        $field['label'] = $field_label;
                        if (!$this->qtranslatex) {
                            $field['label'] .= ' [' . $id_lang . ']';
                        }
                        $this->load_field_content($tmp_id, $field, $term_meta, $id_lang);
                    }
                } else {
                    $term_meta[$id] = wputaxometas_get_term_meta($t_id, $id, 1);
                    $this->load_field_content($id, $field, $term_meta);
                }
            }
        }
    }

    public function load_field_content($id, $field, $term_meta, $id_lang = false, $mode = 'edit') {

        if ($field['type'] == 'title') {
            echo '</table><h2>' . $field['label'] . '</h2><table class="form-table">';
            return;
        }

        echo apply_filters('wputaxometas__box_content__before_field_main', '', $id, $field, $term_meta, $id_lang, $mode);

        // Set value
        $value = '';
        if ($mode == 'edit' && isset($term_meta[$id])) {
            $value = stripslashes($term_meta[$id]);
        }

        // Set ID / Name
        $htmlname = 'term_meta[' . $id . ']';
        if ($field['type'] == 'select' && $field['multiple']) {
            $htmlname .= '[]';
        }
        $htmlid = 'term_meta_' . $id;
        $idname = 'name="' . $htmlname . '" id="' . $htmlid . '"';

        if ($field['type'] == 'select' && $field['multiple']) {
            $idname .= ' multiple="multiple"';
        }

        if ($field['required']) {
            $idname .= ' required="required"';
        }

        $label = '<label for="' . $htmlid . '">' . $field['label'] . ($field['required'] ? ' <b>*</b> ' : '') . '</label>';

        $before_field = apply_filters('wputaxometas__box_content__before_field', '', $id, $field, $term_meta, $id_lang, $mode);

        if ($mode == 'edit') {
            echo '<tr' . ($id_lang != false ? ' data-wputaxometaslang="' . $id_lang . '"' : '') . ' class="form-field wpu-taxometas-form"><th scope="row" style="vertical-align:top;">' . $label . '</th>';
            echo '<td>';
            echo $before_field;
        }

        if ($mode == 'add') {
            echo '<div class="form-field">';
            echo $before_field;
            echo $label;
        }

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
            foreach ($field['datas'] as $key => $var) {
                $is_selected = $key == $value;
                if (isset($term_meta[$id]) && is_array($term_meta[$id])) {
                    $is_selected = in_array($key, $term_meta[$id]);
                }
                echo '<option value="' . $key . '" ' . ($is_selected ? 'selected="selected"' : '') . '>' . $var . '</option>';
            }
            echo '</select>';
            break;
        case 'post':
            $lastposts = get_posts(array(
                'posts_per_page' => 100,
                'order' => 'ASC',
                'orderby' => 'title',
                'post_type' => (isset($field['post_type']) ? $field['post_type'] : 'post')
            ));
            if (!empty($lastposts)) {
                echo '<select ' . $idname . '>';
                if ($field['required']) {
                    echo '<option value="" disabled selected style="display:none;">' . __('Select a value', 'wputaxometas') . '</option>';
                } else {
                    echo '<option value="0">' . __('Select a value', 'wputaxometas') . '</option>';
                }
                foreach ($lastposts as $post) {
                    echo '<option value="' . $post->ID . '" ' . ($post->ID == $value ? 'selected="selected"' : '') . '>' . $post->post_title . '</option>';
                }
                echo '</select>';
            }
            break;
        case 'taxonomy':
            $allterms = get_terms(array(
                'taxonomy' => (isset($field['taxonomy_type']) ? $field['taxonomy_type'] : 'category'),
                'hide_empty' => false,
                'orderby' => 'name'
            ));
            if (!empty($allterms)) {
                echo '<select ' . $idname . '>';
                if ($field['required']) {
                    echo '<option value="" disabled selected style="display:none;">' . __('Select a value', 'wputaxometas') . '</option>';
                } else {
                    echo '<option value="0">' . __('Select a value', 'wputaxometas') . '</option>';
                }
                foreach ($allterms as $term) {
                    echo '<option value="' . $term->term_id . '" ' . ($term->term_id == $value ? 'selected="selected"' : '') . '>' . $term->name . '</option>';
                }
                echo '</select>';
            }

            break;
        case 'radio':
            foreach ($field['datas'] as $key => $var) {
                echo '<label class="wpu-taxometas-input-radio"><input type="radio" name="' . $htmlname . '" value="' . $key . '" ' . ($key == $value ? 'checked="checked"' : '') . ' /> ' . $var . '</label>';
            }
            break;
        case 'checkbox':
            echo '<label><input type="hidden" ' . $idname . ' value="' . esc_attr($value) . '" /><input class="wpu-taxometas-input-checkbox" type="checkbox" ' . checked($value, '1', false) . ' value="1"> ' . $field['long_label'] . '</label>';
            break;
        case 'textarea':
            echo '<textarea ' . ($id_lang != false ? 'class="large-text qtranxs-translatable"' : '') . ' rows="5" cols="50" ' . $idname . '>' . esc_textarea($value) . '</textarea>';
            break;
        case 'color':
        case 'datetime-local':
        case 'date':
        case 'email':
        case 'number':
        case 'url':
            echo '<input ' . ($id_lang != false ? 'class="qtranxs-translatable"' : '') . ' type="' . $field['type'] . '" ' . $idname . ' value="' . esc_attr($value) . '">';
            break;
        default:
            echo '<input ' . ($id_lang != false ? 'class="qtranxs-translatable"' : '') . ' type="text" ' . $idname . ' value="' . esc_attr($value) . '">';
        }

        if (isset($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }

        echo apply_filters('wputaxometas__box_content__after_field', '', $id, $field, $term_meta, $id_lang, $mode);

        if ($mode == 'edit') {
            echo '</td></tr>';
        }

        if ($mode == 'add') {
            echo '</div>';
        }
        echo apply_filters('wputaxometas__box_content__after_field_main', '', $id, $field, $term_meta, $id_lang, $mode);
    }

    public function get_column_taxonomy($current_filter) {
        $to_replace = array(
            'manage_edit-',
            'manage_',
            '_sortable_columns',
            '_columns',
            '_custom_column'
        );
        return str_replace($to_replace, '', $current_filter);
    }

    public function column_title($columns) {
        $current_taxonomy = $this->get_column_taxonomy(current_filter());
        foreach ($this->fields as $id => $field) {
            if (isset($field['taxonomies'], $field['column']) && in_array($current_taxonomy, $field['taxonomies']) && $field['column']) {
                $columns[$id] = $field['label'];
            }
        }
        return $columns;
    }

    public function column_content($deprecated, $column_name, $term_id) {
        $languages = $this->get_languages();
        $current_taxonomy = $this->get_column_taxonomy(current_filter());
        foreach ($this->fields as $id => $field) {
            if (!isset($field['taxonomies'], $field['column']) || !in_array($current_taxonomy, $field['taxonomies']) || $column_name != $id) {
                continue;
            }
            if (isset($field['lang'])) {
                $tmp_values = array();
                foreach ($languages as $id_lang => $lang) {
                    $tmp_value = $this->display_meta_content($field, $term_id, $id_lang . '__' . $column_name);
                    if (!empty($tmp_value)) {
                        $tmp_values[] = '<strong>' . $id_lang . '</strong> : ' . $tmp_value;
                    }
                }
                echo implode('<hr class="wputaxometas-hr" />', $tmp_values);
            } else {
                echo $this->display_meta_content($field, $term_id, $column_name);
            }
            return;
        }
    }

    public function display_meta_content($field, $term_id, $column_name) {
        $max_chars = 50;

        $value = wputaxometas_get_term_meta($term_id, $column_name, 1);
        $valid_value = $this->validate_field($field, $value);
        if ($value != '0' && empty($value)) {
            return $value;
        }

        // If validate value is correct
        if ($valid_value == $value) {
            switch ($field['type']) {
            case 'select':
            case 'checkbox':
            case 'radio':
                return $field['datas'][$value];
                break;
            case 'attachment':
                $image = wp_get_attachment_image_src($value, 'thumbnail');
                if (isset($image[0])) {
                    return '<img class="wputaxometas-col-img" src="' . $image[0] . '" alt="" />';
                }
                break;
            case 'color':
                return '<span class="wputaxometas-col-color" style="background-color:' . $value . '"></span>';
                break;
            }
        }

        $value = strip_tags($value);
        if (strlen($value) > $max_chars + 3) {
            $value = substr($value, 0, $max_chars) . '...';
        }
        return $value;
    }

    public function set_options() {

        // Get Fields
        $this->fields = apply_filters('wputaxometas_fields', array());

        $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
        if (!load_plugin_textdomain('wputaxometas', false, $lang_dir)) {
            load_muplugin_textdomain('wputaxometas', $lang_dir);
        }
        $this->plugin_description = __('Simple admin for taxo metas', 'wputaxometas');

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

            // Required field
            if (!isset($field['required'])) {
                $this->fields[$id]['required'] = false;
            }

            // Multiple attribute
            if (!isset($field['multiple'])) {
                $this->fields[$id]['multiple'] = false;
            }

            // Set default label
            if (!isset($field['label'])) {
                $this->fields[$id]['label'] = ucwords($id);
            }

            if (!isset($field['long_label'])) {
                $this->fields[$id]['long_label'] = $this->fields[$id]['label'];
            }

            // Set default type
            if (!isset($field['type'])) {
                $this->fields[$id]['type'] = 'text';
            }

            // Default datas
            if (!isset($field['datas']) || $field['type'] == 'checkbox') {
                $this->fields[$id]['datas'] = array(
                    __('No', 'wputaxometas'),
                    __('Yes', 'wputaxometas')
                );
            }

            if (!isset($field['display_addform'])) {
                $this->fields[$id]['display_addform'] = false;
            }
        }
    }

    public function get_languages() {
        global $q_config, $polylang;
        $languages = array();

        // Obtaining from Qtranslate
        if (isset($q_config['enabled_languages'])) {
            $this->qtranslate = true;
            if (defined('QTX_VERSION')) {
                $this->qtranslatex = true;
            }
            foreach ($q_config['enabled_languages'] as $lang) {
                if (!in_array($lang, $languages) && isset($q_config['language_name'][$lang])) {
                    $languages[$lang] = $q_config['language_name'][$lang];
                }
            }
        }

        // Obtaining from Polylang
        if (function_exists('pll_the_languages') && is_object($polylang)) {
            $this->polylang = true;
            $poly_langs = $polylang->model->get_languages_list();
            foreach ($poly_langs as $lang) {
                $languages[$lang->slug] = $lang->name;
            }
        }

        // Obtaining from WPML
        if (!function_exists('pll_the_languages') && function_exists('icl_get_languages')) {
            $this->wpml = true;
            $wpml_lang = icl_get_languages();
            foreach ($wpml_lang as $lang) {
                $languages[$lang['code']] = $lang['native_name'];
            }
        }

        return $languages;
    }
}

$WPUTaxoMetas = false;
add_action('init', 'init_WPUTaxoMetas');
function init_WPUTaxoMetas() {
    global $WPUTaxoMetas;
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
    if (empty($metas) && function_exists('get_term_meta')) {
        $metas = wputax_get_term_metas_built($t_id);
    }
    return $metas;
}

function wputax_get_term_metas_built($t_id) {
    $metas = array();
    $term = get_term($t_id);
    if (!is_object($term) || !isset($term->taxonomy)) {
        return;
    }
    global $WPUTaxoMetas;
    if (!is_object($WPUTaxoMetas)) {
        $WPUTaxoMetas = new WPUTaxoMetas();
    }
    foreach ($WPUTaxoMetas->fields as $key => $field) {
        if (in_array($term->taxonomy, $field['taxonomies'])) {
            $metas[$key] = get_term_meta($t_id, $key, 1);
        }
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
