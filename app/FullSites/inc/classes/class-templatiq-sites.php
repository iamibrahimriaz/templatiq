<?php
/**
 * Templatiq Sites
 *
 * @since  1.0.0
 * @package Templatiq Sites
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Templatiq_Sites' ) ):

	/**
	 * Templatiq_Sites
	 */
	class Templatiq_Sites {

		/**
		 * API Domain name
		 *
		 * @var (String) URL
		 */
		public $api_domain;

		/**
		 * API URL which is used to get the response from.
		 *
		 * @since  1.0.0
		 * @var (String) URL
		 */
		public $api_url;

		/**
		 * Search API URL which is used to get the response from.
		 *
		 * @since  2.0.0
		 * @var (String) URL
		 */
		public $search_analytics_url;

		/**
		 * Import Analytics API URL
		 *
		 * @since  3.1.4
		 * @var (String) URL
		 */
		public $import_analytics_url;

		/**
		 * API URL which is used to get the response from Pixabay.
		 *
		 * @since  2.0.0
		 * @var (String) URL
		 */
		public $pixabay_url;

		/**
		 * API Key which is used to get the response from Pixabay.
		 *
		 * @since  2.0.0
		 * @var (String) URL
		 */
		public $pixabay_api_key;

		/**
		 * Instance of Templatiq_Sites
		 *
		 * @since  1.0.0
		 * @var (Object) Templatiq_Sites
		 */
		private static $instance = null;

		/**
		 * Localization variable
		 *
		 * @since  2.0.0
		 * @var (Array) $local_vars
		 */
		public static $local_vars = [];

		/**
		 * Localization variable
		 *
		 * @since  2.0.0
		 * @var (Array) $wp_upload_url
		 */
		public $wp_upload_url = '';

		/**
		 * Ajax
		 *
		 * @since  2.6.20
		 * @var (Array) $ajax
		 */
		private $ajax = [];

		/**
		 * Instance of Templatiq_Sites.
		 *
		 * @since  1.0.0
		 *
		 * @return object Class object.
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor.
		 *
		 * @since  1.0.0
		 */
		private function __construct() {

			$this->set_api_url();

			$this->includes();

			add_action( 'admin_enqueue_scripts', [$this, 'admin_enqueue'], 99 );
			add_action( 'wp_enqueue_scripts', [$this, 'image_search_scripts'] );
			add_action( 'elementor/editor/footer', [$this, 'insert_templates'] );
			add_action( 'admin_footer', [$this, 'insert_image_templates'] );
			add_action( 'customize_controls_print_footer_scripts', [$this, 'insert_image_templates'] );
			add_action( 'wp_footer', [$this, 'insert_image_templates_bb_and_brizy'] );
			add_action( 'elementor/editor/footer', [$this, 'register_widget_scripts'], 99 );
			add_action( 'elementor/editor/before_enqueue_scripts', [$this, 'popup_styles'] );
			add_action( 'elementor/preview/enqueue_styles', [$this, 'popup_styles'] );
			add_action( 'admin_notices', [$this, 'check_filesystem_access_notice'] );

			// AJAX.
			$this->ajax = [
				'astra-required-plugins'                  => 'required_plugin',
				'astra-required-plugin-activate'          => 'required_plugin_activate',
				'templatiq-sites-backup-settings'         => 'backup_settings',
				'templatiq-sites-set-reset-data'          => 'get_reset_data',
				'templatiq-sites-reset-terms-and-forms'   => 'reset_terms_and_forms',
				'templatiq-sites-reset-posts'             => 'reset_posts',
				'templatiq-sites-activate-theme'          => 'activate_theme',
				'templatiq-sites-create-template'         => 'create_template',
				'templatiq-sites-create-image'            => 'create_image',
				'templatiq-sites-get-deleted-post-ids'    => 'get_deleted_post_ids',
				'templatiq-sites-search-images'           => 'search_images',
				'templatiq-sites-favorite'                => 'add_to_favorite',
				'templatiq-sites-api-request'             => 'api_request',
				'templatiq-sites-elementor-api-request'   => 'elementor_api_request',
				'templatiq-sites-elementor-flush-request' => 'elementor_flush_request',
				'astra-page-elementor-insert-page'        => 'elementor_process_import_for_page',
				'templatiq-sites-update-subscription'     => 'update_subscription',
				'templatiq-sites-update-analytics'        => 'update_analytics',
				'templatiq-sites-filesystem-permission'   => 'filesystem_permission',
				'templatiq-sites-generate-analytics-lead' => 'push_to_import_analytics',
			];

			foreach ( $this->ajax as $ajax_hook => $ajax_callback ) {
				add_action( 'wp_ajax_' . $ajax_hook, [$this, $ajax_callback] );
			}

			add_action( 'delete_attachment', [$this, 'delete_templatiq_images'] );
			add_filter( 'heartbeat_received', [$this, 'search_push'], 10, 2 );
			add_filter( 'status_header', [$this, 'status_header'], 10, 4 );
			add_filter( 'wp_php_error_message', [$this, 'php_error_message'], 10, 2 );
			add_filter( 'wp_import_post_data_processed', [$this, 'wp_slash_after_xml_import'], 99, 2 );
			add_filter( 'ast_block_templates_authorization_url_param', [$this, 'add_auth_url_param'] );
		}

		/**
		 * Set plugin param for auth URL.
		 *
		 * @param array $url_param url parameters.
		 *
		 * @since  3.5.0
		 */
		public function add_auth_url_param( $url_param ) {

			$url_param['plugin'] = 'starter-templates';

			return $url_param;
		}

		/**
		 * Get plugin status
		 *
		 * @since 3.5.0
		 *
		 * @param  string $plugin_init_file Plugin init file.
		 * @return string
		 */
		public function get_plugin_status( $plugin_init_file ) {

			$installed_plugins = get_plugins();

			if ( ! isset( $installed_plugins[$plugin_init_file] ) ) {
				return 'not-installed';
			} elseif ( is_plugin_active( $plugin_init_file ) ) {
			return 'active';
		} else {
			return 'inactive';
		}
	}

	/**
	 * Add slashes while importing the XML with WordPress Importer v2.
	 *
	 * @param array $postdata Processed Post data.
	 * @param array $data Post data.
	 */
	public function wp_slash_after_xml_import( $postdata, $data ) {
		return wp_slash( $postdata );
	}

	/**
	 * Check is Starter Templates AJAX request.
	 *
	 * @since 2.7.0
	 * @return boolean
	 */
	public function is_starter_templates_request() {

		if ( isset( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], array_keys( $this->ajax ), true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Fetching GET parameter, no nonce associated with this action.
			return true;
		}

		return false;
	}

	/**
	 * Filters the message that the default PHP error template displays.
	 *
	 * @since 2.7.0
	 *
	 * @param string $message HTML error message to display.
	 * @param array  $error   Error information retrieved from `error_get_last()`.
	 * @return mixed
	 */
	public function php_error_message( $message, $error ) {

		if ( ! $this->is_starter_templates_request() ) {
			return $message;
		}

		if ( empty( $error ) ) {
			return $message;
		}

		$message = isset( $error['message'] ) ? $error['message'] : $message;

		return $message;
	}

	/**
	 * Filters an HTTP status header.
	 *
	 * @since 2.6.20
	 *
	 * @param string $status_header HTTP status header.
	 * @param int    $code          HTTP status code.
	 * @param string $description   Description for the status code.
	 * @param string $protocol      Server protocol.
	 *
	 * @return mixed
	 */
	public function status_header( $status_header, $code, $description, $protocol ) {

		if ( ! $this->is_starter_templates_request() ) {
			return $status_header;
		}

		$error = error_get_last();
		if ( empty( $error ) ) {
			return $status_header;
		}

		$message = isset( $error['message'] ) ? $error['message'] : $description;

		return "$protocol $code $message";
	}

	/**
	 * Update Analytics Optin/Optout
	 */
	public function update_analytics() {

		check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'You are not allowed to perform this action', 'templatiq-sites' );
		}

		$optin_answer = isset( $_POST['data'] ) ? sanitize_text_field( $_POST['data'] ) : 'no';
		$optin_answer = 'yes' === $optin_answer ? 'yes' : 'no';

		update_site_option( 'bsf_analytics_optin', $optin_answer );

		wp_send_json_success();
	}

	/**
	 * Update Subscription
	 */
	public function update_subscription() {

		check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'You are not allowed to perform this action', 'templatiq-sites' );
		}

		$arguments = isset( $_POST['data'] ) ? array_map( 'sanitize_text_field', json_decode( stripslashes( $_POST['data'] ), true ) ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Already sanitized using `array_map` and `sanitize_text_field`.

		// Page Builder mapping.
		$page_builder_mapping = [
			'Elementor'      => 1,
			'Beaver Builder' => 2,
			'Brizy'          => 3,
			'Gutenberg'      => 4,
		];
		$arguments['PAGE_BUILDER'] = isset( $page_builder_mapping[$arguments['PAGE_BUILDER']] ) ? $page_builder_mapping[$arguments['PAGE_BUILDER']] : '';

		$url = apply_filters( 'templatiq_sites_subscription_url', $this->api_domain . 'wp-json/starter-templates/v1/subscribe/' );

		$args = [
			'timeout' => 30,
			'body'    => $arguments,
		];

		$response = wp_remote_post( $url, $args );

		if ( ! is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 200 ) {
			$response = json_decode( wp_remote_retrieve_body( $response ), true );

			// Successfully subscribed.
			if ( isset( $response['success'] ) && $response['success'] ) {
				update_user_meta( get_current_user_ID(), 'templatiq-sites-subscribed', 'yes' );
			}
		}
		wp_send_json_success( $response );
	}

	/**
	 * Push Data to Search API.
	 *
	 * @since  2.0.0
	 * @param Object $response Response data object.
	 * @param Object $data Data object.
	 *
	 * @return array Search response.
	 */
	public function search_push( $response, $data ) {

		// If we didn't receive our data, don't send any back.
		if ( empty( $data['ast-sites-search-terms'] ) ) {
			return $response;
		}

		$args = [
			'timeout'   => 3,
			'blocking'  => true,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			'body'      => [
				'search'  => $data['ast-sites-search-terms'],
				'builder' => isset( $data['ast-sites-builder'] ) ? $data['ast-sites-builder'] : 'gutenberg',
				'url'     => esc_url( site_url() ),
				'type'    => 'templatiq-sites',
			],
		];
		$result                             = wp_remote_post( $this->search_analytics_url, $args );
		$response['ast-sites-search-terms'] = wp_remote_retrieve_body( $result );

		return $response;
	}

	/**
	 * Push Data to Import Analytics API.
	 *
	 * @since  3.1.4
	 */
	public function push_to_import_analytics() {

		check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'You are not allowed to perform this action', 'templatiq-sites' );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( 0 === $id ) {
			wp_send_json_error(
				[
					/* translators: %d is the Template ID. */
					'message' => sprintf( __( 'Invalid Template ID - %d', 'templatiq-sites' ), $id ),
					'code'    => 'Error',
				]
			);
		}

		$data = [
			'id'              => $id,
			'import_attempts' => isset( $_POST['try-again-count'] ) ? absint( $_POST['try-again-count'] ) : 0,
			'import_status'   => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'true',
			'type'            => isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'templatiq-sites',
			'page_builder'    => isset( $_POST['page-builder'] ) ? sanitize_text_field( $_POST['page-builder'] ) : 'gutenberg',
		];

		$result = Templatiq_Sites_Reporting::get_instance()->report( $data );

		if ( $result['status'] ) {
			delete_option( 'templatiq_sites_has_sent_error_report' );
			delete_option( 'templatiq_sites_cached_import_error' );
			wp_send_json_success( $result['data'] );
		}

		wp_send_json_error( $result['data'] );
	}

	/**
	 * Before Templatiq Image delete, remove from options.
	 *
	 * @since  2.0.0
	 * @param int $id ID to deleting image.
	 * @return void
	 */
	public function delete_templatiq_images( $id ) {

		if ( ! $id ) {
			return;
		}

		$saved_images         = get_option( 'templatiq-sites-saved-images', [] );
		$templatiq_image_flag = get_post_meta( $id, 'astra-images', true );
		$templatiq_image_flag = (int) $templatiq_image_flag;
		if (
			'' !== $templatiq_image_flag &&
			is_array( $saved_images ) &&
			! empty( $saved_images ) &&
			in_array( $templatiq_image_flag, $saved_images )
		) {
			$flag_arr     = [$templatiq_image_flag];
			$saved_images = array_diff( $saved_images, $flag_arr );
			update_option( 'templatiq-sites-saved-images', $saved_images, 'no' );
		}
	}

	/**
	 * Enqueue Image Search scripts into Beaver Builder Editor.
	 *
	 * @since  2.0.0
	 * @return void
	 */
	public function image_search_scripts() {

		if (
			class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() // BB Builder is on?
			||
			(
				class_exists( 'Brizy_Editor_Post' ) && // Brizy Builder is on?
				( isset( $_GET['brizy-edit'] ) || isset( $_GET['brizy-edit-iframe'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Fetching GET parameter, no nonce associated with this action.
			)
			||
			is_customize_preview() // Is customizer on?
		) {
			// Image Search assets.
			$this->image_search_assets();
		}
	}

	/**
	 * Elementor Batch Process via AJAX
	 *
	 * @since 2.0.0
	 */
	public function elementor_process_import_for_page() {

		// Verify Nonce.
		check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'You are not allowed to perform this action', 'templatiq-sites' ) );
		}

		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : '';

		$demo_data = get_option( 'templatiq_sites_import_elementor_data_' . $id, [] );

		if ( 'astra-blocks' === $type ) {
			$api_url = trailingslashit( self::get_instance()->get_api_domain() ) . 'wp-json/wp/v2/' . $type . '/' . $id;
		} else {
			$api_url = $demo_data['astra-page-api-url'];
		}

		if ( ! templatiq_sites_is_valid_url( $api_url ) ) {
			wp_send_json_error( __( 'Invalid API URL.', 'templatiq-sites' ) );
		}

		$response = wp_remote_get( $api_url );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( wp_remote_retrieve_body( $response ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! isset( $data['post-meta']['_elementor_data'] ) ) {
			wp_send_json_error( __( 'Invalid Post Meta', 'templatiq-sites' ) );
		}

		$meta    = json_decode( $data['post-meta']['_elementor_data'], true );
		$post_id = isset( $_POST['id'] ) ? absint( sanitize_key( $_POST['id'] ) ) : '';

		if ( empty( $post_id ) || empty( $meta ) ) {
			wp_send_json_error( __( 'Invalid Post ID or Elementor Meta', 'templatiq-sites' ) );
		}

		if ( isset( $data['astra-page-options-data'] ) && isset( $data['astra-page-options-data']['elementor_load_fa4_shim'] ) ) {
			update_option( 'elementor_load_fa4_shim', $data['astra-page-options-data']['elementor_load_fa4_shim'] );
		}

		$import      = new \Elementor\TemplateLibrary\Templatiq_Sites_Elementor_Pages();
		$import_data = $import->import( $post_id, $meta );

		delete_option( 'templatiq_sites_import_elementor_data_' . $id );
		wp_send_json_success( $import_data );
	}

	/**
	 * API Request
	 *
	 * @since 2.0.0
	 */
	public function api_request() {

		// Verify Nonce.
		check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error();
		}

		$demo_data = Templatiq_Sites_Importer::get_instance()->get_single_demo( 12121212212 );
		update_option( 'templatiq_sites_import_data', $demo_data, 'no' );

		error_log( print_r( $demo_data, true ) );
		wp_send_json_success( $demo_data );

		// $url = isset( $_POST['url'] ) ? sanitize_text_field( $_POST['url'] ) : '';

		// if ( empty( $url ) ) {
		// 	wp_send_json_error(
		// 		array(
		// 			'message' => __( 'Provided API URL is empty! Please try again!', 'templatiq-sites' ),
		// 			'code'    => 'Error',
		// 		)
		// 	);
		// }

		// $api_args = apply_filters(
		// 	'templatiq_sites_api_params', array(
		// 		'template_status' => '',
		// 		'version' => TEMPLATIQ_SITES_VER,
		// 	)
		// );

		// $api_url = add_query_arg( $api_args, trailingslashit( self::get_instance()->get_api_domain() ) . 'wp-json/wp/v2/' . $url );

		// error_log( $api_url );

		// if ( ! templatiq_sites_is_valid_url( $api_url ) ) {
		// 	wp_send_json_error(
		// 		array(
		// 			/* Translators: %s is API URL. */
		// 			'message' => sprintf( __( 'Invalid Request URL - %s', 'templatiq-sites' ), $api_url ),
		// 			'code'    => 'Error',
		// 		)
		// 	);
		// }

		// Templatiq_Sites_Error_Handler::get_instance()->start_error_handler();

		// $api_args = apply_filters(
		// 	'templatiq_sites_api_args', array(
		// 		'timeout' => 15,
		// 	)
		// );

		// $request = wp_remote_get( $api_url, $api_args );

		// Templatiq_Sites_Error_Handler::get_instance()->stop_error_handler();

		// if ( is_wp_error( $request ) ) {
		// 	$wp_error_code = $request->get_error_code();
		// 	switch ( $wp_error_code ) {
		// 		case 'http_request_not_executed':
		// 			/* translators: %s Error Message */
		// 			$message = sprintf( __( 'API Request could not be performed - %s', 'templatiq-sites' ), $request->get_error_message() );
		// 			break;
		// 		case 'http_request_failed':
		// 		default:
		// 			/* translators: %s Error Message */
		// 			$message = sprintf( __( 'API Request has failed - %s', 'templatiq-sites' ), $request->get_error_message() );
		// 			break;
		// 	}

		// 	wp_send_json_error(
		// 		array(
		// 			'message'       => $request->get_error_message(),
		// 			'code'          => 'WP_Error',
		// 			'response_code' => $wp_error_code,
		// 		)
		// 	);
		// }

		// $code      = (int) wp_remote_retrieve_response_code( $request );
		// $demo_data = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( 200 === $code ) {
			error_log( print_r( $demo_data, true ) );
			update_option( 'templatiq_sites_import_data', $demo_data, 'no' );
			wp_send_json_success( $demo_data );
		}

		$message       = wp_remote_retrieve_body( $request );
		$response_code = $code;

		if ( 200 !== $code && is_array( $demo_data ) && isset( $demo_data['code'] ) ) {
			$message = $demo_data['message'];
		}

		if ( 500 === $code ) {
			$message = __( 'Internal Server Error.', 'templatiq-sites' );
		}

		if ( 200 !== $code && false !== strpos( $message, 'Cloudflare' ) ) {
			$ip = Templatiq_Sites_Helper::get_client_ip();
			/* translators: %s IP address. */
			$message = sprintf( __( 'Client IP: %1$s </br> Error code: %2$s', 'templatiq-sites' ), $ip, $code );
			$code    = 'Cloudflare';
		}

		wp_send_json_error(
			[
				'message'       => $message,
				'code'          => $code,
				'response_code' => $response_code,
			]
		);
	}

	/**
	 * API Request
	 *
	 * @since 3.2.4
	 */
	public function elementor_api_request() {

		// Verify Nonce.
		check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error();
		}

		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';

		if ( empty( $id ) || empty( $type ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Provided API details are empty! Please try again!', 'templatiq-sites' ),
					'code'    => 'Error',
				]
			);
		}

		$api_args = apply_filters(
			'templatiq_sites_api_params', [
				'url'     => site_url(),
				'version' => TEMPLATIQ_SITES_VER,
			]
		);

		$api_url = add_query_arg( $api_args, trailingslashit( self::get_instance()->get_api_domain() ) . 'wp-json/wp/v2/' . $type . '/' . $id );

		if ( ! templatiq_sites_is_valid_url( $api_url ) ) {
			wp_send_json_error(
				[
					/* Translators: %s is API URL. */
					'message' => sprintf( __( 'Invalid Request URL - %s', 'templatiq-sites' ), $api_url ),
					'code'    => 'Error',
				]
			);
		}

		Templatiq_Sites_Error_Handler::get_instance()->start_error_handler();

		$api_args = apply_filters(
			'templatiq_sites_api_args', [
				'timeout' => 15,
			]
		);

		$request = wp_remote_get( $api_url, $api_args );

		Templatiq_Sites_Error_Handler::get_instance()->stop_error_handler();

		if ( is_wp_error( $request ) ) {
			$wp_error_code = $request->get_error_code();
			switch ( $wp_error_code ) {
				case 'http_request_not_executed':
					/* translators: %s Error Message */
					$message = sprintf( __( 'API Request could not be performed - %s', 'templatiq-sites' ), $request->get_error_message() );
					break;
				case 'http_request_failed':
				default:
					/* translators: %s Error Message */
					$message = sprintf( __( 'API Request has failed - %s', 'templatiq-sites' ), $request->get_error_message() );
					break;
			}

			wp_send_json_error(
				[
					'message'       => $request->get_error_message(),
					'code'          => 'WP_Error',
					'response_code' => $wp_error_code,
				]
			);
		}

		$code      = (int) wp_remote_retrieve_response_code( $request );
		$demo_data = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( 200 === $code ) {
			update_option( 'templatiq_sites_import_elementor_data_' . $id, $demo_data, 'no' );
			wp_send_json_success( $demo_data );
		}

		$message       = wp_remote_retrieve_body( $request );
		$response_code = $code;

		if ( 200 !== $code && is_array( $demo_data ) && isset( $demo_data['code'] ) ) {
			$message = $demo_data['message'];
		}

		if ( 500 === $code ) {
			$message = __( 'Internal Server Error.', 'templatiq-sites' );
		}

		if ( 200 !== $code && false !== strpos( $message, 'Cloudflare' ) ) {
			$ip = Templatiq_Sites_Helper::get_client_ip();
			/* translators: %s IP address. */
			$message = sprintf( __( 'Client IP: %1$s </br> Error code: %2$s', 'templatiq-sites' ), $ip, $code );
			$code    = 'Cloudflare';
		}

		wp_send_json_error(
			[
				'message'       => $message,
				'code'          => $code,
				'response_code' => $response_code,
			]
		);
	}

	/**
	 * API Flush Request
	 *
	 * @since 3.2.4
	 */
	public function elementor_flush_request() {

		// Verify Nonce.
		check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error();
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : '';

		delete_option( 'templatiq_sites_import_elementor_data_' . $id );

		wp_send_json_success();
	}

	/**
	 * Insert Template
	 *
	 * @return void
	 */
	public function insert_image_templates() {
		ob_start();
		require_once TEMPLATIQ_SITES_DIR . 'inc/includes/image-templates.php';
		ob_end_flush();
	}

	/**
	 * Insert Template
	 *
	 * @return void
	 */
	public function insert_image_templates_bb_and_brizy() {

		if (
			class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() // BB Builder is on?
			||
			(
				class_exists( 'Brizy_Editor_Post' ) && // Brizy Builder is on?
				( isset( $_GET['brizy-edit'] ) || isset( $_GET['brizy-edit-iframe'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Fetching GET parameter, no nonce associated with this action.
			)
		) {
			// Image Search Templates.
			ob_start();
			require_once TEMPLATIQ_SITES_DIR . 'inc/includes/image-templates.php';
			ob_end_flush();
		}
	}

	/**
	 * Insert Template
	 *
	 * @return void
	 */
	public function insert_templates() {
		ob_start();
		require_once TEMPLATIQ_SITES_DIR . 'inc/includes/templates.php';
		require_once TEMPLATIQ_SITES_DIR . 'inc/includes/image-templates.php';
		ob_end_flush();
	}

	/**
	 * Add/Remove Favorite.
	 *
	 * @since  2.0.0
	 */
	public function add_to_favorite() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'You are not allowed to perform this action', 'templatiq-sites' );
		}
		// Verify Nonce.
		check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

		$new_favorites = [];
		$site_id       = isset( $_POST['site_id'] ) ? sanitize_key( $_POST['site_id'] ) : '';

		if ( empty( $site_id ) ) {
			wp_send_json_error();
		}

		$favorite_settings = get_option( 'templatiq-sites-favorites', [] );

		if ( false !== $favorite_settings && is_array( $favorite_settings ) ) {
			$new_favorites = $favorite_settings;
		}

		$is_favorite = isset( $_POST['is_favorite'] ) ? sanitize_key( $_POST['is_favorite'] ) : '';

		if ( 'false' === $is_favorite ) {
			if ( in_array( $site_id, $new_favorites, true ) ) {
				$key = array_search( $site_id, $new_favorites, true );
				unset( $new_favorites[$key] );
			}
		} else {
			if ( ! in_array( $site_id, $new_favorites, true ) ) {
				array_push( $new_favorites, $site_id );
			}
		}

		update_option( 'templatiq-sites-favorites', $new_favorites, 'no' );

		wp_send_json_success(
			[
				'all_favorites' => $new_favorites,
			]
		);
	}

	/**
	 * Import Template.
	 *
	 * @since  2.0.0
	 */
	public function create_template() {

		// Verify Nonce.
		check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'customize' ) ) {
			wp_send_json_error( __( 'You are not allowed to perform this action', 'templatiq-sites' ) );
		}

		$id        = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : '';
		$type      = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		$demo_data = get_option( 'templatiq_sites_import_elementor_data_' . $id, [] );

		if ( 'astra-blocks' === $type ) {
			$url = trailingslashit( self::get_instance()->get_api_domain() ) . 'wp-json/wp/v2/' . $type . '/' . $id;
		} else {
			$url = $demo_data['astra-page-api-url'];
		}

		$api_url = add_query_arg(
			[
				'site_url' => site_url(),
				'version'  => TEMPLATIQ_SITES_VER,
			], $url
		);

		if ( ! templatiq_sites_is_valid_url( $api_url ) ) {
			wp_send_json_error( __( 'Invalid API URL.', 'templatiq-sites' ) );
		}

		$response = wp_remote_get( $api_url );

		if ( is_wp_error( $response ) || 200 !== $response['response']['code'] ) {
			wp_send_json_error( wp_remote_retrieve_body( $response ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) ) {
			wp_send_json_error( 'Empty page data.' );
		}

		$content = isset( $data['content']['rendered'] ) ? $data['content']['rendered'] : '';

		$page_id = isset( $data['id'] ) ? sanitize_text_field( $data['id'] ) : '';

		$title          = '';
		$rendered_title = isset( $data['title']['rendered'] ) ? sanitize_text_field( $data['title']['rendered'] ) : '';
		if ( isset( $rendered_title ) ) {
			$title = ( isset( $_POST['title'] ) && '' !== $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) . ' - ' . $rendered_title : $rendered_title;
		}

		$excerpt = isset( $data['excerpt']['rendered'] ) ? sanitize_text_field( $data['excerpt']['rendered'] ) : '';

		$post_args = [
			'post_type'    => 'elementor_library',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
		];

		$new_page_id = wp_insert_post( $post_args );
		update_post_meta( $new_page_id, '_templatiq_sites_enable_for_batch', true );
		$post_meta = isset( $data['post-meta'] ) ? $data['post-meta'] : [];

		if ( ! empty( $post_meta ) ) {
			$this->import_template_meta( $new_page_id, $post_meta );
		}

		$term_value = ( 'pages' === $type ) ? 'page' : 'section';
		update_post_meta( $new_page_id, '_elementor_template_type', $term_value );
		wp_set_object_terms( $new_page_id, $term_value, 'elementor_library_type' );

		// update_post_meta( $new_page_id, '_wp_page_template', 'elementor_header_footer' );

		do_action( 'templatiq_sites_process_single', $new_page_id );

		// Flush the object when import is successful.
		delete_option( 'templatiq_sites_import_elementor_data_' . $id );

		wp_send_json_success(
			[
				'remove-page-id' => $page_id,
				'id'             => $new_page_id,
				'link'           => get_permalink( $new_page_id ),
			]
		);
	}

	/**
	 * Search Images.
	 *
	 * @since 2.7.3.
	 */
	public function search_images() {
		// Verify Nonce.
		check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( __( 'You are not allowed to perform this action', 'templatiq-sites' ) );
		}

		$params = isset( $_POST['params'] ) ? array_map( 'sanitize_text_field', $_POST['params'] ) : [];

		$params['key'] = $this->pixabay_api_key;

		$api_url = add_query_arg( $params, $this->pixabay_url );

		$response = wp_remote_get( $api_url );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( wp_remote_retrieve_body( $response ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		wp_send_json_success( $data );
	}

	/**
	 * Download and save the image in the media library.
	 *
	 * @since  2.0.0
	 */
	public function create_image() {
		// Verify Nonce.
		check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( __( 'You are not allowed to perform this action', 'templatiq-sites' ) );
		}

		$url      = isset( $_POST['url'] ) ? sanitize_url( $_POST['url'] ) : false; // phpcs:ignore -- We need to remove this ignore once the WPCS has released this issue fix - https://github.com/WordPress/WordPress-Coding-Standards/issues/2189.
		$name     = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : false;
		$photo_id = isset( $_POST['id'] ) ? absint( sanitize_key( $_POST['id'] ) ) : 0;

		if ( false === $url ) {
			wp_send_json_error( __( 'Need to send URL of the image to be downloaded', 'templatiq-sites' ) );
		}

		$image  = '';
		$result = [];

		$name  = preg_replace( '/\.[^.]+$/', '', $name ) . '-' . $photo_id . '.jpg';
		$image = $this->create_image_from_url( $url, $name, $photo_id );

		if ( is_wp_error( $image ) ) {
			wp_send_json_error( $image );
		}

		if ( 0 !== $image ) {
			$result['attachmentData'] = wp_prepare_attachment_for_js( $image );
			if ( did_action( 'elementor/loaded' ) ) {
				$result['data'] = Templatiq_Sites_Elementor_Images::get_instance()->get_attachment_data( $image );
			}
			if ( 0 === $photo_id ) {
				/**
				 * This flag ensures these files are deleted in the Reset Process.
				 */
				update_post_meta( $image, '_templatiq_sites_imported_post', true );
			}
		} else {
			wp_send_json_error( __( 'Could not download the image.', 'templatiq-sites' ) );
		}

		// Save downloaded image reference to an option.
		if ( 0 !== $photo_id ) {
			$saved_images = get_option( 'templatiq-sites-saved-images', [] );

			if ( empty( $saved_images ) || false === $saved_images ) {
				$saved_images = [];
			}

			$saved_images[] = $photo_id;
			update_option( 'templatiq-sites-saved-images', $saved_images, 'no' );
		}

		$result['updated-saved-images'] = get_option( 'templatiq-sites-saved-images', [] );

		wp_send_json_success( $result );
	}

	/**
	 * Set the upload directory
	 */
	public function get_wp_upload_url() {
		$wp_upload_dir = wp_upload_dir();

		return isset( $wp_upload_dir['url'] ) ? $wp_upload_dir['url'] : false;
	}

	/**
	 * Create the image and return the new media upload id.
	 *
	 * @param String $url URL to pixabay image.
	 * @param String $name Name to pixabay image.
	 * @param String $photo_id Photo ID to pixabay image.
	 * @see http://codex.wordpress.org/Function_Reference/wp_insert_attachment#Example
	 */
	public function create_image_from_url( $url, $name, $photo_id ) {
		$file_array         = [];
		$file_array['name'] = wp_basename( $name );

		// Download file to temp location.
		$file_array['tmp_name'] = download_url( $url );

		// If error storing temporarily, return the error.
		if ( is_wp_error( $file_array['tmp_name'] ) ) {
			return $file_array;
		}

		// Do the validation and storage stuff.
		$id = media_handle_sideload( $file_array, 0, null );

		// If error storing permanently, unlink.
		if ( is_wp_error( $id ) ) {
			@unlink( $file_array['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Deleting the file from temp location.

			return $id;
		}

		// Store the original attachment source in meta.
		add_post_meta( $id, '_source_url', $url );

		update_post_meta( $id, 'astra-images', $photo_id );
		update_post_meta( $id, '_wp_attachment_image_alt', $name );

		return $id;
	}

	/**
	 * Import Post Meta
	 *
	 * @since 2.0.0
	 *
	 * @param  integer $post_id  Post ID.
	 * @param  array   $metadata  Post meta.
	 * @return void
	 */
	public function import_post_meta( $post_id, $metadata ) {

		$metadata = (array) $metadata;

		foreach ( $metadata as $meta_key => $meta_value ) {

			if ( $meta_value ) {

				if ( '_elementor_data' === $meta_key ) {

					$raw_data = json_decode( stripslashes( $meta_value ), true );

					if ( is_array( $raw_data ) ) {
						$raw_data = wp_slash( wp_json_encode( $raw_data ) );
					} else {
						$raw_data = wp_slash( $raw_data );
					}
				} else {

					if ( is_serialized( $meta_value, true ) ) {
						$raw_data = maybe_unserialize( stripslashes( $meta_value ) );
					} elseif ( is_array( $meta_value ) ) {
						$raw_data = json_decode( stripslashes( $meta_value ), true );
					} else {
						$raw_data = $meta_value;
					}
				}

				update_post_meta( $post_id, $meta_key, $raw_data );
			}
		}
	}

	/**
	 * Import Post Meta
	 *
	 * @since 2.0.0
	 *
	 * @param  integer $post_id  Post ID.
	 * @param  array   $metadata  Post meta.
	 * @return void
	 */
	public function import_template_meta( $post_id, $metadata ) {

		$metadata = (array) $metadata;

		foreach ( $metadata as $meta_key => $meta_value ) {

			if ( $meta_value ) {

				if ( '_elementor_data' === $meta_key ) {

					$raw_data = json_decode( stripslashes( $meta_value ), true );

					if ( is_array( $raw_data ) ) {
						$raw_data = wp_slash( wp_json_encode( $raw_data ) );
					} else {
						$raw_data = wp_slash( $raw_data );
					}
				} else {

					if ( is_serialized( $meta_value, true ) ) {
						$raw_data = maybe_unserialize( stripslashes( $meta_value ) );
					} elseif ( is_array( $meta_value ) ) {
						$raw_data = json_decode( stripslashes( $meta_value ), true );
					} else {
						$raw_data = $meta_value;
					}
				}

				update_post_meta( $post_id, $meta_key, $raw_data );
			}
		}
	}


	/**
	 * Activate theme
	 *
	 * @since 1.3.2
	 * @return void
	 */
	public function activate_theme() {

		// Verify Nonce.
		check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'customize' ) ) {
			wp_send_json_error( __( 'You are not allowed to perform this action', 'templatiq-sites' ) );
		}

		Templatiq_Sites_Error_Handler::get_instance()->start_error_handler();

		switch_theme( 'astra' );

		Templatiq_Sites_Error_Handler::get_instance()->stop_error_handler();

		wp_send_json_success(
			[
				'success' => true,
				'message' => __( 'Theme Activated', 'templatiq-sites' ),
			]
		);
	}

	/**
	 * Reset terms and forms.
	 *
	 * @since 3.0.3
	 */
	public function reset_terms_and_forms() {
		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'templatiq-sites' ) );
			}
		}

		Templatiq_Sites_Error_Handler::get_instance()->start_error_handler();

		$terms = templatiq_sites_get_reset_term_data();

		if ( ! empty( $terms ) ) {
			foreach ( $terms as $key => $term_id ) {
				$term_id = absint( $term_id );
				if ( $term_id ) {
					$term = get_term( $term_id );
					if ( ! is_wp_error( $term ) && ! empty( $term ) && is_object( $term ) ) {

						do_action( 'templatiq_sites_before_delete_imported_terms', $term_id, $term );

						$message = 'Deleted - Term ' . $term_id . ' - ' . $term->name . ' ' . $term->taxonomy;
						Templatiq_Sites_Importer_Log::add( $message );
						wp_delete_term( $term_id, $term->taxonomy );
					}
				}
			}
		}

		$forms = templatiq_sites_get_reset_form_data();

		if ( ! empty( $forms ) ) {
			foreach ( $forms as $key => $post_id ) {
				$post_id = absint( $post_id );
				if ( $post_id ) {

					do_action( 'templatiq_sites_before_delete_imported_wp_forms', $post_id );

					$message = 'Deleted - Form ID ' . $post_id . ' - ' . get_post_type( $post_id ) . ' - ' . get_the_title( $post_id );
					Templatiq_Sites_Importer_Log::add( $message );
					wp_delete_post( $post_id, true );
				}
			}
		}

		Templatiq_Sites_Error_Handler::get_instance()->stop_error_handler();

		if ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}

	/**
	 * Reset posts in chunks.
	 *
	 * @since 3.0.8
	 */
	public function reset_posts() {
		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'templatiq-sites' ) );
			}
		}

		Templatiq_Sites_Error_Handler::get_instance()->start_error_handler();

		// Suspend bunches of stuff in WP core.
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );
		wp_suspend_cache_invalidation( true );

		$all_ids = ( isset( $_POST['ids'] ) ) ? sanitize_text_field( $_POST['ids'] ) : '';

		$posts = json_decode( stripslashes( sanitize_text_field( $_POST['ids'] ) ), true );

		error_log( 'All Posts: ' . print_r( $posts, true ) );

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $key => $post_id ) {
				$post_id = absint( $post_id );
				if ( $post_id ) {
					$post_type = get_post_type( $post_id );
					$message   = 'Deleted - Post ID ' . $post_id . ' - ' . $post_type . ' - ' . get_the_title( $post_id );

					error_log( "Post ID # {$post_id}" );

					if ( 'elementor_library' === $post_type ) {
						$_GET['force_delete_kit'] = true;
					}

					// do_action( 'templatiq_sites_before_delete_imported_posts', $post_id, $post_type );

					if ( wp_delete_post( $post_id, true ) ) {
						error_log( $message );
					}

					Templatiq_Sites_Importer_Log::add( $message );
				}
			}
		}

		// Re-enable stuff in core.
		wp_suspend_cache_invalidation( false );
		wp_cache_flush();
		foreach ( get_taxonomies() as $tax ) {
			delete_option( "{$tax}_children" );
			_get_term_hierarchy( $tax );
		}

		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );

		Templatiq_Sites_Error_Handler::get_instance()->stop_error_handler();

		if ( wp_doing_ajax() ) {
			error_log( 'log: wp_send_json_success()' );
			wp_send_json_success();
		}

		error_log( 'log: wp_send_json_error - hello' );
		wp_send_json_error( 'wp_send_json_error - hello' );
	}

	/**
	 * Get post IDs to be deleted.
	 */
	public function get_deleted_post_ids() {
		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'templatiq-sites' ) );
			}
		}
		wp_send_json_success( templatiq_sites_get_reset_post_data() );
	}

	/**
	 * Set reset data
	 * Note: This function can be deleted after a few releases since we are performing the delete operation in chunks.
	 */
	public function get_reset_data() {

		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'templatiq-sites' ) );
			}
		}

		Templatiq_Sites_Error_Handler::get_instance()->start_error_handler();

		$data = [
			'reset_posts'    => templatiq_sites_get_reset_post_data(),
			'reset_wp_forms' => templatiq_sites_get_reset_form_data(),
			'reset_terms'    => templatiq_sites_get_reset_term_data(),
		];

		Templatiq_Sites_Error_Handler::get_instance()->stop_error_handler();

		if ( wp_doing_ajax() ) {
			wp_send_json_success( $data );
		}

		return $data;
	}

	/**
	 * Backup our existing settings.
	 */
	public function backup_settings() {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'User does not have permission!', 'templatiq-sites' ) );
			}
		}

		$file_name    = 'templatiq-sites-backup-' . gmdate( 'd-M-Y-h-i-s' ) . '.json';
		$old_settings = get_option( 'astra-settings', [] );
		$upload_dir   = Templatiq_Sites_Importer_Log::get_instance()->log_dir();
		$upload_path  = trailingslashit( $upload_dir['path'] );
		$log_file     = $upload_path . $file_name;
		$file_system  = self::get_instance()->get_filesystem();

		// If file system fails? Then take a backup in site option.
		if ( false === $file_system->put_contents( $log_file, wp_json_encode( $old_settings ), FS_CHMOD_FILE ) ) {
			update_option( 'templatiq_sites_' . $file_name, $old_settings, 'no' );
		}

		if ( defined( 'WP_CLI' ) ) {
			WP_CLI::line( 'File generated at ' . $log_file );
		} elseif ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}

	/**
	 * Get theme install, active or inactive status.
	 *
	 * @since 1.3.2
	 *
	 * @return string Theme status
	 */
	public function get_theme_status() {

		$theme = wp_get_theme();

		// Theme installed and activate.
		if ( 'One Directory' === $theme->name || 'One Directory' === $theme->parent_theme ) {
			return 'installed-and-active';
		}

		// Theme installed but not activate.
		foreach ( (array) wp_get_themes() as $theme_dir => $theme ) {
			if ( 'One Directory' === $theme->name || 'One Directory' === $theme->parent_theme ) {
				return 'installed-but-inactive';
			}
		}

		return 'not-installed';
	}

	/**
	 * Get the API URL.
	 *
	 * @since  1.0.0
	 */
	public static function get_api_domain() {
		return defined( 'STARTER_TEMPLATES_REMOTE_URL' ) ? STARTER_TEMPLATES_REMOTE_URL : apply_filters( 'templatiq_sites_api_domain', 'https://websitedemos.net/' );
	}

	/**
	 * Setter for $api_url
	 *
	 * @since  1.0.0
	 */
	public function set_api_url() {
		$this->api_domain = trailingslashit( self::get_api_domain() );
		$this->api_url    = apply_filters( 'templatiq_sites_api_url', $this->api_domain . 'wp-json/wp/v2/' );

		$this->search_analytics_url = apply_filters( 'templatiq_sites_search_api_url', $this->api_domain . 'wp-json/analytics/v2/search/' );
		$this->import_analytics_url = apply_filters( 'templatiq_sites_import_analytics_api_url', $this->api_domain . 'wp-json/analytics/v2/import/' );

		$this->pixabay_url     = 'https://pixabay.com/api/';
		$this->pixabay_api_key = '2727911-c4d7c1031949c7e0411d7e81e';
	}

	/**
	 * Enqueue Image Search scripts.
	 *
	 * @since  2.0.0
	 * @return void
	 */
	public function image_search_assets() {

		wp_enqueue_script( 'masonry' );
		wp_enqueue_script( 'imagesloaded' );

		wp_enqueue_script(
			'templatiq-sites-images-common',
			TEMPLATIQ_SITES_URI . 'inc/assets/js/common.js',
			['jquery', 'wp-util'], // Dependencies, defined above.
			TEMPLATIQ_SITES_VER,
			true
		);

		$data = apply_filters(
			'templatiq_sites_images_common',
			[
				'ajaxurl'             => esc_url( admin_url( 'admin-ajax.php' ) ),
				'asyncurl'            => esc_url( admin_url( 'async-upload.php' ) ),
				'is_bb_active'        => ( class_exists( 'FLBuilderModel' ) ),
				'is_brizy_active'     => ( class_exists( 'Brizy_Editor_Post' ) ),
				'is_elementor_active' => ( did_action( 'elementor/loaded' ) ),
				'is_elementor_editor' => ( did_action( 'elementor/loaded' ) )?Elementor\Plugin::instance()->editor->is_edit_mode() : false,
				'is_bb_editor'        => ( class_exists( 'FLBuilderModel' ) ) ? ( FLBuilderModel::is_builder_active() ) : false,
				'is_brizy_editor'     => ( class_exists( 'Brizy_Editor_Post' ) ) ? ( isset( $_GET['brizy-edit'] ) || isset( $_GET['brizy-edit-iframe'] ) ) : false, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Fetching GET parameter, no nonce associated with this action.
				'saved_images' => get_option( 'templatiq-sites-saved-images', [] ),
				'pixabay_category'    => [
					'all'            => __( 'All', 'templatiq-sites' ),
					'animals'        => __( 'Animals', 'templatiq-sites' ),
					'buildings'      => __( 'Architecture/Buildings', 'templatiq-sites' ),
					'backgrounds'    => __( 'Backgrounds/Textures', 'templatiq-sites' ),
					'fashion'        => __( 'Beauty/Fashion', 'templatiq-sites' ),
					'business'       => __( 'Business/Finance', 'templatiq-sites' ),
					'computer'       => __( 'Computer/Communication', 'templatiq-sites' ),
					'education'      => __( 'Education', 'templatiq-sites' ),
					'feelings'       => __( 'Emotions', 'templatiq-sites' ),
					'food'           => __( 'Food/Drink', 'templatiq-sites' ),
					'health'         => __( 'Health/Medical', 'templatiq-sites' ),
					'industry'       => __( 'Industry/Craft', 'templatiq-sites' ),
					'music'          => __( 'Music', 'templatiq-sites' ),
					'nature'         => __( 'Nature/Landscapes', 'templatiq-sites' ),
					'people'         => __( 'People', 'templatiq-sites' ),
					'places'         => __( 'Places/Monuments', 'templatiq-sites' ),
					'religion'       => __( 'Religion', 'templatiq-sites' ),
					'science'        => __( 'Science/Technology', 'templatiq-sites' ),
					'sports'         => __( 'Sports', 'templatiq-sites' ),
					'transportation' => __( 'Transportation/Traffic', 'templatiq-sites' ),
					'travel'         => __( 'Travel/Vacation', 'templatiq-sites' ),
				],
				'pixabay_order'       => [
					'popular'  => __( 'Popular', 'templatiq-sites' ),
					'latest'   => __( 'Latest', 'templatiq-sites' ),
					'upcoming' => __( 'Upcoming', 'templatiq-sites' ),
					'ec'       => __( 'Editor\'s Choice', 'templatiq-sites' ),
				],
				'pixabay_orientation' => [
					'any'        => __( 'Any Orientation', 'templatiq-sites' ),
					'vertical'   => __( 'Vertical', 'templatiq-sites' ),
					'horizontal' => __( 'Horizontal', 'templatiq-sites' ),
				],
				'title'               => __( 'Free Images', 'templatiq-sites' ),
				'search_placeholder'  => __( 'Search - Ex: flowers', 'templatiq-sites' ),
				'downloading'         => __( 'Downloading...', 'templatiq-sites' ),
				'validating'          => __( 'Validating...', 'templatiq-sites' ),
				'empty_api_key'       => __( 'Please enter an API key.', 'templatiq-sites' ),
				'error_api_key'       => __( 'An error occured with code ', 'templatiq-sites' ),
				'_ajax_nonce'         => wp_create_nonce( 'templatiq-sites' ),
			]
		);
		wp_localize_script( 'templatiq-sites-images-common', 'templatiqImages', $data );

		wp_enqueue_script(
			'templatiq-sites-images-script',
			TEMPLATIQ_SITES_URI . 'inc/assets/js/dist/main.js',
			['wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-api-fetch', 'templatiq-sites-images-common'], // Dependencies, defined above.
			TEMPLATIQ_SITES_VER,
			true
		);

		wp_enqueue_style( 'templatiq-sites-images', TEMPLATIQ_SITES_URI . 'inc/assets/css/images.css', TEMPLATIQ_SITES_VER, true );
		wp_style_add_data( 'templatiq-sites-images', 'rtl', 'replace' );
	}

	/**
	 * Getter for $api_url
	 *
	 * @since  1.0.0
	 */
	public function get_api_url() {
		return $this->api_url;
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since  1.3.2    Added 'install-theme.js' to install and activate theme.
	 * @since  1.0.5    Added 'getUpgradeText' and 'getUpgradeURL' localize variables.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $hook Current hook name.
	 * @return void
	 */
	public function admin_enqueue( $hook = '' ) {

		// Image Search assets.
		if ( 'post-new.php' === $hook || 'post.php' === $hook || 'widgets.php' === $hook ) {
			$this->image_search_assets();
		}

		// Avoid scripts from customizer.
		if ( is_customize_preview() ) {
			return;
		}

		wp_enqueue_script( 'templatiq-sites-install-theme', TEMPLATIQ_SITES_URI . 'inc/assets/js/install-theme.js', ['jquery', 'updates'], TEMPLATIQ_SITES_VER, true );

		$data = apply_filters(
			'templatiq_sites_install_theme_localize_vars',
			[
				'installed'   => __( 'Installed! Activating..', 'templatiq-sites' ),
				'activating'  => __( 'Activating...', 'templatiq-sites' ),
				'activated'   => __( 'Activated!', 'templatiq-sites' ),
				'installing'  => __( 'Installing...', 'templatiq-sites' ),
				'ajaxurl'     => esc_url( admin_url( 'admin-ajax.php' ) ),
				'_ajax_nonce' => wp_create_nonce( 'templatiq-sites' ),
			]
		);
		wp_localize_script( 'templatiq-sites-install-theme', 'TemplatiqSitesInstallThemeVars', $data );

		if ( 'appearance_page_starter-templates' !== $hook ) {
			return;
		}

		global $is_IE, $is_edge;

		if ( $is_IE || $is_edge ) {
			wp_enqueue_script( 'templatiq-sites-eventsource', TEMPLATIQ_SITES_URI . 'inc/assets/js/eventsource.min.js', ['jquery', 'wp-util', 'updates'], TEMPLATIQ_SITES_VER, true );
		}

		// Fetch.
		wp_register_script( 'templatiq-sites-fetch', TEMPLATIQ_SITES_URI . 'inc/assets/js/fetch.umd.js', ['jquery'], TEMPLATIQ_SITES_VER, true );

		// History.
		wp_register_script( 'templatiq-sites-history', TEMPLATIQ_SITES_URI . 'inc/assets/js/history.js', ['jquery'], TEMPLATIQ_SITES_VER, true );

		// Admin Page.
		wp_enqueue_style( 'templatiq-sites-admin', TEMPLATIQ_SITES_URI . 'inc/assets/css/admin.css', TEMPLATIQ_SITES_VER, true );
		wp_style_add_data( 'templatiq-sites-admin', 'rtl', 'replace' );

		$data = $this->get_local_vars();
	}

	/**
	 * Get CTA link
	 *
	 * @param string $source    The source of the link.
	 * @param string $medium    The medium of the link.
	 * @param string $campaign  The campaign of the link.
	 * @return array
	 */
	public function get_cta_link( $source = '', $medium = '', $campaign = '' ) {
		$default_page_builder = Templatiq_Sites_Page::get_instance()->get_setting( 'page_builder' );
		$cta_links            = $this->get_cta_links( $source, $medium, $campaign );

		return isset( $cta_links[$default_page_builder] ) ? $cta_links[$default_page_builder] : 'https://wpastra.com/starter-templates-plans/?utm_source=StarterTemplatesPlugin&utm_campaign=WPAdmin';
	}

	/**
	 * Get CTA Links
	 *
	 * @since 2.6.18
	 *
	 * @param string $source    The source of the link.
	 * @param string $medium    The medium of the link.
	 * @param string $campaign  The campaign of the link.
	 * @return array
	 */
	public function get_cta_links( $source = '', $medium = '', $campaign = '' ) {
		return [
			'elementor'      => add_query_arg(
				[
					'utm_source'   => ! empty( $source ) ? $source : 'elementor-templates',
					'utm_medium'   => 'dashboard',
					'utm_campaign' => 'Starter-Template-Backend',
				], 'https://wpastra.com/elementor-starter-templates/'
			),
			'beaver-builder' => add_query_arg(
				[
					'utm_source'   => ! empty( $source ) ? $source : 'beaver-templates',
					'utm_medium'   => 'dashboard',
					'utm_campaign' => 'Starter-Template-Backend',
				], 'https://wpastra.com/beaver-builder-starter-templates/'
			),
			'gutenberg'      => add_query_arg(
				[
					'utm_source'   => ! empty( $source ) ? $source : 'gutenberg-templates',
					'utm_medium'   => 'dashboard',
					'utm_campaign' => 'Starter-Template-Backend',
				], 'https://wpastra.com/starter-templates-plans/'
			),
			'brizy'          => add_query_arg(
				[
					'utm_source'   => ! empty( $source ) ? $source : 'brizy-templates',
					'utm_medium'   => 'dashboard',
					'utm_campaign' => 'Starter-Template-Backend',
				], 'https://wpastra.com/starter-templates-plans/'
			),
		];
	}

	/**
	 * Returns Localization Variables.
	 *
	 * @since 2.0.0
	 */
	public function get_local_vars() {

		$stored_data = [
			'templatiq-sites-site-category' => [],
			'astra-site-page-builder'       => [],
			'templatiq-sites'               => [],
			'site-pages-category'           => [],
			'site-pages-page-builder'       => [],
			'site-pages-parent-category'    => [],
			'site-pages'                    => [],
			'favorites'                     => get_option( 'templatiq-sites-favorites' ),
		];

		$favorite_data = get_option( 'templatiq-sites-favorites' );

		$license_status = false;
		if ( is_callable( 'BSF_License_Manager::bsf_is_active_license' ) ) {
			$license_status = BSF_License_Manager::bsf_is_active_license( 'astra-pro-sites' );
		}

		$spectra_theme = 'not-installed';
		// Theme installed and activate.
		if ( 'spectra-one' === get_option( 'stylesheet', 'astra' ) ) {
			$spectra_theme = 'installed-and-active';
		}
		$enable_block_builder = apply_filters( 'st_enable_block_page_builder', false );
		$default_page_builder = ( 'installed-and-active' === $spectra_theme ) ? 'fse' : Templatiq_Sites_Page::get_instance()->get_setting( 'page_builder' );
		$default_page_builder = ( $enable_block_builder && empty( $default_page_builder ) ) ? 'gutenberg' : $default_page_builder;

		if ( is_callable( '\SureCart\Models\ApiToken::get()' ) ) {
			$surecart_store_exist = \SureCart\Models\ApiToken::get();
		}

		$data = apply_filters(
			'templatiq_sites_localize_vars',
			[
				'subscribed'                         => get_user_meta( get_current_user_ID(), 'templatiq-sites-subscribed', true ),
				'debug'                              => defined( 'WP_DEBUG' ) ? true : false,
				'isPro'                              => defined( 'TEMPLATIQ_PRO_SITES_NAME' ) ? true : false,
				'isWhiteLabeled'                     => Templatiq_Sites_White_Label::get_instance()->is_white_labeled(),
				'whiteLabelName'                     => Templatiq_Sites_White_Label::get_instance()->get_white_label_name(),
				'whiteLabelUrl'                      => Templatiq_Sites_White_Label::get_instance()->get_white_label_link( '#' ),
				'ajaxurl'                            => esc_url( admin_url( 'admin-ajax.php' ) ),
				'siteURL'                            => site_url(),
				'getProText'                         => __( 'Get Access!', 'templatiq-sites' ),
				'getProURL'                          => esc_url( 'https://wpastra.com/starter-templates-plans/?utm_source=demo-import-panel&utm_campaign=templatiq-sites&utm_medium=wp-dashboard' ),
				'getUpgradeText'                     => __( 'Upgrade', 'templatiq-sites' ),
				'getUpgradeURL'                      => esc_url( 'https://wpastra.com/starter-templates-plans/?utm_source=demo-import-panel&utm_campaign=templatiq-sites&utm_medium=wp-dashboard' ),
				'_ajax_nonce'                        => wp_create_nonce( 'templatiq-sites' ),
				'requiredPlugins'                    => [],
				'syncLibraryStart'                   => '<span class="message">' . esc_html__( 'Syncing template library in the background. The process can take anywhere between 2 to 3 minutes. We will notify you once done.', 'templatiq-sites' ) . '</span>',
				'xmlRequiredFilesMissing'            => __( 'Some of the files required during the import process are missing.<br/><br/>Please try again after some time.', 'templatiq-sites' ),
				'importFailedMessageDueToDebug'      => __( '<p>WordPress debug mode is currently enabled on your website. This has interrupted the import process..</p><p>Kindly disable debug mode and try importing Starter Template again.</p><p>You can add the following code into the wp-config.php file to disable debug mode.</p><p><code>define(\'WP_DEBUG\', false);</code></p>', 'templatiq-sites' ),
				/* translators: %s is a documentation link. */
				'importFailedMessage'                => sprintf( __( '<p>We are facing a temporary issue in importing this template.</p><p>Read <a href="%s" target="_blank">article</a> to resolve the issue and continue importing template.</p>', 'templatiq-sites' ), esc_url( 'https://wpastra.com/docs/fix-starter-template-importing-issues/' ) ),
				/* translators: %s is a documentation link. */
				'importFailedRequiredPluginsMessage' => sprintf( __( '<p>We are facing a temporary issue in installing the required plugins for this template.</p><p>Read&nbsp;<a href="%s" target="_blank">article</a>&nbsp;to resolve the issue and continue importing template.</p>', 'templatiq-sites' ), esc_url( 'https://wpastra.com/docs/plugin-installation-failed-multisite/' ) ),

				'strings'                            => [
					/* translators: %s are white label strings. */
					'warningBeforeCloseWindow' => sprintf( __( 'Warning! %1$s Import process is not complete. Don\'t close the window until import process complete. Do you still want to leave the window?', 'templatiq-sites' ), Templatiq_Sites_White_Label::get_instance()->get_white_label_name() ),
					'viewSite'                 => __( 'Done! View Site', 'templatiq-sites' ),
					'syncCompleteMessage'      => self::get_instance()->get_sync_complete_message(),
					/* translators: %s is a template name */
					'importSingleTemplate'     => __( 'Import "%s" Template', 'templatiq-sites' ),
				],
				'log'                                => [
					'bulkInstall'  => __( 'Installing Required Plugins..', 'templatiq-sites' ),
					/* translators: %s are white label strings. */
					'themeInstall' => sprintf( __( 'Installing %1$s Theme..', 'templatiq-sites' ), Templatiq_Sites_White_Label::get_instance()->get_option( 'astra', 'name', 'Templatiq' ) ),
				],
				'default_page_builder'               => $default_page_builder,
				'default_page_builder_data'          => Templatiq_Sites_Page::get_instance()->get_default_page_builder(),
				'default_page_builder_sites'         => Templatiq_Sites_Page::get_instance()->get_sites_by_page_builder( $default_page_builder ),
				'sites'                              => templatiq_sites_get_api_params(),
				'categories'                         => [],
				'page-builders'                      => [],
				'all_sites'                          => $this->get_all_sites(),
				'all_site_categories'                => get_option( 'templatiq-sites-all-site-categories', [] ),
				'all_site_categories_and_tags'       => get_option( 'templatiq-sites-all-site-categories-and-tags', [] ),
				'license_status'                     => $license_status,
				'license_page_builder'               => get_option( 'templatiq-sites-license-page-builder', '' ),
				'ApiDomain'                          => $this->api_domain,
				'ApiURL'                             => $this->api_url,
				'stored_data'                        => $stored_data,
				'favorite_data'                      => $favorite_data,
				'category_slug'                      => 'templatiq-sites-site-category',
				'page_builder'                       => 'astra-site-page-builder',
				'cpt_slug'                           => 'templatiq-sites',
				'parent_category'                    => '',
				'compatibilities'                    => $this->get_compatibilities(),
				'compatibilities_data'               => $this->get_compatibilities_data(),
				'dismiss'                            => __( 'Dismiss this notice.', 'templatiq-sites' ),
				'headings'                           => [
					'subscription' => esc_html__( 'One Last Step..', 'templatiq-sites' ),
					'site_import'  => esc_html__( 'Your Selected Website is Being Imported.', 'templatiq-sites' ),
					'page_import'  => esc_html__( 'Your Selected Template is Being Imported.', 'templatiq-sites' ),
				],
				'subscriptionSuccessMessage'         => esc_html__( 'We have sent you a surprise gift on your email address! Please check your inbox!', 'templatiq-sites' ),
				'first_import_complete'              => get_option( 'templatiq_sites_import_complete' ),
				'server_import_primary_error'        => __( 'Looks like the template you are importing is temporarily not available.', 'templatiq-sites' ),
				'client_import_primary_error'        => __( 'We could not start the import process and this is the message from WordPress:', 'templatiq-sites' ),
				'cloudflare_import_primary_error'    => __( 'There was an error connecting to the Starter Templates API.', 'templatiq-sites' ),
				'xml_import_interrupted_primary'     => __( 'There was an error while importing the content.', 'templatiq-sites' ),
				'xml_import_interrupted_secondary'   => __( 'To import content, WordPress needs to store XML file in /wp-content/ folder. Please get in touch with your hosting provider.', 'templatiq-sites' ),
				'xml_import_interrupted_error'       => __( 'Looks like your host probably could not store XML file in /wp-content/ folder.', 'templatiq-sites' ),
				/* translators: %s HTML tags */
				'ajax_request_failed_primary'        => sprintf( __( '%1$sWe could not start the import process due to failed AJAX request and this is the message from WordPress:%2$s', 'templatiq-sites' ), '<p>', '</p>' ),
				/* translators: %s URL to document. */
				'ajax_request_failed_secondary'      => sprintf( __( '%1$sRead&nbsp;<a href="%2$s" target="_blank">article</a>&nbsp;to resolve the issue and continue importing template.%3$s', 'templatiq-sites' ), '<p>', esc_url( 'https://wpastra.com/docs/internal-server-error-starter-templates/' ), '</p>' ),
				'cta_links'                          => $this->get_cta_links(),
				'cta_quick_corner_links'             => $this->get_cta_links( 'quick-links-corner' ),
				'cta_premium_popup_links'            => $this->get_cta_links( 'get-premium-access-popup' ),
				'cta_link'                           => $this->get_cta_link(),
				'cta_quick_corner_link'              => $this->get_cta_link( 'quick-links-corner' ),
				'cta_premium_popup_link'             => $this->get_cta_link( 'get-premium-access-popup' ),

				/* translators: %s URL to document. */
				'process_failed_primary'             => sprintf( __( '%1$sWe could not complete the import process due to failed AJAX request and this is the message:%2$s', 'templatiq-sites' ), '<p>', '</p>' ),
				/* translators: %s URL to document. */
				'process_failed_secondary'           => sprintf( __( '%1$sPlease report this <a href="%2$s" target="_blank">here</a>.%3$s', 'templatiq-sites' ), '<p>', esc_url( 'https://wpastra.com/starter-templates-support/?url=#DEMO_URL#&subject=#SUBJECT#' ), '</p>' ),
				'st_page_url'                        => admin_url( 'themes.php?page=starter-templates' ),
				'staging_connected'                  => apply_filters( 'templatiq_sites_staging_connected', '' ),
				'isRTLEnabled'                       => is_rtl(),
				/* translators: %s Anchor link to support URL. */
				'support_text'                       => sprintf( __( 'Please report this error %1$shere%2$s, so we can fix it.', 'templatiq-sites' ), '<a href="https://wpastra.com/support/open-a-ticket/" target="_blank">', '</a>' ),
				'surecart_store_exists'              => isset( $surecart_store_exist ) ? $surecart_store_exist : false,
			]
		);

		return $data;
	}

	/**
	 * Display subscription form
	 *
	 * @since 2.6.1
	 *
	 * @return boolean
	 */
	public function should_display_subscription_form() {

		$subscription = apply_filters( 'templatiq_sites_should_display_subscription_form', null );
		if ( null !== $subscription ) {
			return $subscription;
		}

		// Is WhiteLabel enabled?
		if ( Templatiq_Sites_White_Label::get_instance()->is_white_labeled() ) {
			return false;
		}

		// Is Premium Starter Templates pluign?
		if ( defined( 'TEMPLATIQ_PRO_SITES_NAME' ) ) {
			return false;
		}

		// User already subscribed?
		$subscribed = get_user_meta( get_current_user_ID(), 'templatiq-sites-subscribed', true );
		if ( $subscribed ) {
			return false;
		}

		return true;
	}

	/**
	 * Import Compatibility Errors
	 *
	 * @since 2.0.0
	 * @return mixed
	 */
	public function get_compatibilities_data() {
		return [
			'xmlreader'            => [
				'title'   => esc_html__( 'XMLReader Support Missing', 'templatiq-sites' ),
				/* translators: %s doc link. */
				'tooltip' => '<p>' . esc_html__( 'You\'re close to importing the template. To complete the process, enable XMLReader support on your website..', 'templatiq-sites' ) . '</p><p>' . sprintf( __( 'Read an article <a href="%s" target="_blank">here</a> to resolve the issue.', 'templatiq-sites' ), 'https://wpastra.com/docs/xmlreader-missing/' ) . '</p>',
			],
			'curl'                 => [
				'title'   => esc_html__( 'cURL Support Missing', 'templatiq-sites' ),
				/* translators: %s doc link. */
				'tooltip' => '<p>' . esc_html__( 'To run a smooth import, kindly enable cURL support on your website.', 'templatiq-sites' ) . '</p><p>' . sprintf( __( 'Read an article <a href="%s" target="_blank">here</a> to resolve the issue.', 'templatiq-sites' ), 'https://wpastra.com/docs/curl-support-missing/' ) . '</p>',
			],
			'wp-debug'             => [
				'title'   => esc_html__( 'Disable Debug Mode', 'templatiq-sites' ),
				/* translators: %s doc link. */
				'tooltip' => '<p>' . esc_html__( 'WordPress debug mode is currently enabled on your website. With this, any errors from third-party plugins might affect the import process.', 'templatiq-sites' ) . '</p><p>' . esc_html__( 'Kindly disable it to continue importing the Starter Template. To do so, you can add the following code into the wp-config.php file.', 'templatiq-sites' ) . '</p><p><code>define(\'WP_DEBUG\', false);</code></p><p>' . sprintf( __( 'Read an article <a href="%s" target="_blank">here</a> to resolve the issue.', 'templatiq-sites' ), 'https://wpastra.com/docs/disable-debug-mode/' ) . '</p>',
			],
			'update-available'     => [
				'title'   => esc_html__( 'Update Plugin', 'templatiq-sites' ),
				/* translators: %s update page link. */
				'tooltip' => '<p>' . esc_html__( 'Updates are available for plugins used in this starter template.', 'templatiq-sites' ) . '</p>##LIST##<p>' . sprintf( __( 'Kindly <a href="%s" target="_blank">update</a> them for a successful import. Skipping this step might break the template design/feature.', 'templatiq-sites' ), esc_url( network_admin_url( 'update-core.php' ) ) ) . '</p>',
			],
			'third-party-required' => [
				'title'   => esc_html__( 'Required Plugins Missing', 'templatiq-sites' ),
				'tooltip' => '<p>' . esc_html__( 'This starter template requires premium plugins. As these are third party premium plugins, you\'ll need to purchase, install and activate them first.', 'templatiq-sites' ) . '</p>',
			],
			'dynamic-page'         => [
				'title'   => esc_html__( 'Dynamic Page', 'templatiq-sites' ),
				'tooltip' => '<p>' . esc_html__( 'The page template you are about to import contains a dynamic widget/module. Please note this dynamic data will not be available with the imported page.', 'templatiq-sites' ) . '</p><p>' . esc_html__( 'You will need to add it manually on the page.', 'templatiq-sites' ) . '</p><p>' . esc_html__( 'This dynamic content will be available when you import the entire site.', 'templatiq-sites' ) . '</p>',
			],
		];
	}

	/**
	 * Get all compatibilities
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_compatibilities() {

		$data = $this->get_compatibilities_data();

		$compatibilities = [
			'errors'   => [],
			'warnings' => [],
		];

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$compatibilities['warnings']['wp-debug'] = $data['wp-debug'];
		}

		if ( ! class_exists( 'XMLReader' ) ) {
			$compatibilities['errors']['xmlreader'] = $data['xmlreader'];
		}

		if ( ! function_exists( 'curl_version' ) ) {
			$compatibilities['errors']['curl'] = $data['curl'];
		}

		return $compatibilities;
	}

	/**
	 * Register module required js on elementor's action.
	 *
	 * @since 2.0.0
	 */
	public function register_widget_scripts() {

		$page_builders = self::get_instance()->get_page_builders();
		$has_elementor = false;

		// Use this filter to remove the Starter Templates button from Elementor Editor.
		$elementor_add_ast_site_button = apply_filters( 'starter_templates_hide_elementor_button', false );

		foreach ( $page_builders as $page_builder ) {

			if ( 'elementor' === $page_builder['slug'] ) {
				$has_elementor = true;
			}
		}

		if ( ! $has_elementor ) {
			return;
		}

		if ( $elementor_add_ast_site_button ) {
			return;
		}

		wp_enqueue_script( 'templatiq-sites-helper', TEMPLATIQ_SITES_URI . 'inc/assets/js/helper.js', ['jquery'], TEMPLATIQ_SITES_VER, true );

		wp_enqueue_script( 'masonry' );
		wp_enqueue_script( 'imagesloaded' );

		// Image Search assets.
		$this->image_search_assets();

		wp_enqueue_script( 'templatiq-sites-elementor-admin-page', TEMPLATIQ_SITES_URI . 'inc/assets/js/elementor-admin-page.js', ['jquery', 'wp-util', 'updates', 'masonry', 'imagesloaded'], TEMPLATIQ_SITES_VER, true );
		wp_add_inline_script( 'templatiq-sites-elementor-admin-page', sprintf( 'var pagenow = "%s";', TEMPLATIQ_SITES_NAME ), 'after' );
		wp_enqueue_style( 'templatiq-sites-admin', TEMPLATIQ_SITES_URI . 'inc/assets/css/admin.css', TEMPLATIQ_SITES_VER, true );
		wp_style_add_data( 'templatiq-sites-admin', 'rtl', 'replace' );

		$license_status = false;
		if ( is_callable( 'BSF_License_Manager::bsf_is_active_license' ) ) {
			$license_status = BSF_License_Manager::bsf_is_active_license( 'astra-pro-sites' );
		}

		/* translators: %s are link. */
		$license_msg = sprintf( __( 'This is a premium template available with Essential Bundle and Business Toolkits. you can purchase it from <a href="%s" target="_blank">here</a>.', 'templatiq-sites' ), 'https://wpastra.com/starter-templates-plans/' );

		if ( defined( 'TEMPLATIQ_PRO_SITES_NAME' ) ) {
			/* translators: %s are link. */
			$license_msg = sprintf( __( 'This is a premium template available with Essential Bundle and Business Toolkits. <a href="%s" target="_blank">Validate Your License</a> Key to import this template.', 'templatiq-sites' ), esc_url( admin_url( 'plugins.php?bsf-inline-license-form=astra-pro-sites' ) ) );
		}

		$last_viewed_block_data = [];
		// Retrieve the value of the 'blockID' parameter using filter_input().
		$id = filter_input( INPUT_GET, 'blockID', FILTER_SANITIZE_STRING );
		if ( ! empty( $id ) ) {
			$last_viewed_block_data = get_option( 'templatiq_sites_import_elementor_data_' . $id ) !== false ? get_option( 'templatiq_sites_import_elementor_data_' . $id ) : [];
		}

		$data = apply_filters(
			'templatiq_sites_render_localize_vars',
			[
				'plugin_name'                => Templatiq_Sites_White_Label::get_instance()->get_white_label_name(),
				'sites'                      => templatiq_sites_get_api_params(),
				'version'                    => TEMPLATIQ_SITES_VER,
				'settings'                   => [],
				'page-builders'              => [],
				'categories'                 => [],
				'default_page_builder'       => 'elementor',
				'templatiq_blocks'           => $this->get_all_blocks(),
				'license_status'             => $license_status,
				'ajaxurl'                    => esc_url( admin_url( 'admin-ajax.php' ) ),
				'default_page_builder_sites' => Templatiq_Sites_Page::get_instance()->get_sites_by_page_builder( 'elementor' ),
				'ApiURL'                     => $this->api_url,
				'_ajax_nonce'                => wp_create_nonce( 'templatiq-sites' ),
				'isPro'                      => defined( 'TEMPLATIQ_PRO_SITES_NAME' ) ? true : false,
				'license_msg'                => $license_msg,
				'isWhiteLabeled'             => Templatiq_Sites_White_Label::get_instance()->is_white_labeled(),
				'getProText'                 => __( 'Get Access!', 'templatiq-sites' ),
				'getProURL'                  => esc_url( 'https://wpastra.com/starter-templates-plans/?utm_source=demo-import-panel&utm_campaign=templatiq-sites&utm_medium=wp-dashboard' ),
				'templatiq_block_categories' => $this->get_api_option( 'astra-blocks-categories' ),
				'siteURL'                    => site_url(),
				'template'                   => esc_html__( 'Template', 'templatiq-sites' ),
				'block'                      => esc_html__( 'Block', 'templatiq-sites' ),
				'dismiss_text'               => esc_html__( 'Dismiss', 'templatiq-sites' ),
				'install_plugin_text'        => esc_html__( 'Install Required Plugins', 'templatiq-sites' ),
				'syncCompleteMessage'        => self::get_instance()->get_sync_complete_message(),
				/* translators: %s are link. */
				'page_settings'              => [
					'message'  => __( 'You can locate <strong>Starter Templates Settings</strong> under the <strong>Page Settings</strong> of the Style Tab.', 'templatiq-sites' ),
					'url'      => '#',
					'url_text' => __( 'Read More →', 'templatiq-sites' ),
				],

				'last_viewed_block_data'     => $last_viewed_block_data,
			]
		);

		wp_localize_script( 'templatiq-sites-elementor-admin-page', 'astraElementorSites', $data );
	}

	/**
	 * Register module required js on elementor's action.
	 *
	 * @since 2.0.0
	 */
	public function popup_styles() {

		wp_enqueue_style( 'templatiq-sites-elementor-admin-page', TEMPLATIQ_SITES_URI . 'inc/assets/css/elementor-admin.css', TEMPLATIQ_SITES_VER, true );
		wp_enqueue_style( 'templatiq-sites-elementor-admin-page-dark', TEMPLATIQ_SITES_URI . 'inc/assets/css/elementor-admin-dark.css', TEMPLATIQ_SITES_VER, true );
		wp_style_add_data( 'templatiq-sites-elementor-admin-page', 'rtl', 'replace' );

	}

	/**
	 * Get all sites
	 *
	 * @since 2.0.0
	 * @return array All sites.
	 */
	public function get_all_sites() {
		$sites_and_pages = [];
		$total_requests  = (int) get_site_option( 'templatiq-sites-requests', 0 );

		for ( $page = 1; $page <= $total_requests; $page++ ) {
			$current_page_data = get_site_option( 'templatiq-sites-and-pages-page-' . $page, [] );
			if ( ! empty( $current_page_data ) ) {
				foreach ( $current_page_data as $page_id => $page_data ) {
					$sites_and_pages[$page_id] = $page_data;
				}
			}
		}

		return $sites_and_pages;
	}

	/**
	 * Get all sites
	 *
	 * @since 2.2.4
	 * @param  array $option Site options name.
	 * @return array Site Option value.
	 */
	public function get_api_option( $option ) {
		return get_site_option( $option, [] );
	}

	/**
	 * Get all blocks
	 *
	 * @since 2.0.0
	 * @return array All Elementor Blocks.
	 */
	public function get_all_blocks() {

		$blocks         = [];
		$total_requests = (int) get_site_option( 'astra-blocks-requests', 0 );

		for ( $page = 1; $page <= $total_requests; $page++ ) {
			$current_page_data = get_site_option( 'astra-blocks-' . $page, [] );
			if ( ! empty( $current_page_data ) ) {
				foreach ( $current_page_data as $page_id => $page_data ) {
					$blocks[$page_id] = $page_data;
				}
			}
		}

		return $blocks;
	}

	/**
	 * Load all the required files in the importer.
	 *
	 * @since  1.0.0
	 */
	private function includes() {

		require_once TEMPLATIQ_SITES_DIR . 'inc/classes/functions.php';
		require_once TEMPLATIQ_SITES_DIR . 'inc/classes/class-templatiq-sites-error-handler.php';
		require_once TEMPLATIQ_SITES_DIR . 'inc/classes/class-templatiq-sites-white-label.php';
		require_once TEMPLATIQ_SITES_DIR . 'inc/classes/class-templatiq-sites-page.php';
		require_once TEMPLATIQ_SITES_DIR . 'inc/classes/class-templatiq-sites-elementor-pages.php';
		require_once TEMPLATIQ_SITES_DIR . 'inc/classes/class-templatiq-sites-elementor-images.php';
		// require_once TEMPLATIQ_SITES_DIR . 'inc/classes/compatibility/class-templatiq-sites-compatibility.php';
		require_once TEMPLATIQ_SITES_DIR . 'inc/classes/class-templatiq-sites-importer.php';
		require_once TEMPLATIQ_SITES_DIR . 'inc/classes/class-templatiq-sites-image-processing.php';
		require_once TEMPLATIQ_SITES_DIR . 'inc/classes/class-templatiq-sites-wp-cli.php';
		// require_once TEMPLATIQ_SITES_DIR . 'inc/lib/class-templatiq-sites-ast-block-templates.php';
		require_once TEMPLATIQ_SITES_DIR . 'inc/lib/onboarding/class-onboarding.php';
		require_once TEMPLATIQ_SITES_DIR . 'inc/lib/zip-ai/zip-ai.php';

		// Batch Import.
		require_once TEMPLATIQ_SITES_DIR . 'inc/classes/batch-import/class-templatiq-sites-batch-import.php';
	}

	/**
	 * Required Plugin Activate
	 *
	 * @since 2.0.0 Added parameters $init, $options & $enabled_extensions to add the WP CLI support.
	 * @since 1.0.0
	 * @param  string $init               Plugin init file.
	 * @param  array  $options            Site options.
	 * @param  array  $enabled_extensions Enabled extensions.
	 * @return void
	 */
	public function required_plugin_activate( $init = '', $options = [], $enabled_extensions = [] ) {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'install_plugins' ) || ! isset( $_POST['init'] ) || ! sanitize_text_field( $_POST['init'] ) ) {
				wp_send_json_error(
					[
						'success' => false,
						'message' => __( 'Error: You don\'t have the required permissions to install plugins.', 'templatiq-sites' ),
					]
				);
			}
		}

		Templatiq_Sites_Error_Handler::get_instance()->start_error_handler();

		$plugin_init = ( isset( $_POST['init'] ) ) ? esc_attr( sanitize_text_field( $_POST['init'] ) ) : $init;
		$activate    = activate_plugin( $plugin_init, '', false, false );

		Templatiq_Sites_Error_Handler::get_instance()->stop_error_handler();

		if ( is_wp_error( $activate ) ) {
			if ( defined( 'WP_CLI' ) ) {
				WP_CLI::error( 'Plugin Activation Error: ' . $activate->get_error_message() );
			} elseif ( wp_doing_ajax() ) {
				wp_send_json_error(
					[
						'success' => false,
						'message' => $activate->get_error_message(),
					]
				);
			}
		}

		$options            = templatiq_get_site_data( 'astra-site-options-data' );
		$enabled_extensions = templatiq_get_site_data( 'astra-enabled-extensions' );

		$this->after_plugin_activate( $plugin_init, $options, $enabled_extensions );

		if ( defined( 'WP_CLI' ) ) {
			WP_CLI::line( 'Plugin Activated!' );
		} elseif ( wp_doing_ajax() ) {
			wp_send_json_success(
				[
					'success' => true,
					'message' => __( 'Plugin Activated', 'templatiq-sites' ),
				]
			);
		}
	}

	/**
	 * Retrieves the required plugins data based on the response and required plugin list.
	 *
	 * @param array $response            The response containing the plugin data.
	 * @param array $required_plugins    The list of required plugins.
	 * @since 3.2.5
	 * @return array                     The array of required plugins data.
	 */
	public function get_required_plugins_data( $response, $required_plugins ) {

		$learndash_course_grid = 'https://www.learndash.com/add-on/course-grid/';
		$learndash_woocommerce = 'https://www.learndash.com/add-on/woocommerce/';
		if ( is_plugin_active( 'sfwd-lms/sfwd_lms.php' ) ) {
			$learndash_addons_url  = admin_url( 'admin.php?page=learndash_lms_addons' );
			$learndash_course_grid = $learndash_addons_url;
			$learndash_woocommerce = $learndash_addons_url;
		}

		$third_party_required_plugins = [];
		$third_party_plugins          = [
			'sfwd-lms'              => [
				'init' => 'sfwd-lms/sfwd_lms.php',
				'name' => 'LearnDash LMS',
				'link' => 'https://www.learndash.com/',
			],
			'learndash-course-grid' => [
				'init' => 'learndash-course-grid/learndash_course_grid.php',
				'name' => 'LearnDash Course Grid',
				'link' => $learndash_course_grid,
			],
			'learndash-woocommerce' => [
				'init' => 'learndash-woocommerce/learndash_woocommerce.php',
				'name' => 'LearnDash WooCommerce Integration',
				'link' => $learndash_woocommerce,
			],
		];

		$plugin_updates           = get_plugin_updates();
		$update_available_plugins = [];
		$incompatible_plugins     = [];

		if ( ! empty( $required_plugins ) ) {
			$php_version = Templatiq_Sites_Onboarding_Setup::get_instance()->get_php_version();
			foreach ( $required_plugins as $key => $plugin ) {

				$plugin = (array) $plugin;

				if ( 'woocommerce' === $plugin['slug'] && version_compare( $php_version, '7.0', '<' ) ) {
					$plugin['min_php_version'] = '7.0';
					$incompatible_plugins[]    = $plugin;
				}

				if ( 'presto-player' === $plugin['slug'] && version_compare( $php_version, '7.3', '<' ) ) {
					$plugin['min_php_version'] = '7.3';
					$incompatible_plugins[]    = $plugin;
				}

				/**
				 * Has Pro Version Support?
				 * And
				 * Is Pro Version Installed?
				 */
				$plugin_pro = $this->pro_plugin_exist( $plugin['init'] );
				if ( $plugin_pro ) {

					if ( array_key_exists( $plugin_pro['init'], $plugin_updates ) ) {
						$update_available_plugins[] = $plugin_pro;
					}

					// Pro - Active.
					if ( is_plugin_active( $plugin_pro['init'] ) ) {
						$response['active'][] = $plugin_pro;

						$this->after_plugin_activate( $plugin['init'], $options, $enabled_extensions );

						// Pro - Inactive.
					} else {
						$response['inactive'][] = $plugin_pro;
					}
				} else {
					if ( array_key_exists( $plugin['init'], $plugin_updates ) ) {
						$update_available_plugins[] = $plugin;
					}

					// Lite - Installed but Inactive.
					if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin['init'] ) && is_plugin_inactive( $plugin['init'] ) ) {
						$link = wp_nonce_url(
							add_query_arg(
								[
									'action' => 'activate',
									'plugin' => $plugin['init'],
								],
								admin_url( 'plugins.php' )
							),
							'activate-plugin_' . $plugin['init']
						);
						$link                   = str_replace( '&amp;', '&', $link );
						$plugin['action']       = $link;
						$response['inactive'][] = $plugin;

						// Lite - Not Installed.
					} elseif ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin['init'] ) ) {

						// Added premium plugins which need to install first.
						if ( array_key_exists( $plugin['slug'], $third_party_plugins ) ) {
							$third_party_required_plugins[] = $third_party_plugins[$plugin['slug']];
						} else {
							$link = wp_nonce_url(
								add_query_arg(
									[
										'action' => 'install-plugin',
										'plugin' => $plugin['slug'],
									],
									admin_url( 'update.php' )
								),
								'install-plugin_' . $plugin['slug']
							);
							$link                       = str_replace( '&amp;', '&', $link );
							$plugin['action']           = $link;
							$response['notinstalled'][] = $plugin;
						}

						// Lite - Active.
					} else {
						$response['active'][] = $plugin;

						$this->after_plugin_activate( $plugin['init'], $options, $enabled_extensions );
					}
				}
			}
		}

		// Checking the `install_plugins` and `activate_plugins` capability for the current user.
		// To perform plugin installation process.
		if (
			( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) &&
			(  ( ! current_user_can( 'install_plugins' ) && ! empty( $response['notinstalled'] ) ) || ( ! current_user_can( 'activate_plugins' ) && ! empty( $response['inactive'] ) ) ) ) {
			$message               = __( 'Insufficient Permission. Please contact your Super Admin to allow the install required plugin permissions.', 'templatiq-sites' );
			$required_plugins_list = array_merge( $response['notinstalled'], $response['inactive'] );
			$markup                = $message;
			$markup .= '<ul>';
			foreach ( $required_plugins_list as $key => $required_plugin ) {
				$markup .= '<li>' . esc_html( $required_plugin['name'] ) . '</li>';
			}
			$markup .= '</ul>';

			wp_send_json_error( $markup );
		}

		$data = [
			'required_plugins'             => $response,
			'third_party_required_plugins' => $third_party_required_plugins,
			'update_available_plugins'     => $update_available_plugins,
			'incompatible_plugins'         => $incompatible_plugins,
		];

		return $data;
	}

	/**
	 * Required Plugins
	 *
	 * @since 2.0.0
	 *
	 * @param  array $required_plugins Required Plugins.
	 * @param  array $options            Site Options.
	 * @param  array $enabled_extensions Enabled Extensions.
	 * @return mixed
	 */
	public function required_plugin( $required_plugins = [], $options = [], $enabled_extensions = [] ) {

		// Verify Nonce.
		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_send_json_error();
			}
		}

		$response = [
			'active'       => [],
			'inactive'     => [],
			'notinstalled' => [],
		];

		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : '';
		$screen = isset( $_POST['screen'] ) ? sanitize_text_field( $_POST['screen'] ) : '';

		if ( 'elementor' === $screen ) {
			$options            = [];
			$enabled_extensions = [];
			$imported_demo_data = get_option( 'templatiq_sites_import_elementor_data_' . $id, [] );
			if ( 'astra-blocks' === $imported_demo_data['type'] ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
				$plugins          = unserialize( $imported_demo_data['post-meta']['astra-blocks-required-plugins'] ); // The use of `unserialize()` is necessary in this case to deserialize trusted serialized data.
				$required_plugins = false !== $plugins ? $plugins : [];
			} else {
				$required_plugins = isset( $imported_demo_data['site-pages-required-plugins'] ) ? $imported_demo_data['site-pages-required-plugins'] : [];
			}
		} else {
			$options            = templatiq_get_site_data( 'astra-site-options-data' );
			$enabled_extensions = templatiq_get_site_data( 'astra-enabled-extensions' );
			$required_plugins   = templatiq_get_site_data( 'required-plugins' );
		}

		error_log( print_r( $response, true ) );

		$data = $this->get_required_plugins_data( $response, $required_plugins );

		if ( wp_doing_ajax() ) {
			wp_send_json_success( $data );
		} else {
			return $data;
		}
	}

	/**
	 * After Plugin Activate
	 *
	 * @since 2.0.0
	 *
	 * @param  string $plugin_init        Plugin Init File.
	 * @param  array  $options            Site Options.
	 * @param  array  $enabled_extensions Enabled Extensions.
	 * @return void
	 */
	public function after_plugin_activate( $plugin_init = '', $options = [], $enabled_extensions = [] ) {
		$data = [
			'templatiq_site_options' => $options,
			'enabled_extensions'     => $enabled_extensions,
		];

		do_action( 'templatiq_sites_after_plugin_activation', $plugin_init, $data );
	}

	/**
	 * Has Pro Version Support?
	 * And
	 * Is Pro Version Installed?
	 *
	 * Check Pro plugin version exist of requested plugin lite version.
	 *
	 * Eg. If plugin 'BB Lite Version' required to import demo. Then we check the 'BB Agency Version' is exist?
	 * If yes then we only 'Activate' Agency Version. [We couldn't install agency version.]
	 * Else we 'Activate' or 'Install' Lite Version.
	 *
	 * @since 1.0.1
	 *
	 * @param  string $lite_version Lite version init file.
	 * @return mixed               Return false if not installed or not supported by us
	 *                                    else return 'Pro' version details.
	 */
	public function pro_plugin_exist( $lite_version = '' ) {

		// Lite init => Pro init.
		$plugins = apply_filters(
			'templatiq_sites_pro_plugin_exist',
			[
				'beaver-builder-lite-version/fl-builder.php'                    => [
					'slug' => 'bb-plugin',
					'init' => 'bb-plugin/fl-builder.php',
					'name' => 'Beaver Builder Plugin',
				],
				'ultimate-addons-for-beaver-builder-lite/bb-ultimate-addon.php' => [
					'slug' => 'bb-ultimate-addon',
					'init' => 'bb-ultimate-addon/bb-ultimate-addon.php',
					'name' => 'Ultimate Addon for Beaver Builder',
				],
				'wpforms-lite/wpforms.php'                                      => [
					'slug' => 'wpforms',
					'init' => 'wpforms/wpforms.php',
					'name' => 'WPForms',
				],
			],
			$lite_version
		);

		if ( isset( $plugins[$lite_version] ) ) {

			// Pro plugin directory exist?
			if ( file_exists( WP_PLUGIN_DIR . '/' . $plugins[$lite_version]['init'] ) ) {
				return $plugins[$lite_version];
			}
		}

		return false;
	}

	/**
	 * Get Default Page Builders
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_default_page_builders() {
		return [
			[
				'id'   => 42,
				'slug' => 'gutenberg',
				'name' => 'Gutenberg',
			],
			[
				'id'   => 33,
				'slug' => 'elementor',
				'name' => 'Elementor',
			],
			[
				'id'   => 34,
				'slug' => 'beaver-builder',
				'name' => 'Beaver Builder',
			],
			[
				'id'   => 41,
				'slug' => 'brizy',
				'name' => 'Brizy',
			],
		];
	}

	/**
	 * Get Page Builders
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_page_builders() {
		return $this->get_default_page_builders();
	}

	/**
	 * Get Page Builder Filed
	 *
	 * @since 2.0.0
	 * @param  string $page_builder Page Bulider.
	 * @param  string $field        Field name.
	 * @return mixed
	 */
	public function get_page_builder_field( $page_builder = '', $field = '' ) {
		if ( empty( $page_builder ) ) {
			return '';
		}

		$page_builders = self::get_instance()->get_page_builders();
		if ( empty( $page_builders ) ) {
			return '';
		}

		foreach ( $page_builders as $key => $current_page_builder ) {
			if ( $page_builder === $current_page_builder['slug'] ) {
				if ( isset( $current_page_builder[$field] ) ) {
					return $current_page_builder[$field];
				}
			}
		}

		return '';
	}

	/**
	 * Get License Key
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_license_key() {
		if ( class_exists( 'BSF_License_Manager' ) ) {
			if ( BSF_License_Manager::bsf_is_active_license( 'astra-pro-sites' ) ) {
				return BSF_License_Manager::instance()->bsf_get_product_info( 'astra-pro-sites', 'purchase_key' );
			}
		}

		return '';
	}

	/**
	 * Get Sync Complete Message
	 *
	 * @since 2.0.0
	 * @param  boolean $echo Echo the message.
	 * @return mixed
	 */
	public function get_sync_complete_message( $echo = false ) {

		$message = __( 'Template library refreshed!', 'templatiq-sites' );

		if ( $echo ) {
			echo esc_html( $message );
		} else {
			return esc_html( $message );
		}
	}

	/**
	 * Get an instance of WP_Filesystem_Direct.
	 *
	 * @since 2.0.0
	 * @return object A WP_Filesystem_Direct instance.
	 */
	public static function get_filesystem() {
		global $wp_filesystem;

		require_once ABSPATH . '/wp-admin/includes/file.php';

		WP_Filesystem();

		return $wp_filesystem;
	}

	/**
	 * Get the status of file system permission of "/wp-content/uploads" directory.
	 *
	 * @return void
	 */
	public function filesystem_permission() {
		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			check_ajax_referer( 'templatiq-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( __( 'You do not have permission to perform this action.', 'templatiq-sites' ) );
			}
		}
		$wp_upload_path = wp_upload_dir();
		$permissions    = [
			'is_readable' => false,
			'is_writable' => false,
		];

		foreach ( $permissions as $file_permission => $value ) {
			$permissions[$file_permission] = $file_permission( $wp_upload_path['basedir'] );
		}

		$permissions['is_wp_filesystem'] = true;
		if ( ! WP_Filesystem() ) {
			$permissions['is_wp_filesystem'] = false;
		}

		if ( defined( 'WP_CLI' ) ) {
			if ( ! $permissions['is_readable'] || ! $permissions['is_writable'] || ! $permissions['is_wp_filesystem'] ) {
				WP_CLI::error( esc_html__( 'Please contact the hosting service provider to help you update the permissions so that you can successfully import a complete template.', 'templatiq-sites' ) );
			}
		} else {
			wp_send_json_success(
				[
					'permissions' => $permissions,
					'directory'   => $wp_upload_path['basedir'],
				]
			);
		}
	}

	/**
	 * Display notice on dashboard if WP_Filesystem() false.
	 *
	 * @return void
	 */
	public function check_filesystem_access_notice() {
		// Check if WP_Filesystem() returns false.
		if ( ! WP_Filesystem() ) {
			// Display a notice on the dashboard.
			echo '<div class="error"><p>' . esc_html__( 'Required WP_Filesystem Permissions to import the templates from Starter Templates are missing.', 'templatiq-sites' ) . '</p></div>';
		}
	}
}

/**
 * Kicking this off by calling 'get_instance()' method
 */
Templatiq_Sites::get_instance();

endif;
