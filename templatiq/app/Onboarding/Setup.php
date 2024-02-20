<?php
/**
 * @author  wpWax
 * @since   1.0.0
 * @version 1.0.0
 */

namespace Templatiq\Onboarding;

use Templatiq\FullSite\FullSite;
use Templatiq\Utils\Singleton;

class Setup {
	use Singleton;

	public static $fse_logo_attributes = [];

	public function __construct() {
		// add_action( 'wp_ajax_templatiq_sites_set_site_data', [$this, 'set_fse_site_data'] );
		add_action( 'wp_ajax_templatiq_sites_set_site_data', [$this, 'set_site_data'] );

		add_action( 'wp_ajax_report_error', [$this, 'report_error'] );
		add_action( 'st_before_sending_error_report', [$this, 'delete_transient_for_import_process'] );
		add_action( 'st_before_sending_error_report', [$this, 'temporary_cache_errors'], 10, 1 );
	}

	public function temporary_cache_errors( $posted_data ) {
		update_option( 'templatiq_sites_cached_import_error', $posted_data, 'no' );
	}

	public function delete_transient_for_import_process() {
		delete_transient( 'templatiq_sites_import_started' );
	}

	public function report_error() {
		$api_url = add_query_arg( [], trailingslashit( FullSite::init()->get_api_domain() ) . 'wp-json/starter-templates/v2/import-error/' );

		if ( ! templatiq_sites_is_valid_url( $api_url ) ) {
			wp_send_json_error(
				[
					'message' => sprintf( __( 'Invalid Request URL - %s', 'templatiq' ), $api_url ),
					'code'    => 'Error',
				]
			);
		}

		$post_id           = ( isset( $_POST['id'] ) ) ? intval( $_POST['id'] ) : 0;
		$user_agent_string = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';

		if ( 0 === $post_id ) {
			wp_send_json_error(
				[
					'message' => sprintf( __( 'Invalid Post ID - %d', 'templatiq' ), $post_id ),
					'code'    => 'Error',
				]
			);
		}

		$api_args = [
			'timeout'  => 3,
			'blocking' => true,
			'body'     => [
				'url'        => esc_url( site_url() ),
				'err'        => stripslashes( $_POST['error'] ),
				'id'         => $_POST['id'],
				'logfile'    => $this->get_log_file_path(),
				'version'    => TEMPLATIQ_SITES_VER,
				'abspath'    => ABSPATH,
				'user_agent' => $user_agent_string,
				'server'     => [
					'php_version'            => $this->get_php_version(),
					'php_post_max_size'      => ini_get( 'post_max_size' ),
					'php_max_execution_time' => ini_get( 'max_execution_time' ),
					'max_input_time'         => ini_get( 'max_input_time' ),
					'php_memory_limit'       => ini_get( 'memory_limit' ),
					'php_max_input_vars'     => ini_get( 'max_input_vars' ), // phpcs:ignore:PHPCompatibility.IniDirectives.NewIniDirectives.max_input_varsFound
				],
			],
		];

		do_action( 'st_before_sending_error_report', $api_args['body'] );

		$request = wp_remote_post( $api_url, $api_args );

		do_action( 'st_after_sending_error_report', $api_args['body'], $request );

		if ( is_wp_error( $request ) ) {
			wp_send_json_error( $request );
		}

		$code = (int) wp_remote_retrieve_response_code( $request );
		$data = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( 200 === $code ) {
			wp_send_json_success( $data );
		}

		wp_send_json_error( $data );
	}

	public function get_log_file_path() {
		$log_file = get_option( 'templatiq_sites_recent_import_log_file', false );
		if ( ! empty( $log_file ) && isset( $log_file ) ) {
			return str_replace( ABSPATH, esc_url( site_url() ) . '/', $log_file );
		}

		return "";
	}

	public function get_php_version() {
		if ( defined( 'PHP_MAJOR_VERSION' ) && defined( 'PHP_MINOR_VERSION' ) && defined( 'PHP_RELEASE_VERSION' ) ) { // phpcs:ignore
			return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
		}

		return phpversion();
	}

