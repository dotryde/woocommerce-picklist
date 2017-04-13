<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_PickList_Settings' ) ) :

    class WC_PickList_Settings
    {

        /**
         * Init and hook in the integration.
         */
        public function __construct() {

            // Actions.

            add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_settings_tab' ), 50 );
            add_action( 'woocommerce_settings_tabs_picklist_settings', array( __CLASS__, 'add_settings_tab_content' ) );
            add_action( 'woocommerce_update_options_picklist_settings', array( __CLASS__, 'update_settings_tab_content' ) );

        }


        public static function add_settings_tab( $settings_tabs ) {
            $settings_tabs['picklist_settings'] = __( 'PickList', 'wc-picklist' );
            return $settings_tabs;
        }

        public static function add_settings_tab_content () {
            woocommerce_admin_fields( self::picklist_settings_content() );
        }

        public static function update_settings_tab_content() {
            woocommerce_update_options( self::picklist_settings_content()  );
        }


        private static function picklist_settings_content() {
            $settings = array(
                'section_title' => array(
                    'name'     => __( 'PickList', 'woocommerce-picklist_settings' ),
                    'type'     => 'title',
                    'desc'     => 'Settings for PickList for WooCommerce. For more information see <a href="https://www.picklist.pro" target="_blank">https://www.picklist.pro</a>',
                    'id'       => 'wc_settings_picklist_settings_section_title'
                ),
                "picklist_partial" => array(
                    'id'                => 'picklist_partial',
                    'title'             => __( 'Enable partial shipping', 'wc-picklist' ),
                    'type'              => 'checkbox',
                    'desc'       => __( 'Ship an order with missing items.', 'wc-picklist' ),
                ),
                "picklist_autocomplete" => array(
                    'id'                => 'picklist_autocomplete',
                    'title'             => __( 'Autocomplete order', 'wc-picklist' ),
                    'type'              => 'checkbox',
                    'desc'       => __( 'Automatically set on order to "completed" when all items are fulfilled', 'wc-picklist' ),
                ),
                "picklist_swipeconfirm" => array(
                    'id'                => 'picklist_swipeconfirm',
                    'title'             => __( "Allow swipe confirmation", 'wc-picklist' ),
                    'type'              => 'checkbox',
                    'desc'       => __( 'Allow confirmation of an item by swiping instead of scanning the item\'s barcode. ', 'wc-picklist' ),
                ),
                'section_end' => array(
                    'type' => 'sectionend',
                    'id' => 'wc_settings_tab_picklist_section_end'
                )
            );

            return apply_filters( 'wc_settings_picklist_settings', $settings );
        }

    }

endif;