<?php
class WPUTaxoMetas_Plugin extends WP_UnitTestCase
{

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

        $user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $user_id );

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

        $demo_plugin = new WPUTaxoMetas;

        // Simulate WordPress init
        do_action('init');

        // Test if field has been successfully added
        $this->assertEquals(1, count($demo_plugin->fields));

        // Set metas for term #1
        $demo_plugin->update_metas_for_term(1 , 'category', array(
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
}
