<?php
/**
 * Plugin Name: WPC Variations Table for WooCommerce
 * Plugin URI: https://wpclever.net/
 * Description: WPC Variations Table will replace dropdown selects with a beautiful table.
 * Version: 3.7.2
 * Author: WPClever
 * Author URI: https://wpclever.net
 * Text Domain: wpc-variations-table
 * Domain Path: /languages/
 * Requires Plugins: woocommerce
 * Requires at least: 4.0
 * Tested up to: 6.6
 * WC requires at least: 3.0
 * WC tested up to: 9.1
 */

defined( 'ABSPATH' ) || exit;

! defined( 'WPCVT_VERSION' ) && define( 'WPCVT_VERSION', '3.7.2' );
! defined( 'WPCVT_LITE' ) && define( 'WPCVT_LITE', __FILE__ );
! defined( 'WPCVT_FILE' ) && define( 'WPCVT_FILE', __FILE__ );
! defined( 'WPCVT_URI' ) && define( 'WPCVT_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCVT_DIR' ) && define( 'WPCVT_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCVT_SUPPORT' ) && define( 'WPCVT_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=wpcvt&utm_campaign=wporg' );
! defined( 'WPCVT_REVIEWS' ) && define( 'WPCVT_REVIEWS', 'https://wordpress.org/support/plugin/wpc-variations-table/reviews/?filter=5' );
! defined( 'WPCVT_CHANGELOG' ) && define( 'WPCVT_CHANGELOG', 'https://wordpress.org/plugins/wpc-variations-table/#developers' );
! defined( 'WPCVT_DISCUSSION' ) && define( 'WPCVT_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-variations-table' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCVT_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'wpcvt_init' ) ) {
	add_action( 'plugins_loaded', 'wpcvt_init', 11 );

	function wpcvt_init() {
		// load text-domain
		load_plugin_textdomain( 'wpc-variations-table', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcvt_notice_wc' );

			return null;
		}

		if ( ! class_exists( 'WPCleverWpcvt' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWpcvt {
				protected static $settings = [];
				protected static $localization = [];
				protected static $dt_localization = [];
				protected static $instance = null;

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$settings        = (array) get_option( 'wpcvt_settings', [] );
					self::$localization    = (array) get_option( 'wpcvt_localization', [] );
					self::$dt_localization = [
						'decimal'        => '',
						'emptyTable'     => esc_html__( 'No data available in table', 'wpc-variations-table' ),
						'info'           => esc_html__( 'Showing _START_ to _END_ of _TOTAL_ entries', 'wpc-variations-table' ),
						'infoEmpty'      => esc_html__( 'Showing 0 to 0 of 0 entries', 'wpc-variations-table' ),
						'infoFiltered'   => esc_html__( '(filtered from _MAX_ total entries)', 'wpc-variations-table' ),
						'infoPostFix'    => '',
						'thousands'      => esc_html__( ',', 'wpc-variations-table' ),
						'lengthMenu'     => esc_html__( 'Show _MENU_ entries', 'wpc-variations-table' ),
						'loadingRecords' => esc_html__( 'Loading...', 'wpc-variations-table' ),
						'processing'     => esc_html__( 'Processing...', 'wpc-variations-table' ),
						'search'         => esc_html__( 'Search:', 'wpc-variations-table' ),
						'zeroRecords'    => esc_html__( 'No matching records found', 'wpc-variations-table' ),
						'paginate'       => [
							'first'    => esc_html__( 'First', 'wpc-variations-table' ),
							'last'     => esc_html__( 'Last', 'wpc-variations-table' ),
							'next'     => esc_html__( 'Next', 'wpc-variations-table' ),
							'previous' => esc_html__( 'Previous', 'wpc-variations-table' )
						],
						'aria'           => [
							'sortAscending'  => esc_html__( ': activate to sort column ascending', 'wpc-variations-table' ),
							'sortDescending' => esc_html__( ': activate to sort column descending', 'wpc-variations-table' )
						]
					];

					// init
					add_action( 'init', [ $this, 'init' ] );

					// settings page
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );

					// settings link
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

					// enqueue backend scripts
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 99 );

					// enqueue frontend scripts
					add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 99 );

					// product settings
					add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );
					add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panels' ] );
					add_action( 'woocommerce_process_product_meta', [ $this, 'process_product_meta' ] );

					// functions
					add_action( 'woocommerce_before_add_to_cart_form', [ $this, 'hide_variations_form' ], 99 );

					switch ( self::get_setting( 'position', 'replace_atc' ) ) {
						case 'below_title';
							add_action( 'woocommerce_single_product_summary', [ $this, 'variations_table' ], 6 );
							break;
						case 'below_price':
							add_action( 'woocommerce_single_product_summary', [ $this, 'variations_table' ], 11 );
							break;
						case 'below_excerpt';
							add_action( 'woocommerce_single_product_summary', [ $this, 'variations_table' ], 21 );
							break;
						case 'replace_atc';
							add_action( 'woocommerce_single_product_summary', [ $this, 'variations_table' ], 29 );
							break;
						case 'below_meta';
							add_action( 'woocommerce_single_product_summary', [ $this, 'variations_table' ], 41 );
							break;
						case 'below_sharing';
							add_action( 'woocommerce_single_product_summary', [ $this, 'variations_table' ], 51 );
							break;
					}

					// custom variation name & image
					add_action( 'woocommerce_product_after_variable_attributes', [
						$this,
						'variation_settings'
					], 10, 3 );
					add_action( 'woocommerce_save_product_variation', [ $this, 'save_variation_settings' ], 10, 2 );
					add_filter( 'woocommerce_product_variation_get_name', [ $this, 'variation_get_name' ], 99, 2 );

					// ajax load dropdown attributes
					add_action( 'wp_ajax_wpcvt_dropdown_attributes', [ $this, 'ajax_dropdown_attributes' ] );

					// ajax add-to-cart
					add_action( 'wp_ajax_wpcvt_add_to_cart', [ $this, 'ajax_add_to_cart' ] );
					add_action( 'wp_ajax_nopriv_wpcvt_add_to_cart', [ $this, 'ajax_add_to_cart' ] );

					// WPC Smart Messages
					add_filter( 'wpcsm_locations', [ $this, 'wpcsm_locations' ] );

					// WPC Variation Duplicator
					add_action( 'wpcvd_duplicated', [ $this, 'duplicate_variation' ], 99, 2 );

					// WPC Variation Bulk Editor
					add_action( 'wpcvb_bulk_update_variation', [ $this, 'bulk_update_variation' ], 99, 2 );
				}

				function init() {
					add_shortcode( 'wpcvt', [ $this, 'shortcode' ] );
				}

				public static function get_settings() {
					return apply_filters( 'wpcvt_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
						if ( self::$settings[ $name ] !== '' ) {
							$setting = self::$settings[ $name ];
						} else {
							$setting = $default;
						}
					} else {
						$setting = get_option( '_wpcvt_' . $name, $default );
					}

					return apply_filters( 'wpcvt_get_setting', $setting, $name, $default );
				}

				public static function localization( $key = '', $default = '' ) {
					$str = '';

					if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
						$str = self::$localization[ $key ];
					} elseif ( ! empty( $default ) ) {
						$str = $default;
					}

					return apply_filters( 'wpcvt_localization_' . $key, $str );
				}

				function register_settings() {
					// settings
					register_setting( 'wpcvt_settings', 'wpcvt_settings' );

					// localization
					register_setting( 'wpcvt_localization', 'wpcvt_localization' );
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', esc_html__( 'WPC Variations Table', 'wpc-variations-table' ), esc_html__( 'Variations Table', 'wpc-variations-table' ), 'manage_options', 'wpclever-wpcvt', [
						$this,
						'admin_menu_content'
					] );
				}

				function admin_menu_content() {
					add_thickbox();
					$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Variations Table', 'wpc-variations-table' ) . ' ' . esc_html( WPCVT_VERSION ) . ' ' . ( defined( 'WPCVT_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-variations-table' ) . '</span>' : '' ); ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
								<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-variations-table' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WPCVT_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-variations-table' ); ?></a> |
                                <a href="<?php echo esc_url( WPCVT_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-variations-table' ); ?></a> |
                                <a href="<?php echo esc_url( WPCVT_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-variations-table' ); ?></a>
                            </p>
                        </div>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                            <div class="notice notice-success is-dismissible">
                                <p><?php esc_html_e( 'Settings updated.', 'wpc-variations-table' ); ?></p>
                            </div>
						<?php } ?>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcvt&tab=settings' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Settings', 'wpc-variations-table' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcvt&tab=localization' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Localization', 'wpc-variations-table' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcvt&tab=premium' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>" style="color: #c9356e">
									<?php esc_html_e( 'Premium Version', 'wpc-variations-table' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'wpc-variations-table' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'settings' ) {
								$active             = self::get_setting( 'active', 'yes' );
								$position           = self::get_setting( 'position', 'replace_atc' );
								$layout             = self::get_setting( 'layout', 'default' );
								$hide_unpurchasable = self::get_setting( 'hide_unpurchasable', 'no' );
								$variation_name     = self::get_setting( 'variation_name', 'formatted' );
								$product_name       = self::get_setting( 'product_name', 'yes' );
								$link               = self::get_setting( 'link', 'yes' );
								$show_atc           = self::get_setting( 'show_atc', 'each' );
								$show_image         = self::get_setting( 'show_image', 'yes' );
								$show_price         = self::get_setting( 'show_price', 'yes' );
								$show_availability  = self::get_setting( 'show_availability', 'yes' );
								$show_description   = self::get_setting( 'show_description', 'yes' );
								$before_text        = self::get_setting( 'before_text', '' );
								$after_text         = self::get_setting( 'after_text', '' );
								?>
                                <form method="post" action="options.php">
                                    <table class="form-table">
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Active', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label> <select name="wpcvt_settings[active]">
                                                        <option value="no" <?php selected( $active, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-table' ); ?></option>
                                                        <option value="yes" <?php selected( $active, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-table' ); ?></option>
                                                    </select> </label>
                                                <span class="description"><?php esc_html_e( 'This is the default status, you can set status for individual product in the its settings.', 'wpc-variations-table' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Position', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label> <select name="wpcvt_settings[position]">
                                                        <option value="replace_atc" <?php selected( $position, 'replace_atc' ); ?>><?php esc_html_e( 'Replace the add to cart form', 'wpc-variations-table' ); ?></option>
                                                        <option value="below_title" <?php selected( $position, 'below_title' ); ?>><?php esc_html_e( 'Under the title', 'wpc-variations-table' ); ?></option>
                                                        <option value="below_price" <?php selected( $position, 'below_price' ); ?>><?php esc_html_e( 'Under the price', 'wpc-variations-table' ); ?></option>
                                                        <option value="below_excerpt" <?php selected( $position, 'below_excerpt' ); ?>><?php esc_html_e( 'Under the excerpt', 'wpc-variations-table' ); ?></option>
                                                        <option value="below_meta" <?php selected( $position, 'below_meta' ); ?>><?php esc_html_e( 'Under the meta', 'wpc-variations-table' ); ?></option>
                                                        <option value="below_sharing" <?php selected( $position, 'below_sharing' ); ?>><?php esc_html_e( 'Under the sharing', 'wpc-variations-table' ); ?></option>
                                                        <option value="no" <?php selected( $position, 'no' ); ?>><?php esc_html_e( 'None (hide it)', 'wpc-variations-table' ); ?></option>
                                                    </select> </label>
                                                <p class="description"><?php esc_html_e( 'Choose the position to show the variations table on single product page. You also can use shortcode [wpcvt] to place it where you want.', 'wpc-variations-table' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Layout', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label> <select name="wpcvt_settings[layout]">
                                                        <option value="default" <?php selected( $layout, 'default' ); ?>><?php esc_html_e( 'Simple', 'wpc-variations-table' ); ?></option>
                                                        <option value="datatables" <?php selected( $layout, 'datatables' ); ?>><?php esc_html_e( 'DataTables', 'wpc-variations-table' ); ?></option>
                                                    </select> </label>
                                                <p class="description"><?php esc_html_e( 'DataTables supports pagination and filter results by text search. Read more about it here: https://datatables.net/reference/option/language', 'wpc-variations-table' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Hide unpurchasable variation', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label> <select name="wpcvt_settings[hide_unpurchasable]">
                                                        <option value="no" <?php selected( $hide_unpurchasable, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-table' ); ?></option>
                                                        <option value="yes" <?php selected( $hide_unpurchasable, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-table' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Variation name', 'wpc-variations-table' ); ?></th>
                                            <td>
                                                <label> <select name="wpcvt_settings[variation_name]">
                                                        <option value="formatted" <?php selected( $variation_name, 'formatted' ); ?>><?php esc_html_e( 'Formatted without attribute label (e.g Green, M)', 'wpc-variations-table' ); ?></option>
                                                        <option value="formatted_label" <?php selected( $variation_name, 'formatted_label' ); ?>><?php esc_html_e( 'Formatted with attribute label (e.g Color: Green, Size: M)', 'wpc-variations-table' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Include product name', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label> <select name="wpcvt_settings[product_name]">
                                                        <option value="no" <?php selected( $product_name, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-table' ); ?></option>
                                                        <option value="yes" <?php selected( $product_name, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-table' ); ?></option>
                                                    </select> </label>
                                                <span class="description"><?php esc_html_e( 'Include the product name before variation name.', 'wpc-variations-table' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Open quick view popup', 'wpc-variations-table' ); ?></th>
                                            <td>
                                                <label> <select name="wpcvt_settings[link]">
                                                        <option value="yes" <?php selected( $link, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-table' ); ?></option>
                                                        <option value="no" <?php selected( $link, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-table' ); ?></option>
                                                    </select> </label>
                                                <p class="description">Open quick view popup when clicking on each variation. If you choose "Yes", please install
                                                    <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Smart Quick View">WPC Smart Quick View</a> to make it work.
                                                </p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Show add to cart button', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label> <select name="wpcvt_settings[show_atc]">
                                                        <option value="each" <?php selected( $show_atc, 'each' ); ?>><?php esc_html_e( 'Yes, for each variation', 'wpc-variations-table' ); ?></option>
                                                        <option value="all" <?php selected( $show_atc, 'all' ); ?>><?php esc_html_e( 'Yes, for all variations', 'wpc-variations-table' ); ?></option>
                                                        <option value="no" <?php selected( $show_atc, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-table' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Show image', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label> <select name="wpcvt_settings[show_image]">
                                                        <option value="yes" <?php selected( $show_image, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-table' ); ?></option>
                                                        <option value="no" <?php selected( $show_image, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-table' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Show price', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label> <select name="wpcvt_settings[show_price]">
                                                        <option value="yes" <?php selected( $show_price, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-table' ); ?></option>
                                                        <option value="no" <?php selected( $show_price, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-table' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Show availability', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label> <select name="wpcvt_settings[show_availability]">
                                                        <option value="yes" <?php selected( $show_availability, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-table' ); ?></option>
                                                        <option value="no" <?php selected( $show_availability, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-table' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Show description', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label> <select name="wpcvt_settings[show_description]">
                                                        <option value="yes" <?php selected( $show_description, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-table' ); ?></option>
                                                        <option value="no" <?php selected( $show_description, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-table' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Before text', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <textarea name="wpcvt_settings[before_text]" style="width: 99%"><?php echo esc_textarea( $before_text ); ?></textarea>
                                                </label>
                                                <span class="description"><?php esc_html_e( 'Default text before variations table.', 'wpc-variations-table' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'After text', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <textarea name="wpcvt_settings[after_text]" style="width: 99%"><?php echo esc_textarea( $after_text ); ?></textarea>
                                                </label>
                                                <span class="description"><?php esc_html_e( 'Default text after variations table.', 'wpc-variations-table' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
												<?php settings_fields( 'wpcvt_settings' ); ?><?php submit_button(); ?>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } elseif ( $active_tab === 'localization' ) { ?>
                                <form method="post" action="options.php">
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th scope="row"><?php esc_html_e( 'General', 'wpc-variations-table' ); ?></th>
                                            <td>
												<?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-variations-table' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Add all to cart', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[add_all_to_cart]" value="<?php echo esc_attr( self::localization( 'add_all_to_cart' ) ); ?>" placeholder="<?php /* translators: count */
													esc_attr_e( 'Add all to cart (%s)', 'wpc-variations-table' ); ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Image', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[image]" value="<?php echo esc_attr( self::localization( 'image' ) ); ?>" placeholder="<?php esc_attr_e( 'Image', 'wpc-variations-table' ); ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Name', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[name]" value="<?php echo esc_attr( self::localization( 'name' ) ); ?>" placeholder="<?php esc_attr_e( 'Name', 'wpc-variations-table' ); ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Action', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[action]" value="<?php echo esc_attr( self::localization( 'action' ) ); ?>" placeholder="<?php esc_attr_e( 'Action', 'wpc-variations-table' ); ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th scope="row"><?php esc_html_e( 'DataTables', 'wpc-variations-table' ); ?></th>
                                            <td>
												<?php esc_html_e( 'Read more about the below strings here https://datatables.net/reference/option/language', 'wpc-variations-table' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'decimal', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[decimal]" value="<?php echo esc_attr( self::$localization['decimal'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['decimal']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'emptyTable', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[emptyTable]" value="<?php echo esc_attr( self::$localization['emptyTable'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['emptyTable']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'info', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[info]" value="<?php echo esc_attr( self::$localization['info'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['info']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'infoEmpty', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[infoEmpty]" value="<?php echo esc_attr( self::$localization['infoEmpty'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['infoEmpty']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'infoFiltered', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[infoFiltered]" value="<?php echo esc_attr( self::$localization['infoFiltered'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['infoFiltered']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'infoPostFix', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[infoPostFix]" value="<?php echo esc_attr( self::$localization['infoPostFix'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['infoPostFix']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'thousands', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[thousands]" value="<?php echo esc_attr( self::$localization['thousands'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['thousands']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'lengthMenu', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[lengthMenu]" value="<?php echo esc_attr( self::$localization['lengthMenu'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['lengthMenu']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'loadingRecords', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[loadingRecords]" value="<?php echo esc_attr( self::$localization['loadingRecords'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['loadingRecords']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'processing', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[processing]" value="<?php echo esc_attr( self::$localization['processing'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['processing']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'search', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[search]" value="<?php echo esc_attr( self::$localization['search'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['search']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'zeroRecords', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[zeroRecords]" value="<?php echo esc_attr( self::$localization['zeroRecords'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['zeroRecords']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'paginate:first', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[paginate][first]" value="<?php echo esc_attr( self::$localization['paginate']['first'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['paginate']['first']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'paginate:last', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[paginate][last]" value="<?php echo esc_attr( self::$localization['paginate']['last'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['paginate']['last']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'paginate:next', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[paginate][next]" value="<?php echo esc_attr( self::$localization['paginate']['next'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['paginate']['next']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'paginate:previous', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[paginate][previous]" value="<?php echo esc_attr( self::$localization['paginate']['previous'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['paginate']['previous']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'aria:sortAscending', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[aria][sortAscending]" value="<?php echo esc_attr( self::$localization['aria']['sortAscending'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['aria']['sortAscending']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'aria:sortDescending', 'wpc-variations-table' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcvt_localization[aria][sortDescending]" value="<?php echo esc_attr( self::$localization['aria']['sortDescending'] ?? '' ); ?>" placeholder="<?php echo self::$dt_localization['aria']['sortDescending']; ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
												<?php settings_fields( 'wpcvt_localization' ); ?><?php submit_button(); ?>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } elseif ( $active_tab === 'premium' ) { ?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>
                                        Get the Premium Version just $29!
                                        <a href="https://wpclever.net/downloads/wpc-variations-table?utm_source=pro&utm_medium=wpcvt&utm_campaign=wporg" target="_blank">https://wpclever.net/downloads/wpc-variations-table</a>
                                    </p>
                                    <p><strong>Extra features for Premium Version:</strong></p>
                                    <ul style="margin-bottom: 0">
                                        <li>- Settings for individual product.</li>
                                        <li>- Get the lifetime update & premium support.</li>
                                    </ul>
                                </div>
							<?php } ?>
                        </div><!-- /.wpclever_settings_page_content -->
                        <div class="wpclever_settings_page_suggestion">
                            <div class="wpclever_settings_page_suggestion_label">
                                <span class="dashicons dashicons-yes-alt"></span> Suggestion
                            </div>
                            <div class="wpclever_settings_page_suggestion_content">
                                <div>
                                    To display custom engaging real-time messages on any wished positions, please install
                                    <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                                </div>
                                <div>
                                    Wanna save your precious time working on variations? Try our brand-new free plugin
                                    <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                                    <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcvt&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-variations-table' ) . '</a>';
						$links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcvt&tab=premium' ) ) . '">' . esc_html__( 'Premium Version', 'wpc-variations-table' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'support' => '<a href="' . esc_url( WPCVT_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-variations-table' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function admin_enqueue_scripts() {
					if ( 'product' === get_post_type() ) {
						wp_enqueue_style( 'wpcvt-backend', WPCVT_URI . 'assets/css/backend.css', [], WPCVT_VERSION );
						wp_enqueue_script( 'wpcvt-backend', WPCVT_URI . 'assets/js/backend.js', [ 'jquery' ], WPCVT_VERSION, true );
						wp_localize_script( 'wpcvt-backend', 'wpcvt_vars', [
							'media_add_text' => esc_html__( 'Add to Variation', 'wpc-variations-table' ),
							'media_title'    => esc_html__( 'Custom Image', 'wpc-variations-table' ),
							'media_remove'   => esc_html__( 'Remove', 'wpc-variations-table' )
						] );
					}
				}

				function enqueue_scripts() {
					if ( ! apply_filters( 'wpcvt_disable_datatables', false ) && ( self::get_setting( 'layout', 'default' ) === 'datatables' ) ) {
						wp_enqueue_style( 'datatables', WPCVT_URI . 'assets/libs/datatables/datatables.min.css', [], '1.10.22' );
						wp_enqueue_script( 'datatables', WPCVT_URI . 'assets/libs/datatables/datatables.min.js', [ 'jquery' ], '1.10.22', true );
					}

					wp_enqueue_style( 'wpcvt-frontend', WPCVT_URI . 'assets/css/frontend.css', [], WPCVT_VERSION );
					wp_enqueue_script( 'wpcvt-frontend', WPCVT_URI . 'assets/js/frontend.js', [ 'jquery' ], WPCVT_VERSION, true );
					wp_localize_script( 'wpcvt-frontend', 'wpcvt_vars', apply_filters( 'wpcvt_vars', [
							'ajax_url'                => admin_url( 'admin-ajax.php' ),
							'nonce'                   => wp_create_nonce( 'wpcvt-security' ),
							'wc_ajax_url'             => WC_AJAX::get_endpoint( '%%endpoint%%' ),
							'cart_url'                => apply_filters( 'woocommerce_add_to_cart_redirect', wc_get_cart_url(), null ),
							'cart_redirect_after_add' => get_option( 'woocommerce_cart_redirect_after_add' ),
							'layout'                  => self::get_setting( 'layout', 'default' ),
							'datatable_params'        => apply_filters( 'wpcvt_datatable_params', json_encode( apply_filters( 'wpcvt_datatable_params_arr', [
								'pageLength' => 10,
							] ) ) ),
						] )
					);
				}

				function product_data_tabs( $tabs ) {
					$tabs['wpcvt'] = [
						'label'  => esc_html__( 'Variations Table', 'wpc-variations-table' ),
						'target' => 'wpcvt_settings',
						'class'  => [ 'show_if_variable' ]
					];

					return $tabs;
				}

				function product_data_panels() {
					global $post, $thepostid, $product_object;

					if ( $product_object instanceof WC_Product ) {
						$product_id = $product_object->get_id();
					} elseif ( is_numeric( $thepostid ) ) {
						$product_id = $thepostid;
					} elseif ( $post instanceof WP_Post ) {
						$product_id = $post->ID;
					} else {
						$product_id = 0;
					}

					if ( ! $product_id ) {
						?>
                        <div id='wpcvt_settings' class='panel woocommerce_options_panel wpcvt_table'>
                            <p style="padding: 0 12px; color: #c9356e"><?php esc_html_e( 'Product wasn\'t returned.', 'wpc-variations-table' ); ?></p>
                        </div>
						<?php
						return;
					}

					$active = get_post_meta( $product_id, '_wpcvt_active', true ) ?: 'default';
					?>
                    <div id='wpcvt_settings' class='panel woocommerce_options_panel wpcvt_table'>
                        <div class="wpcvt_tr">
                            <div class="wpcvt_td"><?php esc_html_e( 'Active', 'wpc-variations-table' ); ?></div>
                            <div class="wpcvt_td">
                                <div class="wpcvt-active">
                                    <label>
                                        <input name="_wpcvt_active" type="radio" value="default" <?php echo esc_attr( $active === 'default' ? 'checked' : '' ); ?>/>
										<?php esc_html_e( 'Default', 'wpc-variations-table' ); ?>
                                        (<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcvt&tab=settings' ) ); ?>" target="_blank"><?php esc_html_e( 'settings', 'wpc-variations-table' ); ?></a>)
                                    </label> <label>
                                        <input name="_wpcvt_active" type="radio" value="no" <?php echo esc_attr( $active === 'no' ? 'checked' : '' ); ?>/>
										<?php esc_html_e( 'No', 'wpc-variations-table' ); ?>
                                    </label> <label>
                                        <input name="_wpcvt_active" type="radio" value="yes" <?php echo esc_attr( $active === 'yes' ? 'checked' : '' ); ?> disabled/>
										<?php esc_html_e( 'Yes (Overwrite)', 'wpc-variations-table' ); ?>
                                    </label>
                                </div>
                                <div style="color: #c9356e; padding-left: 0; padding-right: 0; margin-top: 10px">You only can use the
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcvt&tab=settings' ) ); ?>" target="_blank">default settings</a> for all products.<br/>Settings at a product basis only available on the Premium Version.
                                    <a href="https://wpclever.net/downloads/wpc-variations-table?utm_source=pro&utm_medium=wpcvt&utm_campaign=wporg" target="_blank">Click here</a> to buy, just $29!
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function process_product_meta( $post_id ) {
					if ( isset( $_POST['_wpcvt_active'] ) ) {
						update_post_meta( $post_id, '_wpcvt_active', sanitize_key( $_POST['_wpcvt_active'] ) );
					}
				}

				function variation_settings( $loop, $variation_data, $variation ) {
					$variation_id = $variation->ID;
					$name         = get_post_meta( $variation_id, 'wpcvt_name', true );
					$image        = get_post_meta( $variation_id, 'wpcvt_image', true );
					$image_id     = get_post_meta( $variation_id, 'wpcvt_image_id', true );

					echo '<div class="form-row form-row-full wpcvt-variation-settings">';
					echo '<label>' . esc_html__( 'WPC Variations Table', 'wpc-variations-table' ) . '</label>';
					echo '<div class="wpcvt-variation-wrap">';

					echo '<p class="form-field form-row">';
					echo '<label>' . esc_html__( 'Custom name', 'wpc-variations-table' ) . '</label>';
					echo '<input type="text" class="wpcvt_name" name="' . esc_attr( 'wpcvt_name[' . $variation_id . ']' ) . '" value="' . esc_attr( $name ) . '"/>';
					echo '</p>';

					echo '<p class="form-field form-row wpcvt_custom_image">';
					echo '<label>' . esc_html__( 'Custom image', 'wpc-variations-table' ) . '</label>';
					echo '<span class="wpcvt_image_selector">';
					echo '<input type="hidden" class="wpcvt_image_id" name="' . esc_attr( 'wpcvt_image_id[' . $variation_id . ']' ) . '" value="' . esc_attr( $image_id ) . '"/>';

					if ( $image_id ) {
						echo '<span class="wpcvt_image_preview">' . wp_get_attachment_image( $image_id ) . '<a class="wpcvt_image_remove button" href="#">' . esc_html__( 'Remove', 'wpc-variations-table' ) . '</a></span>';
					} else {
						echo '<span class="wpcvt_image_preview">' . wc_placeholder_img() . '</span>';
					}

					echo '<a href="#" class="wpcvt_image_add button" rel="' . esc_attr( $variation_id ) . '">' . esc_html__( 'Choose Image', 'wpc-variations-table' ) . '</a>';
					echo '</span>';
					echo '</p>';

					echo '<p class="form-field form-row">';
					echo '<label>' . esc_html__( '- OR - Custom image URL', 'wpc-variations-table' ) . '</label>';
					echo '<input type="url" class="wpcvt_image_url" name="' . esc_attr( 'wpcvt_image[' . $variation_id . ']' ) . '" value="' . esc_attr( $image ) . '"/>';
					echo '</p>';

					do_action( 'wpcvt_variation_settings', $variation_id );

					echo '</div></div>';
				}

				function save_variation_settings( $post_id ) {
					if ( isset( $_POST['wpcvt_name'][ $post_id ] ) ) {
						update_post_meta( $post_id, 'wpcvt_name', sanitize_text_field( $_POST['wpcvt_name'][ $post_id ] ) );
					}

					if ( isset( $_POST['wpcvt_image'][ $post_id ] ) ) {
						update_post_meta( $post_id, 'wpcvt_image', sanitize_url( $_POST['wpcvt_image'][ $post_id ] ) );
					}

					if ( isset( $_POST['wpcvt_image_id'][ $post_id ] ) ) {
						update_post_meta( $post_id, 'wpcvt_image_id', sanitize_text_field( $_POST['wpcvt_image_id'][ $post_id ] ) );
					}
				}

				function variation_get_name( $name, $product ) {
					if ( ( $custom_name = get_post_meta( $product->get_id(), 'wpcvt_name', true ) ) && ! empty( $custom_name ) ) {
						return $custom_name;
					}

					return $name;
				}

				function is_purchasable( $product ) {
					return $product->is_purchasable() && $product->is_in_stock() && $product->has_enough_stock( 1 );
				}

				function hide_variations_form() {
					global $product;

					if ( ! $product || ! $product->is_type( 'variable' ) ) {
						return;
					}

					$product_id = $product->get_id();
					$active     = self::get_setting( 'active', 'yes' );
					$_active    = get_post_meta( $product_id, '_wpcvt_active', true ) ?: 'default';

					if ( $_active === 'yes' || ( $_active === 'default' && $active === 'yes' ) ) {
						echo '<div class="wpcvt-hide-variations-form"></div>';
					}
				}

				function shortcode() {
					ob_start();
					self::variations_table();

					return ob_get_clean();
				}

				function variations_table() {
					global $product;
					$global_product = $product;

					if ( ! $product || ! $product->is_type( 'variable' ) ) {
						return;
					}

					$product_id = $product->get_id();
					$active     = self::get_setting( 'active', 'yes' );
					$_active    = get_post_meta( $product_id, '_wpcvt_active', true ) ?: 'default';

					if ( $_active === 'yes' || ( $_active === 'default' && $active === 'yes' ) ) {
						$product_id          = $product->get_id();
						$dropdown_attributes = get_post_meta( $product_id, '_wpcvt_dropdown', true ) ?: [];

						$layout             = self::get_setting( 'layout', 'default' );
						$variation_name     = self::get_setting( 'variation_name', 'formatted' );
						$product_name       = self::get_setting( 'product_name', 'yes' );
						$link               = self::get_setting( 'link', 'yes' ) === 'yes';
						$show_atc           = self::get_setting( 'show_atc', 'each' );
						$show_image         = self::get_setting( 'show_image', 'yes' );
						$show_price         = self::get_setting( 'show_price', 'yes' );
						$show_availability  = self::get_setting( 'show_availability', 'yes' );
						$show_description   = self::get_setting( 'show_description', 'yes' );
						$hide_unpurchasable = self::get_setting( 'hide_unpurchasable', 'no' );
						$before_text        = self::get_setting( 'before_text', '' );
						$after_text         = self::get_setting( 'after_text', '' );

						$children = $product->get_children();

						if ( ! empty( $children ) ) {
							// build children data
							$children_data = [];

							foreach ( $children as $child ) {
								$child_product = wc_get_product( $child );

								if ( ! $child_product || ! $child_product->variation_is_visible() ) {
									continue;
								}

								if ( ( $hide_unpurchasable === 'yes' ) && ! self::is_purchasable( $child_product ) ) {
									continue;
								}

								$attrs         = [];
								$product_attrs = $product->get_attributes();
								$child_attrs   = $child_product->get_attributes();

								foreach ( $child_attrs as $k => $a ) {
									if ( $a === '' ) {
										// variation with any attributes
										if ( in_array( $k, $dropdown_attributes ) ) {
											continue;
										}

										if ( $product_attrs[ $k ]->is_taxonomy() ) {
											foreach ( $product_attrs[ $k ]->get_terms() as $term ) {
												$attrs[ 'attribute_' . $k ][] = $term->slug;
											}
										} else {
											// custom attribute
											foreach ( $product_attrs[ $k ]->get_options() as $option ) {
												$attrs[ 'attribute_' . $k ][] = $option;
											}
										}
									} else {
										$attrs[ 'attribute_' . $k ][] = $a;
									}
								}

								$attrs = wpcvt_combinations( $attrs );

								foreach ( $attrs as $attr ) {
									$children_data[] = [
										'id'      => $child,
										'product' => $child_product,
										'attrs'   => $attr
									];
								}
							}

							if ( ! empty( $children_data ) ) {
								do_action( 'wpcvt_wrap_above', $children_data, $product );

								echo '<div class="' . esc_attr( 'wpcvt-wrap wpcvt-wrap-' . $product_id ) . '">';

								do_action( 'wpcvt_variations_above', $children_data, $product );

								if ( ! empty( $before_text ) ) {
									echo '<div class="wpcvt-before-text">' . do_shortcode( $before_text ) . '</div>';
								}

								if ( $layout === 'datatables' ) {
									// datatables
									$dt_localization = array_replace( self::$dt_localization, array_filter( self::$localization ) );
									echo '<table class="wpcvt-variations-table" data-language="' . esc_attr( htmlspecialchars( json_encode( $dt_localization ), ENT_QUOTES, 'UTF-8' ) ) . '">';
									do_action( 'wpcvt_variations_before', $children_data, $product );

									echo '<thead><tr>';

									if ( $show_image === 'yes' ) {
										echo '<th data-searchable="false">' . esc_html( self::localization( 'image', esc_html__( 'Image', 'wpc-variations-table' ) ) ) . '</th>';
									}

									echo '<th>' . esc_html( self::localization( 'name', esc_html__( 'Name', 'wpc-variations-table' ) ) ) . '</th>';
									echo '<th data-orderable="false" data-searchable="false">' . esc_html( self::localization( 'action', esc_html__( 'Action', 'wpc-variations-table' ) ) ) . '</th>';

									echo '</tr></thead>';

									echo '<tbody>';
									foreach ( $children_data as $child_data ) {
										$child_id      = $child_data['id'];
										$child_product = $product = $child_data['product'];
										$child_attrs   = htmlspecialchars( json_encode( $child_data['attrs'] ), ENT_QUOTES, 'UTF-8' );

										// get name
										if ( ( $custom_name = get_post_meta( $child_id, 'wpcvt_name', true ) ) && ! empty( $custom_name ) ) {
											$child_name = $custom_name;
										} else {
											$child_name_arr = [];

											foreach ( $child_data['attrs'] as $k => $a ) {
												if ( $t = get_term_by( 'slug', $a, str_replace( 'attribute_', '', $k ) ) ) {
													$n = $t->name;
												} elseif ( $t = get_term_by( 'name', $a, str_replace( 'attribute_', '', $k ) ) ) {
													$n = $t->name;
												} else {
													$n = $a;
												}

												if ( $variation_name === 'formatted_label' ) {
													$child_name_arr[] = wc_attribute_label( str_replace( 'attribute_', '', $k ), $global_product ) . ': ' . $n;
												} else {
													$child_name_arr[] = $n;
												}
											}

											$child_name = implode( ', ', $child_name_arr );

											if ( $product_name === 'yes' ) {
												$child_name = $global_product->get_name() . ' – ' . $child_name;
											}
										}

										$child_name = apply_filters( 'wpcvt_variation_product_name', $child_name, $child_product, $global_product );

										// get image
										if ( $child_product->get_image_id() ) {
											$child_image     = wp_get_attachment_image_src( $child_product->get_image_id() );
											$child_image_src = $child_image[0];
										} else {
											$child_image_src = wc_placeholder_img_src();
										}

										// custom image
										if ( $child_image_id = get_post_meta( $child_id, 'wpcvt_image_id', true ) ) {
											$child_image     = wp_get_attachment_image_src( absint( $child_image_id ) );
											$child_image_src = $child_image[0];
										} elseif ( get_post_meta( $child_id, 'wpcvt_image', true ) ) {
											$child_image_src = esc_url( get_post_meta( $child_id, 'wpcvt_image', true ) );
										}

										$child_image_src = esc_url( apply_filters( 'wpcvt_variation_image_src', $child_image_src, $child_product ) );
										$child_images    = array_filter( explode( ',', get_post_meta( $child_id, 'wpcvi_images', true ) ) );
										$data_attrs      = apply_filters( 'wpcvt_data_attributes', [
											'id'          => $child_id,
											'pid'         => $product_id,
											'sku'         => $child_product->get_sku(),
											'atc'         => $show_atc,
											'purchasable' => self::is_purchasable( $child_product ) ? 'yes' : 'no',
											'attrs'       => $child_attrs,
											'images'      => class_exists( 'WPCleverWpcvi' ) && ! empty( $child_images ) ? 'yes' : 'no'
										], $child_product );

										echo '<tr class="wpcvt-variation" ' . self::data_attributes( $data_attrs ) . '>';

										do_action( 'wpcvt_variation_before', $child_product, $global_product );

										if ( $show_image === 'yes' ) {
											if ( $link && class_exists( 'WPCleverWoosq' ) ) {
												echo '<td class="wpcvt-variation-image" data-order="' . esc_attr( $child_id ) . '">' . apply_filters( 'wpcvt_variation_image', '<a class="woosq-link" href="javascript:void(0);" data-id="' . esc_attr( $child_id ) . '"><img src="' . esc_url( $child_image_src ) . '"/></a>', $child_product, $global_product ) . '</td>';
											} else {
												echo '<td class="wpcvt-variation-image" data-order="' . esc_attr( $child_id ) . '">' . apply_filters( 'wpcvt_variation_image', '<img src="' . esc_url( $child_image_src ) . '"/>', $child_product, $global_product ) . '</td>';
											}
										}

										if ( apply_filters( 'wpcvt_search_by_name_only', false ) ) {
											echo '<td class="wpcvt-variation-info" data-search="' . esc_attr( $child_name ) . '">';
										} else {
											echo '<td class="wpcvt-variation-info">';
										}

										do_action( 'wpcvt_variation_info_before', $child_product, $global_product );

										if ( $link && class_exists( 'WPCleverWoosq' ) ) {
											echo '<div class="wpcvt-variation-name">' . apply_filters( 'wpcvt_variation_name', '<a class="woosq-link" href="javascript:void(0);" data-id="' . esc_attr( $child_id ) . '">' . $child_name . '</a>', $child_product, $global_product ) . '</div>';
										} else {
											echo '<div class="wpcvt-variation-name">' . apply_filters( 'wpcvt_variation_name', $child_name, $child_product, $global_product ) . '</div>';
										}

										if ( $show_price === 'yes' ) {
											echo '<div class="wpcvt-variation-price">' . apply_filters( 'wpcvt_variation_price', $child_product->get_price_html(), $child_product, $global_product ) . '</div>';
										}

										if ( $show_availability === 'yes' ) {
											echo '<div class="wpcvt-variation-availability">' . apply_filters( 'wpcvt_variation_availability', wc_get_stock_html( $child_product ), $child_product, $global_product ) . '</div>';
										}

										if ( $show_description === 'yes' ) {
											echo '<div class="wpcvt-variation-description">' . apply_filters( 'wpcvt_variation_description', $child_product->get_description(), $child_product, $global_product ) . '</div>';
										}

										do_action( 'wpcvt_variation_info_after', $child_product, $global_product );

										echo '</td>';

										echo '<td class="wpcvt-variation-actions">';

										do_action( 'wpcvt_variation_actions_before', $child_product, $global_product );

										if ( $show_atc === 'each' ) {
											self::add_to_cart( $child_data );
										} elseif ( $show_atc === 'all' ) {
											self::quantity_input( $child_data );
										}

										do_action( 'wpcvt_variation_actions_after', $child_product, $global_product );

										echo '</td>';

										do_action( 'wpcvt_variation_after', $child_product, $global_product );

										echo '</tr>';
									}
									echo '</tbody>';

									do_action( 'wpcvt_variations_after', $children_data, $global_product );
									echo '</table>';
								} else {
									echo '<div class="wpcvt-variations">';

									do_action( 'wpcvt_variations_before', $children_data, $product );

									foreach ( $children_data as $child_data ) {
										$child_id      = $child_data['id'];
										$child_product = $product = $child_data['product'];
										$child_attrs   = htmlspecialchars( json_encode( $child_data['attrs'] ), ENT_QUOTES, 'UTF-8' );

										// get name
										if ( ( $custom_name = get_post_meta( $child_id, 'wpcvt_name', true ) ) && ! empty( $custom_name ) ) {
											$child_name = $custom_name;
										} else {
											$child_name_arr = [];

											foreach ( $child_data['attrs'] as $k => $a ) {
												if ( $t = get_term_by( 'slug', $a, str_replace( 'attribute_', '', $k ) ) ) {
													$n = $t->name;
												} elseif ( $t = get_term_by( 'name', $a, str_replace( 'attribute_', '', $k ) ) ) {
													$n = $t->name;
												} else {
													$n = $a;
												}

												if ( $variation_name === 'formatted_label' ) {
													$child_name_arr[] = wc_attribute_label( str_replace( 'attribute_', '', $k ), $global_product ) . ': ' . $n;
												} else {
													$child_name_arr[] = $n;
												}
											}

											$child_name = implode( ', ', $child_name_arr );

											if ( $product_name === 'yes' ) {
												$child_name = $global_product->get_name() . ' – ' . $child_name;
											}
										}

										$child_name = apply_filters( 'wpcvt_variation_product_name', $child_name, $child_product, $global_product );

										// get image
										if ( $child_product->get_image_id() ) {
											$child_image     = wp_get_attachment_image_src( $child_product->get_image_id() );
											$child_image_src = $child_image[0];
										} else {
											$child_image_src = wc_placeholder_img_src();
										}

										// custom image
										if ( $child_image_id = get_post_meta( $child_id, 'wpcvt_image_id', true ) ) {
											$child_image     = wp_get_attachment_image_src( absint( $child_image_id ) );
											$child_image_src = $child_image[0];
										} elseif ( get_post_meta( $child_id, 'wpcvt_image', true ) ) {
											$child_image_src = esc_url( get_post_meta( $child_id, 'wpcvt_image', true ) );
										}

										$child_image_src = esc_url( apply_filters( 'wpcvt_variation_image_src', $child_image_src, $child_product ) );
										$child_images    = array_filter( explode( ',', get_post_meta( $child_id, 'wpcvi_images', true ) ) );
										$data_attrs      = apply_filters( 'wpcvt_data_attributes', [
											'id'          => $child_id,
											'pid'         => $product_id,
											'sku'         => $child_product->get_sku(),
											'atc'         => $show_atc,
											'purchasable' => self::is_purchasable( $child_product ) ? 'yes' : 'no',
											'attrs'       => $child_attrs,
											'images'      => class_exists( 'WPCleverWpcvi' ) && ! empty( $child_images ) ? 'yes' : 'no'
										], $child_product );

										echo '<div class="wpcvt-variation" ' . self::data_attributes( $data_attrs ) . '>';

										do_action( 'wpcvt_variation_before', $child_product, $global_product );

										if ( $show_image === 'yes' ) {
											if ( $link && class_exists( 'WPCleverWoosq' ) ) {
												echo '<div class="wpcvt-variation-image">' . apply_filters( 'wpcvt_variation_image', '<a class="woosq-link" href="javascript:void(0);" data-id="' . esc_attr( $child_id ) . '"><img src="' . esc_url( $child_image_src ) . '"/></a>', $child_product, $global_product ) . '</div>';
											} else {
												echo '<div class="wpcvt-variation-image">' . apply_filters( 'wpcvt_variation_image', '<img src="' . esc_url( $child_image_src ) . '"/>', $child_product, $global_product ) . '</div>';
											}
										}

										echo '<div class="wpcvt-variation-info">';

										do_action( 'wpcvt_variation_info_before', $child_product, $global_product );

										if ( $link && class_exists( 'WPCleverWoosq' ) ) {
											echo '<div class="wpcvt-variation-name">' . apply_filters( 'wpcvt_variation_name', '<a class="woosq-link" href="javascript:void(0);" data-id="' . esc_attr( $child_id ) . '">' . $child_name . '</a>', $child_product, $global_product ) . '</div>';
										} else {
											echo '<div class="wpcvt-variation-name">' . apply_filters( 'wpcvt_variation_name', $child_name, $child_product, $global_product ) . '</div>';
										}

										if ( $show_price === 'yes' ) {
											echo '<div class="wpcvt-variation-price">' . apply_filters( 'wpcvt_variation_price', $child_product->get_price_html(), $child_product, $global_product ) . '</div>';
										}

										if ( $show_availability === 'yes' ) {
											echo '<div class="wpcvt-variation-availability">' . apply_filters( 'wpcvt_variation_availability', wc_get_stock_html( $child_product ), $child_product, $global_product ) . '</div>';
										}

										if ( $show_description === 'yes' ) {
											echo '<div class="wpcvt-variation-description">' . apply_filters( 'wpcvt_variation_description', $child_product->get_description(), $child_product, $global_product ) . '</div>';
										}

										do_action( 'wpcvt_variation_info_after', $child_product, $global_product );

										echo '</div>';

										echo '<div class="wpcvt-variation-actions">';

										do_action( 'wpcvt_variation_actions_before', $child_product, $global_product );

										if ( $show_atc === 'each' ) {
											self::add_to_cart( $child_data );
										} elseif ( $show_atc === 'all' ) {
											self::quantity_input( $child_data );
										}

										do_action( 'wpcvt_variation_actions_after', $child_product, $global_product );

										echo '</div>';

										do_action( 'wpcvt_variation_after', $child_product, $global_product );

										echo '</div>';
									}

									do_action( 'wpcvt_variations_after', $children_data, $global_product );

									echo '</div>';
								}

								if ( $show_atc === 'all' ) {
									echo '<div class="wpcvt-actions">';
									echo '<button type="button" class="single_add_to_cart_button wpcvt_add_to_cart_button wpcvt_atc_btn wpcvt_btn button alt">' . sprintf( self::localization( 'add_all_to_cart',  /* translators: count */ esc_html__( 'Add all to cart (%s)', 'wpc-variations-table' ) ), '<span class="wpcvt_atc_count"></span>' ) . '</button>';
									echo '</div>';
								}

								if ( ! empty( $after_text ) ) {
									echo '<div class="wpcvt-after-text">' . do_shortcode( $after_text ) . '</div>';
								}

								do_action( 'wpcvt_variations_under', $children_data, $global_product );

								echo '</div>';

								do_action( 'wpcvt_wrap_under', $children_data, $global_product );
							}
						}
					}

					$product = $global_product;
				}

				function data_attributes( $attrs ) {
					$attrs_arr = [];

					foreach ( $attrs as $key => $attr ) {
						$attrs_arr[] = 'data-' . sanitize_title( $key ) . '="' . esc_attr( $attr ) . '"';
					}

					return implode( ' ', $attrs_arr );
				}

				function add_to_cart( $variation_data ) {
					$variation       = $variation_data['product'];
					$variation_id    = $variation_data['id'];
					$variation_attrs = $variation->get_attributes();
					$product_id      = $variation->get_parent_id();
					$product_object  = wc_get_product( $product_id );
					$product_attrs   = $product_object->get_attributes();
					$dropdown_attrs  = get_post_meta( $product_id, '_wpcvt_dropdown', true ) ?: [];

					do_action( 'wpcvt_before_add_to_cart', $variation );

					if ( class_exists( 'WPCleverWpcev' ) && ( $btn = WPCleverWpcev::get_btn( $variation_id ) ) ) {
						// WPC External Variations
						echo wp_kses_post( $btn );
					} elseif ( $variation->is_in_stock() && $variation->is_purchasable() ) {
						?>
                        <div class="wpcvt-atc">
                            <form class="cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $variation->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>
                                <input type="hidden" name="add-to-cart" value="<?php echo absint( $product_id ); ?>"/>
                                <input type="hidden" name="product_id" value="<?php echo absint( $product_id ); ?>"/>
                                <input type="hidden" name="variation_id" value="<?php echo absint( $variation_id ); ?>"/>
								<?php
								// attributes
								foreach ( $variation_data['attrs'] as $k => $a ) {
									echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $a ) . '"/>';
								}

								// dropdown attributes
								foreach ( $product_attrs as $k => $a ) {
									if ( in_array( $k, $dropdown_attrs ) && isset( $variation_attrs[ $k ] ) && ( $variation_attrs[ $k ] === '' ) ) {
										echo '<div class="wpcvt-dropdown-attribute">';
										echo '<label>' . esc_html( wc_attribute_label( $a->get_name() ) ) . '</label>';
										echo '<select name="attribute_' . esc_attr( $k ) . '">';

										if ( $a->is_taxonomy() ) {
											foreach ( $a->get_terms() as $o ) {
												echo '<option value="' . esc_attr( $o->slug ) . '">' . esc_html( apply_filters( 'woocommerce_variation_option_name', $o->name, $o, $a->get_name(), $product_object ) ) . '</option>';
											}
										} else {
											foreach ( $a->get_options() as $o ) {
												echo '<option value="' . esc_attr( $o ) . '">' . esc_html( apply_filters( 'woocommerce_variation_option_name', $o, null, $a->get_name(), $product_object ) ) . '</option>';
											}
										}

										echo '</select>';
										echo '</div>';
									}
								}
								?>
                                <div class="wpcvt-add-to-cart">
									<?php
									do_action( 'woocommerce_before_add_to_cart_quantity' );

									woocommerce_quantity_input(
										[
											'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $variation->get_min_purchase_quantity(), $variation ),
											'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $variation->get_max_purchase_quantity(), $variation ),
											'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $variation->get_min_purchase_quantity(),
										],
										$variation
									);

									do_action( 'woocommerce_after_add_to_cart_quantity' );
									?>
                                    <button type="submit" class="single_add_to_cart_button button alt">
										<?php echo esc_html( $variation->single_add_to_cart_text() ); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
						<?php
					}

					do_action( 'wpcvt_after_add_to_cart', $variation );
				}

				function quantity_input( $variation_data ) {
					$variation = $variation_data['product'];

					echo '<div class="wpcvt-quantity">';
					do_action( 'wpcvt_quantity_before', $variation );

					$input_value = apply_filters( 'wpcvt_qty_input_value', 0, $variation );
					$min_value   = apply_filters( 'wpcvt_qty_min_value', 0, $variation );
					$max_value   = apply_filters( 'wpcvt_qty_max_value', - 1, $variation );

					woocommerce_quantity_input( [
						'input_value' => $input_value,
						'min_value'   => $min_value,
						'max_value'   => $max_value,
						'wpcvt_qty'   => [
							'input_value' => $input_value,
							'min_value'   => $min_value,
							'max_value'   => $max_value
						],
						'classes'     => apply_filters( 'wpcvt_qty_classes', [
							'input-text',
							'wpcvt-qty',
							'qty',
							'text'
						] ),
						'input_name'  => 'wpcvt_qty_' . uniqid()
					], $variation );

					do_action( 'wpcvt_quantity_after', $variation );
					echo '</div>';
				}

				function get_dropdown_attributes( $post_id ) {
					$dropdown_attributes = (array) ( get_post_meta( $post_id, '_wpcvt_dropdown', true ) ?: [] );

					if ( $product_object = wc_get_product( $post_id ) ) {
						$variation_attributes = $product_object->get_attributes();

						if ( ! empty( $variation_attributes ) ) {
							foreach ( $variation_attributes as $variation_attribute ) {
								echo '<input type="checkbox" value="' . esc_attr( sanitize_title( $variation_attribute->get_name() ) ) . '" name="_wpcvt_dropdown[]" ' . ( in_array( sanitize_title( $variation_attribute->get_name() ), $dropdown_attributes ) ? 'checked' : '' ) . '/> ' . esc_html( wc_attribute_label( $variation_attribute->get_name() ) ) . ' &nbsp; ';
							}
						}
					}
				}

				function ajax_dropdown_attributes() {
					$post_id = absint( sanitize_text_field( $_POST['pid'] ) );
					self::get_dropdown_attributes( $post_id );
					wp_die();
				}

				function ajax_add_to_cart() {
					if ( ! apply_filters( 'wpcvt_disable_security_check', false, 'add_to_cart' ) ) {
						if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcvt-security' ) ) {
							die( 'Permissions check failed!' );
						}
					}

					if ( ! empty( $_POST['variations'] ) && is_array( $_POST['variations'] ) ) {
						foreach ( $_POST['variations'] as $variation ) {
							$product_id        = absint( $variation['pid'] );
							$quantity          = (float) $variation['qty'];
							$variation_id      = absint( $variation['id'] );
							$variation         = (array) $variation['attrs'];
							$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variation );

							if ( $passed_validation ) {
								WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
							}
						}

						WC_AJAX::get_refreshed_fragments();
					}

					wp_die();
				}

				function wpcsm_locations( $locations ) {
					$locations['WPC Variations Table'] = [
						'wpcvt_variations_above'         => esc_html__( 'Before variations wrap', 'wpc-variations-table' ),
						'wpcvt_variations_under'         => esc_html__( 'After variations wrap', 'wpc-variations-table' ),
						'wpcvt_variations_before'        => esc_html__( 'Before variations list', 'wpc-variations-table' ),
						'wpcvt_variations_after'         => esc_html__( 'After variations list', 'wpc-variations-table' ),
						'wpcvt_variation_before'         => esc_html__( 'Before variation', 'wpc-variations-table' ),
						'wpcvt_variation_after'          => esc_html__( 'After variation', 'wpc-variations-table' ),
						'wpcvt_variation_info_before'    => esc_html__( 'Before variation info', 'wpc-variations-table' ),
						'wpcvt_variation_info_after'     => esc_html__( 'After variation info', 'wpc-variations-table' ),
						'wpcvt_variation_actions_before' => esc_html__( 'Before variation actions', 'wpc-variations-table' ),
						'wpcvt_variation_actions_after'  => esc_html__( 'After variation actions', 'wpc-variations-table' ),
					];

					return $locations;
				}

				function duplicate_variation( $old_variation_id, $new_variation_id ) {
					if ( $name = get_post_meta( $old_variation_id, 'wpcvt_name', true ) ) {
						update_post_meta( $new_variation_id, 'wpcvt_name', $name );
					}

					if ( $image = get_post_meta( $old_variation_id, 'wpcvt_image', true ) ) {
						update_post_meta( $new_variation_id, 'wpcvt_image', $image );
					}

					if ( $image_id = get_post_meta( $old_variation_id, 'wpcvt_image_id', true ) ) {
						update_post_meta( $new_variation_id, 'wpcvt_image_id', $image_id );
					}
				}

				function bulk_update_variation( $variation_id, $fields ) {
					if ( ! empty( $fields['wpcvt_name'] ) ) {
						update_post_meta( $variation_id, 'wpcvt_name', sanitize_text_field( $fields['wpcvt_name'] ) );
					}

					if ( ! empty( $fields['wpcvt_image'] ) ) {
						update_post_meta( $variation_id, 'wpcvt_image', sanitize_text_field( $fields['wpcvt_image'] ) );
					}

					if ( ! empty( $fields['wpcvt_image_id'] ) ) {
						update_post_meta( $variation_id, 'wpcvt_image_id', sanitize_text_field( $fields['wpcvt_image_id'] ) );
					}
				}
			}

			return WPCleverWpcvt::instance();
		}

		return null;
	}
}

if ( ! function_exists( 'wpcvt_combinations' ) ) {
	function wpcvt_combinations( $arrays ) {
		$result = [ [] ];

		foreach ( $arrays as $property => $property_values ) {
			$tmp = [];

			foreach ( $result as $result_item ) {
				foreach ( $property_values as $property_value ) {
					$tmp[] = array_merge( $result_item, [ $property => $property_value ] );
				}
			}

			$result = $tmp;
		}

		return $result;
	}
}

if ( ! function_exists( 'wpcvt_notice_wc' ) ) {
	function wpcvt_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Variations Table</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
