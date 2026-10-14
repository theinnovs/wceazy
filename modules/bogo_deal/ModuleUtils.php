<?php

// If this file is called directly, abort.
if (!defined ('WPINC')) {
    die;
}

if(!isset($_SESSION)){session_start();}

if (!class_exists ('WcEazyBogoDealUtils')) {
    class WcEazyBogoDealUtils
    {
        public $base_admin;
        public $module_admin;

        public function __construct ($base_admin, $module_admin)
        {
            $this->base_admin = $base_admin;
            $this->module_admin = $module_admin;

            if (!get_option("wceazy_bogo_deal_rules")) {
                update_option('wceazy_bogo_deal_rules', "");
            }
        }





        public function listAllRules()
        {
            $dataRules = get_option("wceazy_bogo_deal_rules");
            return stripslashes($dataRules);
        }

        public function saveAllRules($rules)
        {
            update_option('wceazy_bogo_deal_rules', $rules);
            return True;
        }


        public function getWooProducts($search = "")
        {
            global $wpdb;
            $results = array();
            if (class_exists('WooCommerce')) {
                $sql = $wpdb->prepare( "SELECT ID, post_title FROM {$wpdb->prefix}posts WHERE post_type = 'product' AND post_status = 'publish' AND post_title LIKE %s ORDER BY ID DESC LIMIT 10", array( '%'.$search.'%' ) );
                $listPosts = $wpdb->get_results($sql);
                if (sizeof($listPosts) > 0) {
                    foreach ($listPosts as $singlePost) {
                        $results[] = array("id" => $singlePost->ID, "text" => $singlePost->post_title);
                    }
                }
            }
            return $results;
        }


        // public function addOrUpdateProductOnCart($product_id, $quantity){
        //     $is_exists = False;

        //     $cart = WC()->cart;
        //     foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        //         $cart_product_id = $cart_item['product_id'];

        //         if($cart_product_id == $product_id){
        //             $is_exists = True;
        //             $cart->set_quantity( $cart_item_key, ($quantity + $cart_item['quantity']) );
        //             break;
        //         }
        //     }

        //     if(!$is_exists){
        //         $cart->add_to_cart( $product_id, $quantity);
        //     }
        // }

        
        public function addBogoGiftToCart($product_id, $quantity, $rule_key, $rule) {

            if (!WC()->cart || $quantity <= 0) {
                return false;
            }

            $cart = WC()->cart;

            $existing_key = false;

            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {

                if (
                    !empty($cart_item['wceazy_bogo_gift']) &&
                    !empty($cart_item['wceazy_bogo_rule']) &&
                    $cart_item['wceazy_bogo_rule'] === $rule_key &&
                    (int) $cart_item['product_id'] === (int) $product_id
                ) {
                    $existing_key = $cart_item_key;
                    break;
                }
            }

            /*
            * Gift already exists.
            * Set exact quantity instead of adding quantity again.
            */
            if ($existing_key !== false) {
                $cart->set_quantity($existing_key, (int) $quantity, false);
                return $existing_key;
            }

            /*
            * Add BOGO gift as a separate cart item.
            */
            $cart_item_data = array(
                'wceazy_bogo_gift'            => true,
                'wceazy_bogo_rule'            => $rule_key,
                'wceazy_bogo_discount_type'   => isset($rule->discount_type)
                    ? sanitize_text_field($rule->discount_type)
                    : 'fixed',
                'wceazy_bogo_discount_amount' => isset($rule->discount_amount)
                    ? (float) $rule->discount_amount
                    : 0,
            );

            return $cart->add_to_cart(
                (int) $product_id,
                (int) $quantity,
                0,
                array(),
                $cart_item_data
            );
        }


        public function getCartProductQuantity ($woo_cart, $product_id)
        {
            $quantity = 0;
            foreach ( $woo_cart as $cart_item_key => $cart_item ) {
                $cart_product_id = $cart_item['product_id'];

                if($cart_product_id == $product_id){
                    $quantity = $cart_item['quantity'];
                    break;
                }
            }
            return $quantity;
        }


        public function getCustomerCartProductQuantity($woo_cart, $product_id){
            $quantity = 0;

            foreach ($woo_cart as $cart_item) {

                /*
                * BOGO gift কখনো Buy quantity হিসেবে গণনা হবে না।
                */
                if (!empty($cart_item['wceazy_bogo_gift'])) {
                    continue;
                }

                $cart_product_id = isset($cart_item['product_id'])
                    ? (int) $cart_item['product_id']
                    : 0;

                if ($cart_product_id === (int) $product_id) {
                    $quantity += (int) $cart_item['quantity'];
                }
            }

            return $quantity;
        }


        // public function getBuyProductQuantity ($buy_data, $product_id)
        // {
        //     $quantity = 0;
        //     foreach ( $buy_data as $required_product ) {
        //         if($required_product->id == $product_id){
        //             $quantity = $required_product->quantity;
        //             break;
        //         }
        //     }
        //     return $quantity;
        // }

        // public function addGiftProduct(){

        //     if (class_exists('WooCommerce')) {

        //         $woo_cart = WC()->cart->get_cart();

        //         $rules = !empty($this->listAllRules()) ? json_decode($this->listAllRules(), false) : array();

        //         foreach ($rules as $rule){

        //             $rule_achieved = array();

        //             foreach ($rule->buy_data as $required_product){
        //                 if($required_product->quantity <= $this->getCartProductQuantity($woo_cart, $required_product->id)){
        //                     $rule_achieved[] = 1;
        //                 }else{
        //                     $rule_achieved[] = 0;
        //                 }
        //             }

        //             if (!in_array(0, $rule_achieved)) {
        //                 // Operation to add get products
        //                 foreach ($rule->get_data as $gift_product){
        //                     $quantity_in_cart = $this->getCartProductQuantity($woo_cart, $gift_product->id);
        //                     $quantity_required = $this->getBuyProductQuantity($rule->buy_data, $gift_product->id);
        //                     if($quantity_in_cart < $quantity_required + $gift_product->quantity){

        //                         $this->addOrUpdateProductOnCart($gift_product->id, $gift_product->quantity);

        //                     }
        //                 }
        //             }
        //         }
        //     }
        // }

        public function addGiftProduct(){

            if (!class_exists('WooCommerce') || !WC()->cart) {
                return;
            }

            /*
            * Prevent recursive execution.
            *
            * add_to_cart() / remove_cart_item() নিজেরাও WooCommerce
            * cart hooks trigger করতে পারে।
            */
            static $running = false;

            if ($running) {
                return;
            }

            $running = true;

            try {

                $cart = WC()->cart;

                $rules_data = $this->listAllRules();

                $rules = !empty($rules_data)
                    ? json_decode($rules_data, false)
                    : array();

                if (!is_array($rules)) {
                    $rules = array();
                }

                /*
                * এখানে সব currently required BOGO gifts
                * collect করা হবে।
                *
                * Structure:
                *
                * $desired_gifts[rule_key][product_id] = quantity
                */
                $desired_gifts = array();

                foreach ($rules as $rule_index => $rule) {

                    if (
                        empty($rule->buy_data) ||
                        !is_array($rule->buy_data)
                    ) {
                        continue;
                    }

                    /*
                    * Rule-এর জন্য unique identifier.
                    *
                    * Rule change হলে নতুন key হবে,
                    * ফলে পুরোনো gift automatically stale হয়ে যাবে।
                    */
                    $rule_key = 'rule_' . $rule_index . '_' . md5(
                        wp_json_encode($rule)
                    );

                    $rule_achieved = true;

                    /*
                    * Check all Buy products.
                    */
                    foreach ($rule->buy_data as $required_product) {

                        $required_product_id = isset($required_product->id)
                            ? (int) $required_product->id
                            : 0;

                        $required_quantity = isset($required_product->quantity)
                            ? (int) $required_product->quantity
                            : 0;

                        if (
                            $required_product_id <= 0 ||
                            $required_quantity <= 0
                        ) {
                            $rule_achieved = false;
                            break;
                        }

                        $customer_quantity = $this->getCustomerCartProductQuantity(
                            $cart->get_cart(),
                            $required_product_id
                        );

                        if ($customer_quantity < $required_quantity) {
                            $rule_achieved = false;
                            break;
                        }
                    }

                    /*
                    * Rule achieved হলে Gift products collect করি।
                    */
                    if ($rule_achieved && !empty($rule->get_data)) {

                        foreach ($rule->get_data as $gift_product) {

                            $gift_product_id = isset($gift_product->id)
                                ? (int) $gift_product->id
                                : 0;

                            $gift_quantity = isset($gift_product->quantity)
                                ? (int) $gift_product->quantity
                                : 0;

                            if (
                                $gift_product_id <= 0 ||
                                $gift_quantity <= 0
                            ) {
                                continue;
                            }

                            if (!isset($desired_gifts[$rule_key])) {
                                $desired_gifts[$rule_key] = array();
                            }

                            /*
                            * Same gift product যদি একই rule-এ
                            * একাধিকবার থাকে, quantity যোগ হবে।
                            */
                            if (!isset($desired_gifts[$rule_key][$gift_product_id])) {
                                $desired_gifts[$rule_key][$gift_product_id] = 0;
                            }

                            $desired_gifts[$rule_key][$gift_product_id] += $gift_quantity;
                        }
                    }
                }

                /*
                * Existing BOGO gift cart items synchronize করি।
                */
                $existing_bogo_items = array();

                foreach ($cart->get_cart() as $cart_item_key => $cart_item) {

                    if (empty($cart_item['wceazy_bogo_gift'])) {
                        continue;
                    }

                    $existing_bogo_items[$cart_item_key] = array(
                        'rule_key'   => isset($cart_item['wceazy_bogo_rule'])
                            ? $cart_item['wceazy_bogo_rule']
                            : '',
                        'product_id' => isset($cart_item['product_id'])
                            ? (int) $cart_item['product_id']
                            : 0,
                    );
                }

                /*
                * Remove stale gifts এবং existing gifts-এর
                * quantity exact value-তে set করি।
                */
                foreach ($existing_bogo_items as $cart_item_key => $gift_data) {

                    $rule_key   = $gift_data['rule_key'];
                    $product_id = $gift_data['product_id'];

                    $wanted_quantity = 0;

                    if (
                        isset($desired_gifts[$rule_key]) &&
                        isset($desired_gifts[$rule_key][$product_id])
                    ) {
                        $wanted_quantity = (int) $desired_gifts[$rule_key][$product_id];
                    }

                    if ($wanted_quantity <= 0) {

                        /*
                        * Buy condition আর fulfilled না হলে
                        * gift remove হবে।
                        */
                        $cart->remove_cart_item($cart_item_key);

                    } else {

                        /*
                        * Exact configured quantity রাখি।
                        */
                        $cart->set_quantity(
                            $cart_item_key,
                            $wanted_quantity,
                            false
                        );
                    }
                }

                /*
                * Missing gifts add করি।
                */
                foreach ($desired_gifts as $rule_key => $gift_products) {

                    /*
                    * Original rule বের করি।
                    */
                    $rule = null;

                    foreach ($rules as $rule_index => $single_rule) {

                        $single_rule_key = 'rule_' . $rule_index . '_' . md5(
                            wp_json_encode($single_rule)
                        );

                        if ($single_rule_key === $rule_key) {
                            $rule = $single_rule;
                            break;
                        }
                    }

                    if (!$rule) {
                        continue;
                    }

                    foreach ($gift_products as $gift_product_id => $gift_quantity) {

                        $already_exists = false;

                        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {

                            if (
                                !empty($cart_item['wceazy_bogo_gift']) &&
                                isset($cart_item['wceazy_bogo_rule']) &&
                                $cart_item['wceazy_bogo_rule'] === $rule_key &&
                                (int) $cart_item['product_id'] === (int) $gift_product_id
                            ) {
                                $already_exists = true;
                                break;
                            }
                        }

                        if (!$already_exists) {

                            $this->addBogoGiftToCart(
                                $gift_product_id,
                                $gift_quantity,
                                $rule_key,
                                $rule
                            );
                        }
                    }
                }

            } finally {

                $running = false;
            }
        }


        // public function displayDiscountPrice( $cart ) {
        //     if (class_exists('WooCommerce')) {
        //         if ( is_admin() && ! defined( 'DOING_AJAX' ) ){
        //             return;
        //         }

        //         if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ){
        //             return;
        //         }

        //         $rules = !empty($this->listAllRules()) ? json_decode($this->listAllRules(), false) : array();

        //         foreach ($rules as $rule){
        //             $rule_achieved = array();
        //             foreach ($rule->buy_data as $required_product){
        //                 if($required_product->quantity <= $this->getCartProductQuantity($cart->get_cart(), $required_product->id)){
        //                     $rule_achieved[] = 1;
        //                 }else{
        //                     $rule_achieved[] = 0;
        //                 }
        //             }

        //             if (!in_array(0, $rule_achieved)) {
        //                 foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        //                     $price = wc_get_product( $cart_item['product_id'] )->get_price();
        //                     foreach ($rule->get_data as $gift_product){
        //                         if($cart_item['product_id'] == $gift_product->id){
        //                             $quantity_in_cart = $this->getCartProductQuantity($cart->get_cart(), $gift_product->id);
        //                             $quantity_required = $this->getBuyProductQuantity($rule->buy_data, $gift_product->id);
        //                             if($quantity_in_cart >= $quantity_required + $gift_product->quantity){

        //                                 $normal_price = ($quantity_in_cart - $gift_product->quantity) * $price;
        //                                 if($rule->discount_type == "percent"){
        //                                     $percent = ($rule->discount_amount / 100) * $price;
        //                                     $discounted_price = $gift_product->quantity * ($price - $percent);
        //                                 }else{
        //                                     $discounted_price = $gift_product->quantity * ($price - $rule->discount_amount);
        //                                 }
        //                                 $new_price = ($normal_price + $discounted_price) / $quantity_in_cart;
        //                                 $cart_item['data']->set_price( $new_price );

        //                             }
        //                         }
        //                     }
        //                 }

        //             }
        //         }
        //     }
        // }

        public function displayDiscountPrice($cart){

            if (!class_exists('WooCommerce') || !$cart) {
                return;
            }

            /*
            * Admin normal page-এ cart price change করবো না।
            */
            if (is_admin() && !defined('DOING_AJAX')) {
                return;
            }

            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {

                /*
                * শুধু BOGO gift item-এর price পরিবর্তন হবে।
                *
                * Customer-এর normal purchase এখানে কখনো discount হবে না।
                */
                if (empty($cart_item['wceazy_bogo_gift'])) {
                    continue;
                }

                if (empty($cart_item['data']) || !is_object($cart_item['data'])) {
                    continue;
                }

                /*
                * Fresh product object থেকে original/current WooCommerce price নিই।
                *
                * Variation থাকলে variation price ব্যবহার হবে।
                */
                $price_product_id = !empty($cart_item['variation_id'])
                    ? (int) $cart_item['variation_id']
                    : (int) $cart_item['product_id'];

                $product = wc_get_product($price_product_id);

                if (!$product) {
                    continue;
                }

                $normal_price = (float) $product->get_price();

                $discount_type = isset($cart_item['wceazy_bogo_discount_type'])
                    ? $cart_item['wceazy_bogo_discount_type']
                    : 'fixed';

                $discount_amount = isset($cart_item['wceazy_bogo_discount_amount'])
                    ? (float) $cart_item['wceazy_bogo_discount_amount']
                    : 0;

                /*
                * Percentage discount
                */
                if ($discount_type === 'percent') {

                    $discounted_price = $normal_price
                        - ($normal_price * ($discount_amount / 100));

                } else {

                    /*
                    * Fixed discount
                    */
                    $discounted_price = $normal_price - $discount_amount;
                }

                /*
                * Price কখনো negative হবে না।
                */
                $discounted_price = max(0, $discounted_price);

                /*
                * শুধু Gift cart line-এর unit price change করছি।
                */
                $cart_item['data']->set_price($discounted_price);
            }
        }

        // public function displayDiscountPriceText( $price, $cart_item, $cart_item_key ) {
        //     $product_id = $cart_item['product_id'];
        //     $cart = WC()->cart;
        //     $rules = !empty($this->listAllRules()) ? json_decode($this->listAllRules(), false) : array();
        //     foreach ($rules as $rule){
        //         $rule_achieved = array();
        //         foreach ($rule->buy_data as $required_product){
        //             if($required_product->quantity <= $this->getCartProductQuantity($cart->get_cart(), $required_product->id)){
        //                 $rule_achieved[] = 1;
        //             }else{
        //                 $rule_achieved[] = 0;
        //             }
        //         }

        //         if (!in_array(0, $rule_achieved)) {
        //             foreach ($rule->get_data as $gift_product){
        //                 if($product_id == $gift_product->id){
        //                     $quantity_in_cart = $this->getCartProductQuantity($cart->get_cart(), $gift_product->id);
        //                     $quantity_required = $this->getBuyProductQuantity($rule->buy_data, $gift_product->id);
        //                     if($quantity_in_cart >= $quantity_required + $gift_product->quantity){


        //                         $price = wc_get_product( $product_id )->get_price();
        //                         $normal_quantity = $quantity_in_cart - $gift_product->quantity;

        //                         if($rule->discount_type == "percent"){
        //                             $percent = ($rule->discount_amount / 100) * $price;
        //                             $discounted_price = $price - $percent;
        //                         }else{
        //                             $discounted_price = $price - $rule->discount_amount;
        //                         }

        //                         $discounted_quantity = $gift_product->quantity;

        //                         if($quantity_in_cart == $gift_product->quantity){
        //                             $price = "<s>".wc_price($price)."</s> ".wc_price($discounted_price);
        //                         }else{
        //                             $price = wc_price($price)." x ".$normal_quantity."<br>";
        //                             $price .= wc_price($discounted_price)." x ".$discounted_quantity;
        //                         }


        //                         return $price;


        //                     }
        //                 }
        //             }

        //         }
        //     }
        //     return $price;
        // }

        public function displayDiscountPriceText($price, $cart_item, $cart_item_key) {
            /*
            * Normal product হলে WooCommerce-এর original price return হবে।
            */
            if (empty($cart_item['wceazy_bogo_gift'])) {
                return $price;
            }

            $product_id = !empty($cart_item['variation_id'])
                ? (int) $cart_item['variation_id']
                : (int) $cart_item['product_id'];

            $product = wc_get_product($product_id);

            if (!$product) {
                return $price;
            }

            /*
            * Catalog-এর normal price.
            */
            $normal_price = (float) $product->get_price();

            $discount_type = isset($cart_item['wceazy_bogo_discount_type'])
                ? $cart_item['wceazy_bogo_discount_type']
                : 'fixed';

            $discount_amount = isset($cart_item['wceazy_bogo_discount_amount'])
                ? (float) $cart_item['wceazy_bogo_discount_amount']
                : 0;

            if ($discount_type === 'percent') {

                $discounted_price = $normal_price
                    - ($normal_price * ($discount_amount / 100));

            } else {

                $discounted_price = $normal_price - $discount_amount;
            }

            $discounted_price = max(0, $discounted_price);

            /*
            * Example:
            *
            * $20.00 → $10.00
            *
            * অথবা
            *
            * $20.00 → $18.00
            */
            return '<s>' .
                wc_price($normal_price) .
                '</s> ' .
                wc_price($discounted_price);
        }



    }
}