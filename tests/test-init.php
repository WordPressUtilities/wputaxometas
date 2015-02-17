<?php

class WPUTaxoMetas_Init extends WP_UnitTestCase
{

    public $demo_plugin;

    function setUp() {
        parent::setUp();
        $this->demo_plugin = new WPUTaxoMetas;
    }

    function test_init_plugin() {
        // Simulate WordPress init
        do_action('init');
        $this->assertEquals(10, has_action('admin_enqueue_scripts', array(
            $this->demo_plugin,
            'load_assets'
        )));
    }
}
