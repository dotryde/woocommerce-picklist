<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/*
Plugin Name: PickList for WooCommerce
Plugin URI:  https://www.picklist.pro
Description: PickList for WooCommerce - Order Processing on Steroids
Version:     1.1
Author:      dotry UG (haftungsbeschränkt)
Author URI:  https://www.dotry.de
Text Domain: picklist
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

if (!class_exists('WC_PickList')) :

    class WC_PickList
    {

        public function __construct()
        {

            add_action('plugins_loaded', array($this, 'init'));
            add_action('admin_enqueue_scripts', array($this, 'add_admin_style'));

            add_filter('woocommerce_hidden_order_itemmeta', array($this, 'remove_order_item_meta_fields'));

            add_filter('manage_shop_order_posts_columns', array($this, 'add_woocomerce_picklist_column'), 11);
            add_filter('manage_shop_order_posts_custom_column', array($this, 'add_woocomerce_picklist_column_row'), 10, 3);

            add_action('woocommerce_after_order_itemmeta', array($this, 'action_woocommerce_order_item_type_html'), 10, 3);

        }

        public function init()
        {
            if (class_exists('WC_Integration')) {
                include_once 'WC_PickList_Settings.php';
                $WC_PickList_Settings = new WC_PickList_Settings();
                include_once 'WC_PickList_API.php';
                $WC_PickList_API = new WC_PickList_API();
            }

        }

        public function add_admin_style()
        {
            wp_enqueue_style('admin-styles', plugin_dir_url(__FILE__) . 'css/admin.css');
        }

        public function remove_order_item_meta_fields($fields)
        {
            $fields[] = '_picklist_shipped';

            return $fields;
        }

        public function add_woocomerce_picklist_column($column)
        {
            $column["picklist"] = "Fulfillment";
            return $column;
        }

        public function add_woocomerce_picklist_column_row($column_name, $id)
        {

            switch ($column_name) {
                case 'picklist' :
                    echo $this->getPickListItemsFormatted($id);
                    break;
                default:
            }
            return $column_name;
        }

        private function getPickListItemsFormatted($order_id)
        {

            $WC_PickList_API = new WC_PickList_API();

            $order = $WC_PickList_API->getOrder($order_id);

            $qty_open = $order["qty_open"];
            $qty_shipped = $order["qty_shipped"];
            $qty_ordered_actual = $order["qty_ordered_actual"];

            $qty_total = $qty_open + $qty_shipped;
            $percent = 100 / $qty_total * $qty_shipped;

            $status = "normal";
            if ($qty_open == 0) {
                $status = "done";
            } else if ($qty_shipped == 0) {
                $status = "open";
            } else if ($qty_shipped > 0) {
                $status = "waiting";
            }

            echo '<div class="' . $status . '">';

            if ($status == "done") {
                echo "shipped";
            } else if ($status == "open") {
                echo "unfulfilled";
            } else {
                echo "$qty_shipped / $qty_total";
            }


            echo '</div>';

        }

        public function action_woocommerce_order_item_type_html($item_id, $item, $product)
        {

            $WC_Order = new WC_Order(get_the_ID());

            $qty_ordered = abs($item['qty']);
            $qty_refunded = abs($WC_Order->get_qty_refunded_for_item($item_id));
            $qty_shipped = array_sum(wc_get_order_item_meta($item_id, '_picklist_shipped', false));

            $qty_open = $qty_ordered - $qty_refunded - $qty_shipped;

            if ($qty_open > 0) {
                echo '<span class="picklist picklist_open">Unfullfilled: ';
                echo $qty_open;
                echo "</span><br>";
            }

            if ($qty_shipped > 0) {
                echo '<span class="picklist picklist_shipped">Shipped: ';
                echo $qty_shipped;
                echo "</span><br>";
            }

        }

        public function install()
        {
            add_option('picklist_autocomplete', "yes");
            add_option('picklist_partial', "no");
            add_option('picklist_swipeconfirm', "no");
        }

    }

    $WC_PickList = new WC_PickList(__FILE__);

    register_activation_hook(__FILE__, array('WC_PickList', 'install'));

endif;
