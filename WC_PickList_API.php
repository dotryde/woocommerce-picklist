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

if ( ! class_exists( 'WC_PickList_API' ) ) :

    class WC_PickList_API extends WC_Integration
    {

        public function __construct()
        {

            add_action('rest_api_init', function () {
                register_rest_route('wc-picklist/v1', 'isAuthenticated', array(
                    'methods' => 'GET',
                    'callback' => array($this, 'isAuthenticatedAPI'),
                ));
            });

            add_action('rest_api_init', function () {
                register_rest_route('wc-picklist/v1', 'getOpenOrders', array(
                    'methods' => 'GET',
                    'callback' => array($this, 'getOpenOrdersAPI'),
                    'permission_callback' => function () {
                        return true;
                        return $this->isAuthenticatedCheck();

                    },
                ));
            });

            add_action('rest_api_init', function () {
                register_rest_route('wc-picklist/v1', '/getOpenOrderByID/(?P<id>\d+)', array(
                    'methods' => 'GET',
                    'callback' => array($this, 'getOpenOrderByIDAPI'),
                    'args' => array(
                        'id' => array(
                            'validate_callback' => function ($param, $request, $key) {
                                return is_numeric($param);
                            }
                        ),
                    ),
                    'permission_callback' => function () {
                        return true;
                        return $this->isAuthenticatedCheck();
                    },
                ));
            });

            add_action('rest_api_init', function () {
                register_rest_route('wc-picklist/v1', 'getImageByID/(?P<id>\d+)', array(
                    'methods' => 'GET',
                    'callback' => array($this, 'getImageByIDAPI'),
                    'args' => array(
                        'id' => array(
                            'validate_callback' => function ($param, $request, $key) {
                                return is_numeric($param);
                            }
                        ),
                    ),
                ));
            });


            add_action('rest_api_init', function () {
                register_rest_route('wc-picklist/v1', 'setShipmentForOrderID', array(
                    'methods' => 'POST',
                    'callback' => array($this, 'setShipmentForOrderIDAPI'),
                    'permission_callback' => function () {
                        return $this->isAuthenticatedCheck();
                    },
                ));
            });

        }


        //// AUTH

        public function isAuthenticatedAPI()
        {

            $picklist_setting = get_option('woocommerce_picklist_settings');

            if ($this->isAuthenticatedCheck()) {
                wp_send_json(array('success' => $this->isAuthenticatedCheck(), 'settings' => $picklist_setting));
            } else {
                wp_send_json(array('success' => $this->isAuthenticatedCheck()));
            }

            die();
        }

        private function isAuthenticatedCheck()
        {

            $WC_REST_Authentication = new WC_REST_Authentication();
            if ($WC_REST_Authentication->authenticate(false)) {
                return true;
            }

            return false;

        }


        //// GET OPEN ORDERS

        public function getOpenOrdersAPI()
        {

            $orders = array();

            // limit it to 50 orders at once
            $max_count = 50;
            $i = 0;

            while (count($orders) < $max_count) {

                $args = array(
                    'post_type' => 'shop_order',
                    'post_status' => 'wc-processing',
                    'posts_per_page' => $max_count,
                    'orderby' => 'date',
                    'order' => 'ASC',
                    'page' => 1,
                    'offset' => $max_count * $i
                );

                $i++;

                $wp_query = new WP_Query($args);

                if ($wp_query->have_posts()) {
                    while ($wp_query->have_posts()) {
                        $wp_query->the_post();

                        $order = $this->getOrder(get_the_ID());

                        if ($order["qty_open"] > 0) {
                            $orders[] = $order;
                        }

                        if (count($orders) >= $max_count) {
                            break;
                        }
                    }
                } else {
                    break;
                }
            }

            $wp_query = new WP_Query(array(
                'post_type' => 'shop_order',
                'post_status' => 'wc-processing',
            ));

            foreach ($orders as $key => $row) {
                $types[$key] = $row['type'];
                $timestamps[$key] = $row['timestamp'];

            }

            array_multisort($types, SORT_ASC, $timestamps, SORT_ASC, $orders);

            wp_send_json(array('success' => true, 'orders' => $orders, 'count' => (string)count($orders), 'total' => (string)$wp_query->found_posts));

            die();

        }


        //// GET ORDER


        public function getOrder($order_id)
        {

            $WC_Order = new WC_Order($order_id);
            if (!isset($WC_Order->post)) {
                return false;
            }

            $order = array();

            $order['status'] = $WC_Order->post_status;
            $order['id'] = (string)$order_id;
            $order['title'] = $this->getFormattedTitleForOrderID($order_id);
            $order['admin_link'] = admin_url('post.php?post=' . $order_id . '&action=edit');
            $order['date'] = date('d.m - H:i', strtotime($WC_Order->order_date));
            $order['timestamp'] = (string)strtotime($WC_Order->order_date);
            $order['items'] = $this->getItemsForOrder($WC_Order);

            $order['amount'] = (string)$this->getAmountOpenForOrderID($order_id) . ' ' . $WC_Order->order_currency;
            $order['qty_open'] = (string)$this->getQtyOpenForOrderID($order_id);
            $order['qty_shipped'] = (string)$this->getQtyShippedForOrderID($order_id);

            $order['shipping_name'] = $WC_Order->shipping_first_name . ' ' . $WC_Order->shipping_last_name;
            $order['formatted_shipping_address'] = str_ireplace(array("<br />", "<br>", "<br/>"), " ", $WC_Order->get_formatted_shipping_address());
            $order['formatted_shipping_address_url'] = $WC_Order->get_shipping_address_map_url();

            $order['billing_phone'] = (string)$WC_Order->billing_phone;
            $order['billing_email'] = $WC_Order->billing_email;


            if ($order['qty_shipped'] > 0) {
                $order['type'] = 'bpart';
            } else {
                $order['type'] = 'anew';
            }

            return $order;

        }


        private function getFormattedTitleForOrderID($id)
        {

            if (count(get_post_meta($id, '_picklist_shipment')) == 0) {
                return "#" . $id;

            } else {
                return "#" . $id . "-" . (count(get_post_meta($id, '_picklist_shipment')) + 1);
            }

        }


        public function getItemsForOrder($WC_Order, $indexed = false)
        {

            if (!isset($WC_Order->post)) {
                $WC_Order = new WC_Order($WC_Order);
            }

            $items = array();
            foreach ($WC_Order->get_items() as $itemID => $lineItem) {

                $item = array();
                $item["order_item_id"] = (string)$itemID;
                $item['name'] = $lineItem['name'];
                $item['qty_ordered'] = (string)abs($lineItem['qty']);
                $item['price'] = (string)abs($lineItem['line_total']/max( 1, $lineItem['qty']));
                $item['qty_refunded'] = (string)abs($WC_Order->get_qty_refunded_for_item($itemID));

                $item['qty_shipped'] = (string)array_sum(wc_get_order_item_meta($itemID, '_picklist_shipped', false));
                $item['qty_notpicked'] = (string)array_sum(wc_get_order_item_meta($itemID, '_picklist_notpicked', false));
                $item['qty_open'] = (string)($item['qty_ordered'] - $item['qty_refunded'] - $item['qty_shipped']);

                if ($item['qty_open'] <= 0) {
                    $item['qty_notpicked'] = "0";
                }

                $item['qty_picked'] = "0";

                if ($lineItem['variation_id'] > 0) {
                    $item['id'] = $lineItem['variation_id'];
                } else {
                    $item['id'] = $lineItem['product_id'];
                }

                if ($WC_Product = new WC_Product($lineItem['id'])) {
                    $item['sku'] = $WC_Product->get_sku();
                }

                if ($item['sku'] == "" && $lineItem['variation_id'] > 0) {
                    if ($WC_Product = new WC_Product($lineItem['variation_id'])) {
                        $item['sku'] = $WC_Product->get_sku();
                    }
                }

                if ($item['sku'] == "") {
                    $item['sku'] = $item['id'];
                }

                $item['formatted_attributes'] = $this->extractVariableProductAttributes($lineItem);

                if ($item['formatted_attributes'] == "") {
                    $item['formatted_attributes'] = $this->extractProductCategories($item['id']);
                }

                if ($indexed) {
                    $items[$itemID] = $item;
                } else {
                    $items[] = $item;
                }


            }

            // sort by sku or id

            foreach ($items as $key => $row) {
                $skus[$key] = $row['sku'];
            }

            uasort($items, function ($a, $b) {
                return strnatcmp($a['sku'], $b['sku']);
            });

            return $items;
        }

        private function extractVariableProductAttributes($product)
        {

            $attributes = "";

            foreach ($product as $productMetaKey => $productMetaValue) {

                if (0 === strpos($productMetaKey, 'pa_')) {
                    $key = str_replace("pa_", "", $productMetaKey);
                    if ($attributes != "") {
                        $attributes .= ' ';
                    }
                    $attributes .= strtoupper($key) . ': ' . $productMetaValue;
                }
            }


            return $attributes;
        }

        private function extractProductCategories($id)
        {

            $categories = "";

            $parent_id = wp_get_post_parent_id($id);
            if ($parent_id) {
                $id = $parent_id;
            }

            $terms = get_the_terms($id, 'product_cat');

            if (is_array($terms))
                foreach ($terms as $term) {
                    if (strlen($categories) > 0) {
                        $categories .= ' > ';
                    }
                    $categories .= $term->name;
                }


            return $categories;
        }

        private function getAmountOpenForOrderID($order_id)
        {

            $WC_Order = new WC_Order($order_id);
            if (!isset($WC_Order->post)) {
                return 0;
            }

            $items = $this->getItemsForOrder($WC_Order);


            $price = 0;

            foreach ($items as $item) {
                $price = $price + $item["qty_open"] * $item["price"];
            }

            return round($price);
        }

        private function getQtyOpenForOrderID($order_id)
        {

            $WC_Order = new WC_Order($order_id);
            if (!isset($WC_Order->post)) {
                return 0;
            }

            $items = $this->getItemsForOrder($WC_Order);


            $qty = 0;

            foreach ($items as $item) {
                $qty = $qty + $item["qty_open"];
            }

            return $qty;
        }



        private function getQtyShippedForOrderID($order_id)
        {

            $WC_Order = new WC_Order($order_id);
            if (!isset($WC_Order->post)) {
                return 0;
            }

            $items = $this->getItemsForOrder($WC_Order);


            $qty = 0;

            foreach ($items as $item) {
                $qty = $qty + $item["qty_shipped"];
            }

            return $qty;
        }




        public function getOpenOrderByIDAPI($data)
        {

            $order_id = $data['id'];

            $order = $this->getOrder($order_id);

            if ($order["status"] != "wc-processing") {
                wp_send_json(array('success' => false, 'message' => "Order #$order_id can't be shipped, because it is " . $order["status"]));
                die();
            }

            if ($order) {

                if (isset($order["items"])) {
                    $order["items"] = $this->filterOpenItemsFromOrder($order["items"]);

                    if (count($order["items"]) > 0) {
                        wp_send_json(array('success' => true, 'order' => $order));
                        die();
                    } else {
                        wp_send_json(array('success' => false, 'message' => "There are no open items for this order!"));
                        die();
                    }


                }
            }

            wp_send_json(array('success' => false, 'message' => "Order #$order_id doesn't need to be shipped anymore"));
            die();

        }



        private function filterOpenItemsFromOrder($items)
        {

            $openItems = array();

            foreach ($items as $item) {
                if ($item["qty_open"] > 0) {
                    $openItems[] = $item;
                }
            }

            return $openItems;

        }

        public function getImageByIDAPI($data)
        {

            $product_id = $data['id'];

            $size = 'single-postthumbnail';
            if (isset($_REQUEST['size'])) {
                $size = $_REQUEST['size'];
            }

            $image_url = "";
            $images = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), $size);
            if (isset($images[0])) {
                $image_url = $images[0];
            } else {
                $parent = wp_get_post_parent_id($product_id);
                if ($parent > 0) {
                    $images = wp_get_attachment_image_src(get_post_thumbnail_id($parent), $size);
                    if (isset($images[0])) {
                        $image_url = $images[0];
                    }
                }
            }

            if ($image_url != "") {
                wp_redirect($image_url);
            } else {
                wp_redirect(plugin_dir_url(__FILE__) . 'images/placeholder.png');


            }

            die();

        }

        public function setShipmentForOrderIDAPI($data)
        {

            $inputJSON = file_get_contents('php://input');
            $parameters = json_decode($inputJSON, true);
            if (!isset($parameters["id"]) || !isset($parameters["processed_items"])) {
                wp_send_json(array('success' => false, 'message' => "There is nothing to be shipped!"));
                die();
            } else {
                $order_id = $parameters["id"];
                $processed_items = $parameters["processed_items"];
            }
            $WC_Order = new WC_Order($order_id);
            if (!isset($WC_Order->post)) {
                wp_send_json(array('success' => false, 'message' => "Can't find order with ID " . $order_id));
                die();
            }
            if ($WC_Order->post_status != "wc-processing") {
                wp_send_json(array('success' => false, 'message' => "We can't ship that order, because order is " . $WC_Order->post_status));
                die();
            }

            $picklist_shipped_items = array();
            $order_items = $this->getItemsForOrder($WC_Order, true);
            foreach ($processed_items as $processed_item) {

                if (isset($order_items[$processed_item["order_item_id"]])) {

                    $qty_open = $order_items[$processed_item["order_item_id"]]["qty_open"];
                    $qty_picked = $processed_item["qty_picked"];
                    if ($qty_picked <= $qty_open && $qty_picked > 0) {
                        wc_add_order_item_meta($processed_item["order_item_id"], '_picklist_shipped', $qty_picked, false);
                        $picklist_shipped_items[] = $processed_item;
                        continue;
                    } else if ($qty_open > 0 && $qty_picked == 0) {
                        wc_add_order_item_meta($processed_item["order_item_id"], '_picklist_notpicked', $qty_open, false);
                        continue;
                    }

                }

                wp_send_json(array('success' => false, 'message' => $processed_item["name"] . " (" . $processed_item["sku"] . ") not needed anymore. Please check the order manually."));
                die();

            }

            if (count($picklist_shipped_items) > 0) {
                $picklist_shipment = array("time" => time(), "items" => $picklist_shipped_items);
                add_post_meta($order_id, '_picklist_shipment', $picklist_shipment);


                $comment = "SHIPMENT #" . count(get_post_meta($order_id, '_picklist_shipment')) . "\r\n";
                foreach ($picklist_shipped_items as $item) {
                    $comment .= $item["qty_picked"] . ' x ' . $item["name"] . ' (' . $item["sku"] . ')' . "\r\n";

                }
                $this->addCommentToOrder($order_id, $comment);
            }

            $picklist_setting = get_option('woocommerce_picklist_settings');

            if (isset($picklist_setting["autocomplete_order"])) {
                if($picklist_setting["autocomplete_order"]=="yes"){
                    $autocomplete_order = true;
                }else{
                    $autocomplete_order = false;
                }

            } else {
                $autocomplete_order = true;
            }

            $qty_open = $this->getQtyOpenForOrderID($order_id);



            if ($WC_Order->post_status == "wc-processing" && $autocomplete_order && $qty_open == 0) {
                $WC_Order->update_status('completed', 'PickList: ');
            }

            if ($qty_open == 0) {
                $isComplete = true;
            }else{
                $isComplete = false;
            }

            wp_send_json(array('success' => true, 'orderState' => array('isComplete' => (string)$isComplete, 'itemsLeft' => (string)$qty_open)));

        }




    }

endif;