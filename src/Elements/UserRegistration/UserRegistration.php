<?php
/**
 * @author  wpWax
 * @since   1.0.0
 * @version 1.0.0
 */

namespace TemplateMarket\Elements\UserRegistration;

use TemplateMarket\Elements\UserLogin\Styles as LoginStyles;
use Directorist\Helper as DirectoristHelper;
use TemplateMarket\Elements\Common\TextControls;
use TemplateMarket\Elements\Common\Container;
use Elementor\Controls_Manager;
use TemplateMarket\Utils\Helper;
use Elementor\Widget_Base;
use ATBDP_Permalink;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UserRegistration extends Widget_Base {

	use Styles;
	use Container;
	use LoginStyles;
	use TextControls;

	public function get_name() {
		return 'directorist_custom_registration';
	}

	public function get_title() {
		return __( 'Registration', 'template-market' );
	}

	public function get_icon() {
		return 'directorist-el-custom';
	}

	public function get_categories() {
		return ['directorist-widgets'];
	}

	public function get_keywords() {
		return [
			'registration', 'signup', 'user', 'reg',
		];
	}

	protected function register_controls(): void {
		$this->register_contents();
		$this->register_styles();
	}

	protected function register_contents(): void {
		$this->start_controls_section(
			'sec_general',
			[
				'label' => __( 'General', 'template-market' ),
			]
		);

		$this->add_control(
			'sec_heading',
			[
				'label'     => __( 'This widget works only in Registration page. It has no additional elementor settings.', 'template-market' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->end_controls_section();
	}

	protected function register_styles(): void {

		$this->register_container_style_controls(
			__( 'Form Container', 'template-market' ),
			'directorist_registration',
			'.directorist-author__form',
		);

		$this->register_text_controls( __( 'Label', 'template-market' ), 'registration_form_label', '.directorist-form-group label' );

		$this->register_text_controls( __( 'Placeholder', 'template-market' ), 'registration_form_placeholder', '.directorist-form-element::placeholder' );

		$this->register_form_fields_separator_controls( __( 'Fields Separator', 'template-market' ), 'registration_form_fields_separator', '.directorist-form-element' );

		$this->register_button_2_style_controls( __( 'Sign Up', 'template-market' ), 'registration_button', '.directorist-author__form__btn' );

		$this->register_text_controls( __( 'Already have an account?', 'template-market' ), 'registration_form_info', '.directorist-author__form__toggle-area' );

		$this->register_text_controls( __( 'Here', 'template-market' ), 'registration_form_here', '.directorist-author__form__toggle-area a' );
	}

	protected function render(): void {
		if ( is_user_logged_in() && Helper::is_edit() ) {
			$user_type = ! empty( $atts['user_type'] ) ? $atts['user_type'] : '';
			$user_type = ! empty( $_REQUEST['user_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['user_type'] ) ) : $user_type;

			$args = [
				'parent'               => 0,
				'container_fluid'      => is_directoria_active() ? 'container' : 'container-fluid',
				'username'             => get_directorist_option( 'reg_username', __( 'Username', 'template-market' ) ),
				'password'             => get_directorist_option( 'reg_password', __( 'Password', 'template-market' ) ),
				'display_password_reg' => get_directorist_option( 'display_password_reg', 1 ),
				'require_password'     => get_directorist_option( 'require_password_reg', 1 ),
				'email'                => get_directorist_option( 'reg_email', __( 'Email', 'template-market' ) ),
				'display_website'      => get_directorist_option( 'display_website_reg', 0 ),
				'website'              => get_directorist_option( 'reg_website', __( 'Website', 'template-market' ) ),
				'require_website'      => get_directorist_option( 'require_website_reg', 0 ),
				'display_fname'        => get_directorist_option( 'display_fname_reg', 0 ),
				'first_name'           => get_directorist_option( 'reg_fname', __( 'First Name', 'template-market' ) ),
				'require_fname'        => get_directorist_option( 'require_fname_reg', 0 ),
				'display_lname'        => get_directorist_option( 'display_lname_reg', 0 ),
				'last_name'            => get_directorist_option( 'reg_lname', __( 'Last Name', 'template-market' ) ),
				'require_lname'        => get_directorist_option( 'require_lname_reg', 0 ),
				'display_bio'          => get_directorist_option( 'display_bio_reg', 0 ),
				'bio'                  => get_directorist_option( 'reg_bio', __( 'About/bio', 'template-market' ) ),
				'require_bio'          => get_directorist_option( 'require_bio_reg', 0 ),
				'reg_signup'           => get_directorist_option( 'reg_signup', __( 'Sign Up', 'template-market' ) ),
				'display_login'        => get_directorist_option( 'display_login', 1 ),
				'login_text'           => get_directorist_option( 'login_text', __( 'Already have an account?', 'template-market' ) ),
				'login_url'            => ATBDP_Permalink::get_login_page_link(),
				'log_linkingmsg'       => get_directorist_option( 'log_linkingmsg', __( 'Login', 'template-market' ) ),
				'terms_label'          => get_directorist_option( 'regi_terms_label', __( 'I agree with all', 'template-market' ) ),
				'terms_label_link'     => get_directorist_option( 'regi_terms_label_link', __( 'terms & conditions', 'template-market' ) ),
				't_C_page_link'        => ATBDP_Permalink::get_terms_and_conditions_page_url(),
				'privacy_page_link'    => ATBDP_Permalink::get_privacy_policy_page_url(),
				'privacy_label'        => get_directorist_option( 'registration_privacy_label', __( 'I agree to the', 'template-market' ) ),
				'privacy_label_link'   => get_directorist_option( 'registration_privacy_label_link', __( 'Privacy & Policy', 'template-market' ) ),
				'user_type'            => $user_type,
				'author_checked'       => ( 'general' !== $user_type ) ? 'checked' : '',
				'general_checked'      => ( 'general' === $user_type ) ? 'checked' : '',
			];

			DirectoristHelper::get_template( 'account/registration', $args );
		} else {
			Helper::run_shortcode( 'directorist_custom_registration' );
		}
	}
}
