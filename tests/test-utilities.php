<?php
class WPUTaxoMetas_Utilities extends WP_UnitTestCase {

    public $demo_plugin;

    function setUp() {
        parent::setUp();
        $this->demo_plugin = new WPUTaxoMetas;
        do_action('init');
    }

    function test_empty_taxonomy_metas() {
        $metas = get_taxonomy_metas(false);
        $this->assertEquals(0, count($metas));
    }

    function test_wputaxometas_get_term_meta() {
        add_filter('wputaxometas_fields', 'test_utilities___set_wputaxometas_fields');
        function test_utilities___set_wputaxometas_fields($fields) {
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

        // Set metas for term #1
        $demo_plugin->update_metas_for_term(1 , 'category', array(
            'test_field' => 'test123'
        ));

        $val = wputaxometas_get_term_meta(1, 'test_field', 1);
        $this->assertEquals('test123', $val);

    }
}
