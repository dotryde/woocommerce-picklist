<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/*
Plugin Name: PickList for WooCommerce
Plugin URI:  https://www.picklist.pro
Description: PickList for WooCommerce - Order processing on steroids
Version:     1.0
Author:      dotry UG (haftungsbeschränkt)
Author URI:  https://www.dotry.de
Text Domain: wporg
Domain Path: /languages
License:     GPL2

PickList for WooCommerce is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

PickList for WooCommerce is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with PickList for WooCommerce. If not, see http://www.gnu.org/licenses/.
*/

if ( ! class_exists( 'WC_PickList' ) ) :

    class WC_PickList
    {

        public function __construct() {

            add_action( 'plugins_loaded', array( $this, 'init' ) );
            add_action( 'admin_enqueue_scripts', array($this,'add_admin_style'));

        }

        public function init() {
            if  (class_exists('WC_Integration')){
                include_once 'WC_PickList_Integration.php';
                add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
                include_once 'WC_PickList_API.php';
                $WC_PickList_API = new WC_PickList_API();
            }

        }

        public function add_integration( $integrations ){
            $integrations[] = 'WC_PickList_Integration';
            return $integrations;

        }

        public function  add_admin_style() {
            wp_enqueue_style('admin-styles', plugin_dir_url( __FILE__ ).'css/admin.css');
        }



    }

    $WC_PickList = new WC_PickList( __FILE__ );


endif;
