<?php
/**
 * WPEssential
 *
 * @package           wpessential-checkout-quantity
 * @author            WPEssential
 * @copyright         2024 WPEssential
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: WPEssential Checkout Quantity
 * Description: WPEssential checkout quantity adds the quantity field on each product to update it via Ajax.
 * Plugin URI: https://wpessential.org/
 * Author: WPEssential
 * Version: 1.0
 * Author URI: https://wpessential.org/
 * Text Domain: wpessential-checkout-quantity
 * Requires PHP: 7.3
 * Requires at least: 5.5
 * Tested up to: 5.7
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path: /languages
 */

/**
 * Class WPEssential Checkout Quantity
 *
 * Handles the addition of quantity update functionality on the WooCommerce checkout page,
 * with auto-update functionality via AJAX and a loader display during updates.
 */
class WPEssentialCheckoutQuantity {
    /**
     * Initialize the class hooks.
     */
    public function __construct() {
        add_action('woocommerce_review_order_after_cart_contents', [$this, 'add_qty_fields']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_inline_scripts']);
        add_action('wp_ajax_update_checkout_qty', [$this, 'handle_qty_update']);
        add_action('wp_ajax_nopriv_update_checkout_qty', [$this, 'handle_qty_update']);
    }

    /**
     * Adds quantity input fields for each product on the checkout page.
     */
    public function add_qty_fields() {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];

            echo '<tr class="woocommerce-cart-form__cart-item">';
            echo '<td class="product-name">' . esc_html($product->get_name()) . '</td>';
            echo '<td class="product-quantity">';
            echo '<input onChange="wpe_checkout_qty_change(this)" type="number" 
                        name="cart[' . esc_attr($cart_item_key) . '][qty]" 
                        class="checkout-qty-input" 
                        data-cart-item-key="' . esc_attr($cart_item_key) . '" 
                        value="' . esc_attr($quantity) . '" 
                        min="1">';
            echo '</td>';
            echo '</tr>';
        }
    }

    /**
     * Enqueues inline JavaScript for auto-updating quantities via AJAX.
     */
    public function enqueue_inline_scripts() {
        if (is_checkout()) {
            wp_add_inline_script('jquery', $this->get_inline_js());
            wp_add_inline_style('woocommerce-inline-loader', $this->get_loader_css());
        }
    }

    /**
     * Returns the inline JavaScript for handling quantity changes.
     *
     * @return string
     */
    private function get_inline_js() {
        return '
        function wpe_checkout_qty_change (obj) {
            let cartKey = jQuery(obj).data("cart-item-key");
            let newQty = jQuery(obj).val();

            // Show loader
            jQuery("body").addClass("processing");

            jQuery.ajax({
                type: "POST",
                url: "' . admin_url('/admin-ajax.php') . '",
                data: {
                    action: "update_checkout_qty",
                    cart_item_key: cartKey,
                    qty: newQty
                },
                success: function (response) {
                    if (response.success) {
                        // Reload the checkout order review section
                        jQuery("body").trigger("update_checkout");
                    } else {
                        alert(response.data.message);
                    }
                },
                complete: function () {
                    // Hide loader
                    jQuery("body").removeClass("processing");
                }
            });
        }
        ';
    }

    /**
     * Returns the CSS for the loader.
     *
     * @return string
     */
    private function get_loader_css() {
        return <<<CSS
            body.processing {position: relative;}

            body.processing:after {
                content: "";
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255, 255, 255, 0.8) url('/wp-admin/images/spinner.gif') no-repeat center center;
                z-index: 9999;
                pointer-events: none;
            }
        CSS;
    }

    /**
     * Handles AJAX requests to update product quantities in the cart.
     */
    public function handle_qty_update() {
        if (!isset($_POST['cart_item_key'], $_POST['qty'])) {
            wp_send_json_error(['message' => 'Invalid request.']);
        }

        $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
        $new_qty = intval($_POST['qty']);

        if ($new_qty < 1) {
            wp_send_json_error(['message' => 'Quantity must be at least 1.']);
        }

        $cart = WC()->cart;

        if ($cart->set_quantity($cart_item_key, $new_qty, true)) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Failed to update the quantity.']);
        }
    }
}

// Initialize the class.
new WPEssentialCheckoutQuantity();
