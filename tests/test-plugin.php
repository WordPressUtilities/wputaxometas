<?php
class WPUTaxoMetas_Plugin extends WP_UnitTestCase {

    public $demo_plugin;

    function setUp() {
        parent::setUp();
    }

    // Test adding empty fields
    function test_add_empty_fields() {

        add_filter('wputaxometas_fields', 'test_add_empty_fields___set_wputaxometas_fields');
        function test_add_empty_fields___set_wputaxometas_fields($fields) {
            $fields['test_field'] = array();
            return $fields;
        }

        $demo_plugin = new WPUTaxoMetas;

        // Simulate WordPress init
        do_action('init');

        $this->assertEquals(1, count($demo_plugin->fields));
    }

    // Test adding fields
    function test_update_fields() {

        $user_id = $this->factory->user->create(array(
            'role' => 'administrator'
        ));
        wp_set_current_user($user_id);

        add_filter('wputaxometas_fields', 'test_update_fields___set_wputaxometas_fields');
        function test_update_fields___set_wputaxometas_fields($fields) {
            $fields['test_field'] = array(
                'label' => 'Test field',
                'taxonomies' => array(
                    'category'
                )
            );
            return $fields;
        }

        $demo_plugin = new WPUTaxoMetas();

        // Simulate WordPress init
        do_action('init');

        // Test if field has been successfully added
        $this->assertEquals(1, count($demo_plugin->fields));

        // Set metas for term #1
        $demo_plugin->update_metas_for_term(1, 'category', array(
            'test_field' => 'test123'
        ));

        $metas = get_taxonomy_metas(1);

        // Test if metas have been saved
        $this->assertEquals(1, count($metas));

        // Test if key exists
        $this->arrayHasKey('test_field', $metas);

        // Test if key value is correct
        $this->assertEquals('test123', $metas['test_field']);
    }

    function test_validation() {
        $demo_plugin = new WPUTaxoMetas;

        $field_default = array(
            'type' => '',
            'datas' => array(
                'a' => 'AA',
                'b' => 'BB'
            )
        );

        // Attachment
        $field_default['type'] = 'attachment';
        $this->assertEquals(1, $demo_plugin->validate_field($field_default, 1));
        $this->assertEquals(0, $demo_plugin->validate_field($field_default, 'az'));

        // Number
        $field_default['type'] = 'number';
        $this->assertEquals(1, $demo_plugin->validate_field($field_default, 1));
        $this->assertEquals(0, $demo_plugin->validate_field($field_default, 'az'));

        // Email
        $field_default['type'] = 'email';
        $this->assertEquals('test@yopmail.com', $demo_plugin->validate_field($field_default, 'test@yopmail.com'));
        $this->assertEquals('', $demo_plugin->validate_field($field_default, 'testayopmailcom'));

        // URL
        $field_default['type'] = 'url';
        $this->assertEquals('http://www.github.com', $demo_plugin->validate_field($field_default, 'http://www.github.com'));
        $this->assertEquals('', $demo_plugin->validate_field($field_default, 'testayopmailcom'));

        // Checkbox
        $field_default['type'] = 'checkbox';
        $this->assertEquals(1, $demo_plugin->validate_field($field_default, 1));
        $this->assertEquals(0, $demo_plugin->validate_field($field_default, 'az'));

        // Radio
        $field_default['type'] = 'radio';
        $this->assertEquals('a', $demo_plugin->validate_field($field_default, 'a'));
        $this->assertEquals('a', $demo_plugin->validate_field($field_default, 'abzb'));

        // Select
        $field_default['type'] = 'select';
        $this->assertEquals('a', $demo_plugin->validate_field($field_default, 'a'));
        $this->assertEquals('a', $demo_plugin->validate_field($field_default, 'abzb'));

        // Select
        $field_default['type'] = 'color';
        $this->assertEquals('#AABBCC', $demo_plugin->validate_field($field_default, '#AABBCC'));
        $this->assertEquals('#000000', $demo_plugin->validate_field($field_default, '#AZBAZB'));
    }

    function test_qtranslate() {
        global $q_config;
        if (!defined('QTX_VERSION')) {
            define('QTX_VERSION', '1');
        }
        $q_config = array(
            'language_name' => array(
                'fr' => 'France',
                'en' => 'English'
            ) ,
            'enabled_languages' => array(
                'fr',
                'en'
            )
        );
        $demo_plugin = new WPUTaxoMetas;
        $langs = $demo_plugin->get_languages();

        $this->assertTrue($demo_plugin->qtranslate);
        $this->assertEquals(2, count($langs));
    }
}
