<?php

class WPUTaxoMetas_Utilities extends WP_UnitTestCase
{

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
}
