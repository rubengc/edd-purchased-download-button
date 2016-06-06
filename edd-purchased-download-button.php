<?php
/**
 * Plugin Name:     EDD Purchased Download Button
 * Plugin URI:      https://wordpress.org/plugins/edd-purchased-download-button
 * Description:     Automatically adds a "Download" button instead of "Add To Cart" on purchased downloads
 * Version:         1.0.0
 * Author:          rubengc
 * Author URI:      http://rubengc.com
 * Text Domain:     purchased-download-button
 *
 * @package         EDD\PurchasedDownloadButton
 * @author          rubengc
 * @copyright       Copyright (c) rubengc
 */


// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'EDD_Purchased_Download_Button' ) ) {

    /**
     * Main EDD_Purchased_Download_Button class
     *
     * @since       1.0.0
     */
    class EDD_Purchased_Download_Button {

        /**
         * @var         EDD_Purchased_Download_Button $instance The one true EDD_Purchased_Download_Button
         * @since       1.0.0
         */
        private static $instance;


        /**
         * Get active instance
         *
         * @access      public
         * @since       1.0.0
         * @return      object self::$instance The one true EDD_Purchased_Download_Button
         */
        public static function instance() {
            if( !self::$instance ) {
                self::$instance = new EDD_Purchased_Download_Button();
                self::$instance->setup_constants();
                self::$instance->load_textdomain();
                self::$instance->hooks();
            }

            return self::$instance;
        }


        /**
         * Setup plugin constants
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function setup_constants() {
            // Plugin version
            define( 'EDD_PURCHASED_DOWNLOAD_BUTTON_VER', '1.0.0' );

            // Plugin path
            define( 'EDD_PURCHASED_DOWNLOAD_BUTTON_DIR', plugin_dir_path( __FILE__ ) );

            // Plugin URL
            define( 'EDD_PURCHASED_DOWNLOAD_BUTTON_URL', plugin_dir_url( __FILE__ ) );
        }


        /**
         * Run action and filter hooks
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function hooks() {
            // Register settings
            add_filter( 'edd_settings_extensions', array( $this, 'settings' ), 1 );

            // Register a "Download" button instead of "Add To Cart" on purchased downloads
            add_filter( 'edd_purchase_download_form', array( $this, 'purchased_download_button' ), 10, 2 );
        }


        /**
         * Internationalization
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function load_textdomain() {
            // Set filter for language directory
            $lang_dir = EDD_PURCHASED_DOWNLOAD_BUTTON_DIR . '/languages/';
            $lang_dir = apply_filters( 'edd_purchased_download_button_languages_directory', $lang_dir );

            // Traditional WordPress plugin locale filter
            $locale = apply_filters( 'plugin_locale', get_locale(), 'edd-purchased-download-button' );
            $mofile = sprintf( '%1$s-%2$s.mo', 'edd-purchased-download-button', $locale );

            // Setup paths to current locale file
            $mofile_local   = $lang_dir . $mofile;
            $mofile_global  = WP_LANG_DIR . '/edd-purchased-download-button/' . $mofile;

            if( file_exists( $mofile_global ) ) {
                // Look in global /wp-content/languages/edd-purchased-download-button/ folder
                load_textdomain( 'edd-purchased-download-button', $mofile_global );
            } elseif( file_exists( $mofile_local ) ) {
                // Look in local /wp-content/plugins/edd-purchased-download-button/languages/ folder
                load_textdomain( 'edd-purchased-download-button', $mofile_local );
            } else {
                // Load the default language files
                load_plugin_textdomain( 'edd-purchased-download-button', false, $lang_dir );
            }
        }


        /**
         * Add settings
         *
         * @access      public
         * @since       1.0.0
         * @param       array $settings The existing EDD settings array
         * @return      array The modified EDD settings array
         */
        public function settings( $settings ) {
            $new_settings = array(
                array(
                    'id'    => 'edd_purchased_download_button_settings',
                    'name'  => '<strong>' . __( 'Purchased Download Button', 'edd-purchased-download-button' ) . '</strong>',
                    'desc'  => __( 'Configure Purchased Download Button', 'edd-purchased-download-button' ),
                    'type'  => 'header',
                ),
                'purchased_download_button_text' => array(
                    'id'    => 'purchased_download_button_text',
                    'name'  => __( 'Download text', 'edd-purchased-download-button' ),
                    'desc'  => __( 'Text shown on the Download button.', 'edd-purchased-download-button' ),
                    'type'  => 'text',
                    'std'   => __( 'Download', 'edd-purchased-download-button' )
                ),
            );

            return array_merge( $settings, $new_settings );
        }

        /**
         * Download button on purchased downloads
         *
         * @access      public
         * @since       1.0.0
         * @param       string $purchase_form EDD original purchase form
         * @param       array $args Purchase form args (contains the download ID)
         * @return      string $purchase_form
         */
        public function purchased_download_button( $purchase_form, $args ) {
            global $edd_options;

            if ( !is_user_logged_in() ) {
                return $purchase_form;
            }

            $download_id = (string)$args['download_id'];
            $current_user_id = get_current_user_id();
            // If the user has purchased this item, itterate through their purchases to get the specific purchase data and pull out the key and email associated with it. 
            // This is necessary for the generation of the download link
            if ( edd_has_user_purchased( $current_user_id, $download_id, $variable_price_id = null ) ) {
                $user_purchases = edd_get_users_purchases( $current_user_id, -1, false, 'complete' );

                foreach ( $user_purchases as $purchase ) {
                    $cart_items = edd_get_payment_meta_cart_details( $purchase->ID );
                    $item_ids = wp_list_pluck( $cart_items, 'id' );

                    if ( in_array( $download_id, $item_ids ) ) {
                        $email = edd_get_payment_user_email( $purchase->ID );
                        $payment_key = edd_get_payment_key( $purchase->ID );
                    }
                }

                $download_ids = array();

                if ( edd_is_bundled_product( $download_id ) ) {
                    $download_ids = edd_get_bundled_products( $download_id );
                } else {
                    $download_ids[] = $download_id;
                }

                $text = isset( $edd_options['purchased_download_button_text'] ) ? $edd_options['purchased_download_button_text'] : 'Download';
                $style = isset( $edd_options['button_style'] ) ? $edd_options['button_style'] : 'button';
                $color = isset( $edd_options['checkout_color'] ) ? $edd_options['checkout_color'] : 'blue';
                $new_purchase_form = '';

                foreach ( $download_ids as $item ) {
                    // Attempt to get the file data associated with this download
                    $download_data = edd_get_download_files( $item, null );

                    if ( $download_data ) {
                        foreach ( $download_data as $filekey => $file ) {
                            // Generate the file URL and then make a link to it
                            $file_url = edd_get_download_file_url( $payment_key, $email, $filekey, $item, null );
                            $new_purchase_form .= '<a href="' . $file_url . '" class="edd-purchased-download-button ' . $style . ' ' . $color . ' edd-submit"><span class="edd-purchased-download-label">' . __( $text, 'edd-purchased-download-button' ) . '</span></a>';
                        }
                    }
                    // As long as we ended up with links to show, use them.
                    if ( !empty( $new_purchase_form ) ) {
                        $purchase_form = '<div class="edd_purchase_submit_wrapper">' . $new_purchase_form . '</div>';
                    }
                }
            }

            return apply_filters( 'edd_purchases_download_button', $purchase_form, $args );
        }
    }
} // End if class_exists check


/**
 * The main function responsible for returning the one true EDD_Purchased_Download_Button
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      \EDD_Purchased_Download_Button The one true EDD_Purchased_Download_Button
 */
function edd_purchased_download_button() {
    return EDD_Purchased_Download_Button::instance();
}
add_action( 'plugins_loaded', 'edd_purchased_download_button' );