	public function set_site_data() {
		check_ajax_referer( 'templatiq-sites-set-ai-site-data', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'success' => false,
					'message' => __( 'You are not authorized to perform this action.', 'templatiq' ),
				]
			);
		}

		$param = isset( $_POST['param'] ) ? sanitize_text_field( $_POST['param'] ) : '';

		if ( empty( $param ) ) {
			wp_send_json_error();
		}

		// error_log( "\n" .'***** Updating Site Data ******');

		switch ( $param ) {

			case 'site-title':
				$business_name = isset( $_POST['business-name'] ) ? sanitize_text_field( stripslashes( $_POST['business-name'] ) ) : '';
				if ( ! empty( $business_name ) ) {
					update_option( 'blogname', $business_name );
					error_log( 'Site Title Added' );
				}

				break;

			case 'site-logo' === $param:
				$logo_id = isset( $_POST['logo'] ) ? sanitize_text_field( $_POST['logo'] ) : '';

				if ( $logo_id ) {
					set_theme_mod( 'custom_logo', $logo_id );
					update_option( 'site_logo', $logo_id );
					error_log( 'Logo Inserted from set_site_data()' );
				}

				break;

			case 'site-colors' === $param:
				$palette = isset( $_POST['palette'] ) ? (array) json_decode( stripslashes( $_POST['palette'] ) ) : [];
				$colors  = isset( $palette['colors'] ) ? (array) $palette['colors'] : [];

				if ( ! empty( $colors ) ) {
					error_log( 'Site Colors Added  from set_site_data()' );
				}

				break;

			case 'site-typography' === $param && function_exists( 'templatiq_get_option' ):
				$typography = isset( $_POST['typography'] ) ? (array) json_decode( stripslashes( $_POST['typography'] ) ) : '';

				$font_size_body = isset( $typography['font-size-body'] ) ? (array) $typography['font-size-body'] : '';
				if ( ! empty( $font_size_body ) && is_array( $font_size_body ) ) {
					// templatiq_update_option( 'font-size-body', $font_size_body );
				}

				if ( ! empty( $typography['body-font-family'] ) ) {
					// templatiq_update_option( 'body-font-family', $typography['body-font-family'] );
				}

				if ( ! empty( $typography['body-font-variant'] ) ) {
					// templatiq_update_option( 'body-font-variant', $typography['body-font-variant'] );
				}

				if ( ! empty( $typography['body-font-weight'] ) ) {
					// templatiq_update_option( 'body-font-weight', $typography['body-font-weight'] );
				}

				if ( ! empty( $typography['body-line-height'] ) ) {
					// templatiq_update_option( 'body-line-height', $typography['body-line-height'] );
				}

				if ( ! empty( $typography['headings-font-family'] ) ) {
					// templatiq_update_option( 'headings-font-family', $typography['headings-font-family'] );
				}

				if ( ! empty( $typography['headings-font-weight'] ) ) {
					// templatiq_update_option( 'headings-font-weight', $typography['headings-font-weight'] );
				}

				if ( ! empty( $typography['headings-line-height'] ) ) {
					// templatiq_update_option( 'headings-line-height', $typography['headings-line-height'] );
				}

				if ( ! empty( $typography['headings-font-variant'] ) ) {
					// templatiq_update_option( 'headings-font-variant', $typography['headings-font-variant'] );
				}

				error_log( 'Site Typography Added' );

				break;
		}

		// error_log( "\n" . '**** Ended Site Data ****' . "\n" );

		wp_send_json_success();
	}

	public function set_fse_site_data() {

		check_ajax_referer( 'templatiq-sites-set-ai-site-data', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'success' => false,
					'message' => __( 'You are not authorized to perform this action.', 'templatiq' ),
				]
			);
		}

		$param = isset( $_POST['param'] ) ? sanitize_text_field( $_POST['param'] ) : '';

		if ( empty( $param ) ) {
			wp_send_json_error();
		}

		switch ( $param ) {
			case 'site-logo' === $param:
				$logo       = isset( $_POST['logo'] ) ? absint( $_POST['logo'] ) : 0;
				$logo_width = isset( $_POST['logo-width'] ) ? sanitize_text_field( $_POST['logo-width'] ) : '';

				if ( ! empty( $logo ) || ! empty( $logo_width ) ) {
					self::$fse_logo_attributes = [
						'logo_width' => $logo_width,
						'logo'       => $logo,
					];
					$this->update_fse_site_logo( 'header' );
					$this->update_fse_site_logo( 'footer' );

					update_option( 'site_logo', $logo );
				}
				break;

			case 'site-colors' === $param:
				$palette       = isset( $_POST['palette'] ) ? (array) json_decode( stripslashes( $_POST['palette'] ) ) : [];
				$colors_passed = isset( $palette['colors'] ) ? (array) $palette['colors'] : [];
				if ( ! empty( $colors_passed ) ) {
					$colors_array   = Swt\get_theme_custom_styles();
					$colors_content = $colors_array['post_content'];
					if ( $colors_content && isset( $colors_content['settings']['color']['palette']['theme'] ) ) {
						$theme_colors = $colors_content['settings']['color']['palette']['theme'];
						$set_colors   = [];
						foreach ( $theme_colors as $key => $single ) {
							$single['color'] = $colors_passed[$key];
							$set_colors[]    = $single;

						}
						$colors_content['settings']['color']['palette']['theme'] = $set_colors;
					}

					$update_colors = [
						'ID'           => $colors_array['ID'],
						'post_content' => json_encode( $colors_content ),
					];

					// Update the post into the database
					wp_update_post( $update_colors );
				}

				break;

			case 'site-typography' === $param:
				$typography_passed                              = isset( $_POST['typography'] ) ? (array) json_decode( stripslashes( $_POST['typography'] ) ) : '';
				$typography_passed['body-font-family-slug']     = isset( $typography_passed['body-font-family-slug'] ) ? $typography_passed['body-font-family-slug'] : 'inter';
				$typography_passed['headings-font-family-slug'] = isset( $typography_passed['headings-font-family-slug'] ) ? $typography_passed['headings-font-family-slug'] : 'inter';
				if ( ! empty( $typography_passed ) ) {
					if ( is_callable( 'UAGB_FSE_Fonts_Compatibility::get_instance' ) ) {
						$fse_fonts_comp_instance = new UAGB_FSE_Fonts_Compatibility();
						$fse_fonts_comp_instance->get_font_family_for_starter_template( [ucfirst( $typography_passed['body-font-family'] ), ucfirst( $typography_passed['headings-font-family'] )] );
					}
					$typography_array   = Swt\get_theme_custom_styles();
					$typography_content = $typography_array['post_content'];
					if ( $typography_content && isset( $typography_content['styles']['typography'] ) ) {
						$typography_content['styles']['typography']['fontFamily'] = 'var:preset|font-family|' . $typography_passed['body-font-family-slug'];
					}

					if ( $typography_content && isset( $typography_content['styles']['elements']['link']['typography'] ) ) {
						$typography_content['styles']['elements']['link']['typography']['fontFamily'] = 'var:preset|font-family|' . $typography_passed['body-font-family-slug'];
					}

					if ( $typography_content && isset( $typography_content['styles']['elements']['heading']['typography'] ) ) {
						$typography_content['styles']['elements']['heading']['typography']['fontFamily'] = 'var:preset|font-family|' . $typography_passed['headings-font-family-slug'];
					}

					if ( $typography_content && isset( $typography_content['styles']['elements']['button']['typography'] ) ) {
						$typography_content['styles']['elements']['button']['typography']['fontFamily'] = 'var:preset|font-family|' . $typography_passed['body-font-family-slug'];
					}

					if ( $typography_content && isset( $typography_content['styles']['elements']['h1']['typography'] ) ) {
						$typography_content['styles']['elements']['h1']['typography']['fontFamily'] = 'var:preset|font-family|' . $typography_passed['headings-font-family-slug'];
					}

					if ( $typography_content && isset( $typography_content['styles']['elements']['h2']['typography'] ) ) {
						$typography_content['styles']['elements']['h2']['typography']['fontFamily'] = 'var:preset|font-family|' . $typography_passed['headings-font-family-slug'];
					}

					if ( $typography_content && isset( $typography_content['styles']['elements']['h3']['typography'] ) ) {
						$typography_content['styles']['elements']['h3']['typography']['fontFamily'] = 'var:preset|font-family|' . $typography_passed['headings-font-family-slug'];
					}

					if ( $typography_content && isset( $typography_content['styles']['elements']['h4']['typography'] ) ) {
						$typography_content['styles']['elements']['h4']['typography']['fontFamily'] = 'var:preset|font-family|' . $typography_passed['headings-font-family-slug'];
					}

					if ( $typography_content && isset( $typography_content['styles']['elements']['h5']['typography'] ) ) {
						$typography_content['styles']['elements']['h5']['typography']['fontFamily'] = 'var:preset|font-family|' . $typography_passed['headings-font-family-slug'];
					}

					if ( $typography_content && isset( $typography_content['styles']['elements']['h6']['typography'] ) ) {
						$typography_content['styles']['elements']['h6']['typography']['fontFamily'] = 'var:preset|font-family|' . $typography_passed['headings-font-family-slug'];
					}

					$update_typography = [
						'ID'           => $typography_array['ID'],
						'post_content' => json_encode( $typography_content ),
					];

					// Update the post into the database
					wp_update_post( $update_typography );
				}

				break;
		}

		wp_send_json_success();
	}

	public function update_fse_site_logo( $post_name ) {
		$args = [
			'orderby'     => 'post_type',
			'post_status' => 'publish',
			'post_type'   => ['wp_template_part'],
			'name'        => $post_name,
		];

		$fse_posts    = get_posts( $args );
		$post_content = '';

		if ( isset( $fse_posts[0] ) && isset( $fse_posts[0]->post_content ) ) {
			$post_content = stripslashes( $fse_posts[0]->post_content );
		}

		// Define the regex pattern to match the logo code with 'site-logo-img' class
		$regex_pattern = '/<img\s[^>]*src=([\'\"]??)([^\' >]*?)\\1[^>]*>/i';
		$regex_src     = '/src=[\'"]([^\'"]+)[\'"]/i';
		$regex_width   = '/width=[\'"]([^\'"]+)[\'"]/i';

		// Search for the logo code using regex
		preg_match( $regex_pattern, $post_content, $matches );

		// Check if a match is found
		if ( ! empty( $matches ) ) {
			$logo_code = $matches[0]; // The matched logo code
			if ( strpos( $logo_code, 'width=' ) === false ) {
				// Width attribute is not present, so add it
				$width_add = 'width="' . self::$fse_logo_attributes['logo_width'] . '" />';
				$logo_code = str_replace( '/>', $width_add, $logo_code );
			}

			// Extract the src attribute using regex
			preg_match( $regex_src, $logo_code, $matches_src );
			if ( ! empty( $matches_src ) ) {
				$src_attribute = $matches_src[1]; // The value of the src attribute
				$attachment    = wp_prepare_attachment_for_js( absint( self::$fse_logo_attributes['logo'] ) );
				if ( is_wp_error( $attachment ) ) {
					return;
				}
				$post_content = str_replace( $src_attribute, $attachment['url'], $post_content );
			}

			// Extract the width attribute using regex
			preg_match( $regex_width, $logo_code, $matches_width );
			if ( ! empty( $matches_width ) ) {
				$width_attribute = $matches_width[1]; // The value of the width attribute
				$post_content    = str_replace( $width_attribute, self::$fse_logo_attributes['logo_width'], $post_content );
			}
			$update_post = [
				'ID'           => $fse_posts[0]->ID,
				'post_content' => $post_content,
			];

			// Update the post into the database
			wp_update_post( $update_post );
		}
	}
}