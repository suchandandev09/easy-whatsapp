<?php
/**
 * Main plugin class.
 *
 * @package EasyWhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Easy_WhatsApp_Plugin {
	/**
	 * Meta key for WhatsApp number.
	 */
	public const META_KEY = '_easy_whatsapp_number';

	/**
	 * Input field name.
	 */
	private const FIELD_NAME = 'easy_whatsapp_number';

	/**
	 * Nonce action.
	 */
	private const NONCE_ACTION = 'easy_whatsapp_save_number';

	/**
	 * Nonce field name.
	 */
	private const NONCE_NAME = 'easy_whatsapp_nonce';

	/**
	 * Option name for selected post types.
	 */
	private const OPTION_POST_TYPES = 'easy_whatsapp_post_types';

	/**
	 * Plugin singleton instance.
	 *
	 * @var Easy_WhatsApp_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns singleton instance.
	 *
	 * @return Easy_WhatsApp_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_post_meta_fields' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		
		// Frontend hooks for floating button
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		add_action( 'wp_footer', array( $this, 'render_floating_button' ) );
	}

	/**
	 * Loads plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'easy-whatsapp', false, dirname( plugin_basename( EASY_WHATSAPP_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Registers the admin settings page.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_options_page(
			__( 'Easy WhatsApp', 'easy-whatsapp' ),
			__( 'Easy WhatsApp', 'easy-whatsapp' ),
			'manage_options',
			'easy-whatsapp',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'easy_whatsapp_settings',
			self::OPTION_POST_TYPES,
			array(
				'type'              => 'array',
				'default'           => $this->get_default_post_types(),
				'sanitize_callback' => array( $this, 'sanitize_post_types_option' ),
			)
		);

		add_settings_section(
			'easy_whatsapp_main_section',
			__( 'Post Type Settings', 'easy-whatsapp' ),
			'__return_false',
			'easy-whatsapp'
		);

		add_settings_field(
			'easy_whatsapp_post_types_field',
			__( 'Enabled post types', 'easy-whatsapp' ),
			array( $this, 'render_post_types_field' ),
			'easy-whatsapp',
			'easy_whatsapp_main_section'
		);
	}

	/**
	 * Renders settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Easy WhatsApp', 'easy-whatsapp' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'easy_whatsapp_settings' );
				do_settings_sections( 'easy-whatsapp' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders post types field.
	 *
	 * @return void
	 */
	public function render_post_types_field() {
		$selected_post_types  = $this->sanitize_post_types_option( get_option( self::OPTION_POST_TYPES, $this->get_default_post_types() ) );
		$available_post_types = $this->get_available_post_types();

		if ( empty( $available_post_types ) ) {
			echo '<p>' . esc_html__( 'No editable post types found.', 'easy-whatsapp' ) . '</p>';
			return;
		}

		printf(
			'<input type="hidden" name="%1$s[]" value="" />',
			esc_attr( self::OPTION_POST_TYPES )
		);

		foreach ( $available_post_types as $slug => $label ) {
			printf(
				'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s <span class="description">(%5$s)</span></label><br />',
				esc_attr( self::OPTION_POST_TYPES ),
				esc_attr( $slug ),
				checked( in_array( $slug, $selected_post_types, true ), true, false ),
				esc_html( $label ),
				esc_html( $slug )
			);
		}

		echo '<p class="description">' . esc_html__( 'Select one or more post types where the WhatsApp field should appear.', 'easy-whatsapp' ) . '</p>';
	}

	/**
	 * Returns default post types.
	 *
	 * @return string[]
	 */
	private function get_default_post_types() {
		return array( 'post' );
	}

	/**
	 * Returns available post types for settings UI.
	 *
	 * @return array<string, string>
	 */
	private function get_available_post_types() {
		$post_type_objects = get_post_types(
			array(
				'show_ui' => true,
			),
			'objects'
		);

		if ( ! is_array( $post_type_objects ) ) {
			return array();
		}

		$post_types = array();

		foreach ( $post_type_objects as $post_type_object ) {
			if ( ! isset( $post_type_object->name ) ) {
				continue;
			}

			$slug = sanitize_key( (string) $post_type_object->name );
			if ( '' === $slug ) {
				continue;
			}

			$label = '';
			if ( isset( $post_type_object->labels, $post_type_object->labels->singular_name ) && is_string( $post_type_object->labels->singular_name ) ) {
				$label = $post_type_object->labels->singular_name;
			} elseif ( isset( $post_type_object->label ) && is_string( $post_type_object->label ) ) {
				$label = $post_type_object->label;
			}

			if ( '' === $label ) {
				$label = $slug;
			}

			$post_types[ $slug ] = $label;
		}

		if ( ! empty( $post_types ) ) {
			asort( $post_types, SORT_NATURAL | SORT_FLAG_CASE );
		}

		return $post_types;
	}

	/**
	 * Sanitizes selected post types from settings.
	 *
	 * @param mixed $post_types Raw option value.
	 *
	 * @return string[]
	 */
	public function sanitize_post_types_option( $post_types ) {
		$available_post_types = array_keys( $this->get_available_post_types() );

		if ( ! is_array( $post_types ) ) {
			$post_types = $this->get_default_post_types();
		}

		$sanitized = array();

		foreach ( $post_types as $post_type ) {
			if ( ! is_scalar( $post_type ) ) {
				continue;
			}

			$slug = sanitize_key( (string) $post_type );
			if ( '' === $slug ) {
				continue;
			}

			if ( ! empty( $available_post_types ) && ! in_array( $slug, $available_post_types, true ) ) {
				continue;
			}

			$sanitized[] = $slug;
		}

		$sanitized = array_values( array_unique( $sanitized ) );

		if ( ! empty( $sanitized ) ) {
			return $sanitized;
		}

		$fallback = array_values( array_intersect( $this->get_default_post_types(), $available_post_types ) );

		if ( ! empty( $fallback ) ) {
			return $fallback;
		}

		if ( ! empty( $available_post_types ) ) {
			return array( (string) reset( $available_post_types ) );
		}

		return $this->get_default_post_types();
	}

	/**
	 * Returns the target post types.
	 *
	 * @return string[]
	 */
	public function get_post_types() {
		$post_types = $this->sanitize_post_types_option( get_option( self::OPTION_POST_TYPES, $this->get_default_post_types() ) );
		$post_types = apply_filters( 'easy_whatsapp_post_types', $post_types );

		if ( ! is_array( $post_types ) ) {
			$post_types = $this->get_default_post_types();
		}

		$post_types = array_filter(
			array_map(
				'sanitize_key',
				$post_types
			),
			'post_type_exists'
		);

		if ( empty( $post_types ) ) {
			$post_types = array_filter(
				array_map(
					'sanitize_key',
					$this->get_default_post_types()
				),
				'post_type_exists'
			);
		}

		if ( empty( $post_types ) ) {
			$post_types = array_keys( $this->get_available_post_types() );
		}

		return array_values( array_unique( $post_types ) );
	}

	/**
	 * Registers the post meta field.
	 *
	 * @return void
	 */
	public function register_post_meta_fields() {
		foreach ( $this->get_post_types() as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array( $this, 'sanitize_whatsapp_number' ),
					'auth_callback'     => array( $this, 'can_edit_meta' ),
					'description'       => __( 'WhatsApp number attached to this content.', 'easy-whatsapp' ),
				)
			);
		}
	}

	/**
	 * Auth callback for post meta editing.
	 *
	 * @param bool   $allowed   Current status.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Post ID.
	 * @param int    $user_id   User ID.
	 * @param string $cap       Capability name.
	 * @param array  $caps      User capabilities.
	 *
	 * @return bool
	 */
	public function can_edit_meta( $allowed, $meta_key, $object_id, $user_id, $cap = '' ) {
		if ( 'edit_post_meta' === $cap || 'add_post_meta' === $cap || 'delete_post_meta' === $cap ) {
			return user_can( (int) $user_id, 'edit_post', (int) $object_id );
		}

		return true;
	}

	/**
	 * Registers meta box on selected post types.
	 *
	 * @return void
	 */
	public function register_meta_boxes() {
		foreach ( $this->get_post_types() as $post_type ) {
			add_meta_box(
				'easy-whatsapp-number',
				__( 'WhatsApp Number', 'easy-whatsapp' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Renders meta box content.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$number = get_post_meta( $post->ID, self::META_KEY, true );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<p>
			<label for="<?php echo esc_attr( self::FIELD_NAME ); ?>">
				<?php esc_html_e( 'WhatsApp number', 'easy-whatsapp' ); ?>
			</label>
		</p>
		<input
			type="text"
			id="<?php echo esc_attr( self::FIELD_NAME ); ?>"
			name="<?php echo esc_attr( self::FIELD_NAME ); ?>"
			class="widefat"
			value="<?php echo esc_attr( $number ); ?>"
			placeholder="<?php echo esc_attr_x( '+12025550123', 'WhatsApp example number', 'easy-whatsapp' ); ?>"
		/>
		<p class="description">
			<?php esc_html_e( 'Use international format with country code.', 'easy-whatsapp' ); ?>
		</p>
		<?php
	}

	/**
	 * Saves meta box value.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function save_meta_box( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! in_array( $post_type, $this->get_post_types(), true ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$number = '';
		if ( isset( $_POST[ self::FIELD_NAME ] ) ) {
			$number = $this->sanitize_whatsapp_number( wp_unslash( $_POST[ self::FIELD_NAME ] ) );
		}

		if ( '' === $number ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		update_post_meta( $post_id, self::META_KEY, $number );
	}

	/**
	 * Sanitizes a WhatsApp number.
	 *
	 * @param mixed $value Value to sanitize.
	 *
	 * @return string
	 */
	public function sanitize_whatsapp_number( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$normalized = trim( (string) $value );
		if ( '' === $normalized ) {
			return '';
		}

		$normalized = preg_replace( '/[^0-9+]/', '', $normalized );
		if ( null === $normalized || '' === $normalized ) {
			return '';
		}

		$plus_count = substr_count( $normalized, '+' );
		if ( $plus_count > 1 ) {
			return '';
		}

		if ( 1 === $plus_count && '+' !== substr( $normalized, 0, 1 ) ) {
			return '';
		}

		$digits_only = str_replace( '+', '', $normalized );
		if ( '' === $digits_only ) {
			return '';
		}

		$length = strlen( $digits_only );
		if ( $length < 6 || $length > 15 ) {
			return '';
		}

		return $normalized;
	}

	/**
	 * Enqueue frontend scripts and styles on supported post types.
	 *
	 * @return void
	 */
	public function enqueue_frontend_scripts() {
		if ( is_admin() ) {
			return;
		}

		if ( ! is_singular( $this->get_post_types() ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$number = easy_whatsapp_get_number( $post_id );
		if ( empty( $number ) ) {
			return;
		}

		wp_enqueue_style(
			'easy-whatsapp-floating-button',
			EASY_WHATSAPP_PLUGIN_URL . 'assets/css/floating-button.css',
			array(),
			EASY_WHATSAPP_VERSION
		);

		wp_enqueue_script(
			'easy-whatsapp-floating-button',
			EASY_WHATSAPP_PLUGIN_URL . 'assets/js/floating-button.js',
			array(),
			EASY_WHATSAPP_VERSION,
			true
		);
	}

	/**
	 * Render floating button in footer.
	 *
	 * @return void
	 */
	public function render_floating_button() {
		if ( is_admin() ) {
			return;
		}

		if ( ! is_singular( $this->get_post_types() ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$number = easy_whatsapp_get_number( $post_id );
		if ( empty( $number ) ) {
			return;
		}

		// Modern WhatsApp SVG Icon
		$svg_icon = '
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
			<!--!Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2023 Fonticons, Inc.-->
			<path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157.1zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/>
		</svg>';

		printf(
			'<a href="#" class="easy-whatsapp-floating-button animate" data-number="%1$s" title="%2$s">%3$s</a>',
			esc_attr( $number ),
			esc_attr__( 'Message us on WhatsApp', 'easy-whatsapp' ),
			$svg_icon
		);
	}
}
