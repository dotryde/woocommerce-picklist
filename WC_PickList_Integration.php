<?php
/**
 * Created by PhpStorm.
 * User: danielgoebel
 * Date: 28.03.17
 * Time: 13:44
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


if ( ! class_exists( 'WC_PickList_Integration' ) ) :

    class WC_PickList_Integration extends WC_Integration
    {

        /**
         * Init and hook in the integration.
         */
        public function __construct() {
            global $woocommerce;
            $this->id                 = 'picklist';
            $this->method_title       = __( 'PickList Settings', 'wc-picklist' );
            $this->method_description = __( 'PickList Settings', 'wc-picklist' );
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
            // Define user set variables.
            $this->picklist_partial          = $this->get_option( 'picklist_partial' );
            $this->picklist_autocomplete            = $this->get_option( 'picklist_autocomplete' );
            // Actions.
            add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
        }

        /**
         * Initialize integration settings form fields.
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                "picklist_partial" => array(
                    'title'             => __( 'Enable partial shipping', 'wc-picklist' ),
                    'type'              => 'checkbox',
                    'description'       => __( '', 'wc-picklist' ),
                    'desc_tip'          => true,
                    'default'           => ''
                ),
                "picklist_autocomplete" => array(
                    'title'             => __( 'Autocomplete order', 'wc-picklist' ),
                    'type'              => 'checkbox',
                    'description'       => __( 'Autocomplete Order when all items are fulfilled', 'wc-picklist' ),
                    'desc_tip'          => true,
                    'default'           => ''
                ),
            );
        }

    }

endif;