<?php

/**
 * Main plugin class.
 *
 * @package EasyWhatsApp
 */

if (! defined('ABSPATH')) {
	exit;
}

final class Easy_WhatsApp_Plugin
{
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
	private const OPTION_LEADS_TABLE_VERSION = 'easy_whatsapp_leads_table_version';

	/**
	 * Nonce action for lead save AJAX.
	 */
	private const LEAD_NONCE_ACTION = 'easy_whatsapp_save_lead';

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
	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct()
	{
		add_action('plugins_loaded', array($this, 'load_textdomain'));
		add_action('init', array($this, 'maybe_create_leads_table'), 5);
		add_action('init', array($this, 'register_post_meta_fields'));
		add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
		add_action('save_post', array($this, 'save_meta_box'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_menu', array($this, 'register_admin_menu'));

		// Frontend hooks for floating button
		add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
		add_action('wp_footer', array($this, 'render_floating_button'));
		add_action('wp_ajax_easy_whatsapp_save_lead', array($this, 'ajax_save_lead'));
		add_action('wp_ajax_nopriv_easy_whatsapp_save_lead', array($this, 'ajax_save_lead'));
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate()
	{
		self::create_leads_table();
		update_option(self::OPTION_LEADS_TABLE_VERSION, EASY_WHATSAPP_VERSION);
	}

	/**
	 * Returns leads table name.
	 *
	 * @return string
	 */
	public static function get_leads_table_name()
	{
		global $wpdb;

		return $wpdb->prefix . 'easy_whatsapp_leads';
	}

	/**
	 * Creates leads table if it does not exist.
	 *
	 * @return void
	 */
	public static function create_leads_table()
	{
		global $wpdb;

		$table_name      = self::get_leads_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			whatsapp_number varchar(20) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			phone varchar(30) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			page_url text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta($sql);
	}

	/**
	 * Creates or upgrades leads table when needed.
	 *
	 * @return void
	 */
	public function maybe_create_leads_table()
	{
		$stored_version = get_option(self::OPTION_LEADS_TABLE_VERSION, '');

		if (EASY_WHATSAPP_VERSION === $stored_version) {
			return;
		}

		self::create_leads_table();
		update_option(self::OPTION_LEADS_TABLE_VERSION, EASY_WHATSAPP_VERSION);
	}

	/**
	 * Loads plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain('easy-whatsapp', false, dirname(plugin_basename(EASY_WHATSAPP_PLUGIN_FILE)) . '/languages');
	}

	/**
	 * Registers plugin admin menu and submenu pages.
	 *
	 * @return void
	 */
	public function register_admin_menu()
	{
		add_menu_page(
			__('Easy WhatsApp', 'easy-whatsapp'),
			__('Easy WhatsApp', 'easy-whatsapp'),
			'manage_options',
			'easy-whatsapp-dashboard',
			array($this, 'render_dashboard_page'),
			'dashicons-format-chat',
			58
		);

		add_submenu_page(
			'easy-whatsapp-dashboard',
			__('Dashboard', 'easy-whatsapp'),
			__('Dashboard', 'easy-whatsapp'),
			'manage_options',
			'easy-whatsapp-dashboard',
			array($this, 'render_dashboard_page')
		);

		add_submenu_page(
			'easy-whatsapp-dashboard',
			__('Settings', 'easy-whatsapp'),
			__('Settings', 'easy-whatsapp'),
			'manage_options',
			'easy-whatsapp-settings',
			array($this, 'render_settings_page')
		);
	}

	/**
	 * Registers plugin settings.
	 *
	 * @return void
	 */
	public function register_settings()
	{
		register_setting(
			'easy_whatsapp_settings',
			self::OPTION_POST_TYPES,
			array(
				'type'              => 'array',
				'default'           => $this->get_default_post_types(),
				'sanitize_callback' => array($this, 'sanitize_post_types_option'),
			)
		);

		add_settings_section(
			'easy_whatsapp_main_section',
			__('Post Type Settings', 'easy-whatsapp'),
			'__return_false',
			'easy-whatsapp-settings'
		);

		add_settings_field(
			'easy_whatsapp_post_types_field',
			__('Enabled post types', 'easy-whatsapp'),
			array($this, 'render_post_types_field'),
			'easy-whatsapp-settings',
			'easy_whatsapp_main_section'
		);
	}

	/**
	 * Renders dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page()
	{
		if (! current_user_can('manage_options')) {
			return;
		}

		global $wpdb;

		$table_name = self::get_leads_table_name();
		$per_page   = 20;
		$paged      = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
		$offset     = ($paged - 1) * $per_page;

		$allowed_orderby = array(
			'id'              => 'id',
			'created_at'      => 'created_at',
			'name'            => 'name',
			'phone'           => 'phone',
			'email'           => 'email',
			'whatsapp_number' => 'whatsapp_number',
		);

		$orderby = isset($_GET['orderby']) ? sanitize_key((string) $_GET['orderby']) : 'created_at';
		if (! isset($allowed_orderby[$orderby])) {
			$orderby = 'created_at';
		}

		$order = isset($_GET['order']) ? strtolower(sanitize_text_field(wp_unslash($_GET['order']))) : 'desc';
		if (! in_array($order, array('asc', 'desc'), true)) {
			$order = 'desc';
		}

		$search_term = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
		$date_from   = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
		$date_to     = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
		$has_email   = isset($_GET['has_email']) ? sanitize_key((string) $_GET['has_email']) : '';

		$where_clauses = array('1=1');
		$where_values  = array();

		if ('' !== $search_term) {
			$like = '%' . $wpdb->esc_like($search_term) . '%';
			$where_clauses[] = '(name LIKE %s OR phone LIKE %s OR email LIKE %s OR whatsapp_number LIKE %s OR page_url LIKE %s)';
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
		}

		if ('' !== $date_from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[]  = $date_from . ' 00:00:00';
		}

		if ('' !== $date_to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[]  = $date_to . ' 23:59:59';
		}

		if ('yes' === $has_email) {
			$where_clauses[] = "email <> ''";
		} elseif ('no' === $has_email) {
			$where_clauses[] = "email = ''";
		}

		$where_sql = implode(' AND ', $where_clauses);

		$count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
		if (! empty($where_values)) {
			$count_sql = $wpdb->prepare($count_sql, $where_values);
		}

		$total_items = (int) $wpdb->get_var($count_sql);

		$query_sql = "SELECT id, post_id, whatsapp_number, name, phone, email, page_url, created_at
			FROM {$table_name}
			WHERE {$where_sql}
			ORDER BY {$allowed_orderby[$orderby]} {$order}
			LIMIT %d OFFSET %d";

		$query_values   = $where_values;
		$query_values[] = $per_page;
		$query_values[] = $offset;

		$leads = $wpdb->get_results(
			$wpdb->prepare($query_sql, $query_values),
			ARRAY_A
		);

		$total_pages = max(1, (int) ceil($total_items / $per_page));

		$base_url = menu_page_url('easy-whatsapp-dashboard', false);
		$pagination_args = array(
			'orderby'   => $orderby,
			'order'     => $order,
			's'         => $search_term,
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'has_email' => $has_email,
		);

		$pagination_args = array_filter(
			$pagination_args,
			static function ($value) {
				return '' !== $value;
			}
		);

		$page_links = paginate_links(
			array(
				'base'      => add_query_arg('paged', '%#%', add_query_arg($pagination_args, $base_url)),
				'format'    => '',
				'prev_text' => __('&laquo;', 'easy-whatsapp'),
				'next_text' => __('&raquo;', 'easy-whatsapp'),
				'total'     => $total_pages,
				'current'   => $paged,
			)
		);

		$sortable_columns = array(
			'id'              => __('ID', 'easy-whatsapp'),
			'created_at'      => __('Date', 'easy-whatsapp'),
			'name'            => __('Name', 'easy-whatsapp'),
			'phone'           => __('Phone', 'easy-whatsapp'),
			'email'           => __('Email', 'easy-whatsapp'),
			'whatsapp_number' => __('WhatsApp Number', 'easy-whatsapp'),
		);
?>
		<div class="wrap">
			<h1><?php esc_html_e('Easy WhatsApp Dashboard', 'easy-whatsapp'); ?></h1>
			<p><?php esc_html_e('Leads submitted from the WhatsApp popup are listed below.', 'easy-whatsapp'); ?></p>

			<form method="get">
				<input type="hidden" name="page" value="easy-whatsapp-dashboard" />

				<div class="tablenav top">
					<div class="alignleft actions">
						<label for="easy-whatsapp-date-from" class="screen-reader-text"><?php esc_html_e('From date', 'easy-whatsapp'); ?></label>
						<input type="date" id="easy-whatsapp-date-from" name="date_from" value="<?php echo esc_attr($date_from); ?>" />

						<label for="easy-whatsapp-date-to" class="screen-reader-text"><?php esc_html_e('To date', 'easy-whatsapp'); ?></label>
						<input type="date" id="easy-whatsapp-date-to" name="date_to" value="<?php echo esc_attr($date_to); ?>" />

						<label for="easy-whatsapp-has-email" class="screen-reader-text"><?php esc_html_e('Email filter', 'easy-whatsapp'); ?></label>
						<select id="easy-whatsapp-has-email" name="has_email">
							<option value=""><?php esc_html_e('All emails', 'easy-whatsapp'); ?></option>
							<option value="yes" <?php selected('yes', $has_email); ?>><?php esc_html_e('With email', 'easy-whatsapp'); ?></option>
							<option value="no" <?php selected('no', $has_email); ?>><?php esc_html_e('Without email', 'easy-whatsapp'); ?></option>
						</select>

						<?php submit_button(__('Filter', 'easy-whatsapp'), 'secondary', 'filter_action', false); ?>
					</div>

					<p class="search-box">
						<label class="screen-reader-text" for="easy-whatsapp-search-input"><?php esc_html_e('Search leads', 'easy-whatsapp'); ?></label>
						<input type="search" id="easy-whatsapp-search-input" name="s" value="<?php echo esc_attr($search_term); ?>" />
						<?php submit_button(__('Search Leads', 'easy-whatsapp'), 'button', 'search_submit', false); ?>
					</p>

					<div class="tablenav-pages one-page">
						<span class="displaying-num">
							<?php echo esc_html(sprintf(_n('%d lead', '%d leads', $total_items, 'easy-whatsapp'), $total_items)); ?>
						</span>
						<?php if (! empty($page_links)) : ?>
							<span class="pagination-links"><?php echo wp_kses_post($page_links); ?></span>
						<?php endif; ?>
					</div>
				</div>

			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<?php foreach ($sortable_columns as $column_key => $column_label) : ?>
							<?php
							$next_order   = ('asc' === $order && $orderby === $column_key) ? 'desc' : 'asc';
							$sort_classes = array('manage-column', 'sortable');
							if ($orderby === $column_key) {
								$sort_classes = array('manage-column', 'sorted', $order);
							} else {
								$sort_classes[] = $next_order;
							}

							$sort_url = add_query_arg(
								array_merge(
									$pagination_args,
									array(
										'orderby' => $column_key,
										'order'   => $next_order,
										'paged'   => 1,
									)
								),
								$base_url
							);
							?>
							<th scope="col" class="<?php echo esc_attr(implode(' ', $sort_classes)); ?> column-<?php echo esc_attr($column_key); ?>">
								<a href="<?php echo esc_url($sort_url); ?>">
									<span><?php echo esc_html($column_label); ?></span>
									<span class="sorting-indicators">
										<span class="sorting-indicator asc" aria-hidden="true"></span>
										<span class="sorting-indicator desc" aria-hidden="true"></span>
									</span>
								</a>
							</th>
						<?php endforeach; ?>
						<th scope="col" class="manage-column column-post"><?php esc_html_e('Post', 'easy-whatsapp'); ?></th>
						<th scope="col" class="manage-column column-page-url"><?php esc_html_e('Page URL', 'easy-whatsapp'); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<?php foreach ($sortable_columns as $column_key => $column_label) : ?>
							<th scope="col" class="manage-column column-<?php echo esc_attr($column_key); ?>"><?php echo esc_html($column_label); ?></th>
						<?php endforeach; ?>
						<th scope="col" class="manage-column column-post"><?php esc_html_e('Post', 'easy-whatsapp'); ?></th>
						<th scope="col" class="manage-column column-page-url"><?php esc_html_e('Page URL', 'easy-whatsapp'); ?></th>
					</tr>
				</tfoot>
				<tbody>
					<?php if (empty($leads)) : ?>
						<tr class="no-items">
							<td class="colspanchange" colspan="8"><?php esc_html_e('No leads found yet.', 'easy-whatsapp'); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ($leads as $lead) : ?>
							<tr>
								<td><?php echo esc_html((string) absint($lead['id'])); ?></td>
								<td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $lead['created_at'])); ?></td>
								<td><?php echo esc_html($lead['name']); ?></td>
								<td><?php echo esc_html($lead['phone']); ?></td>
								<td><?php echo '' !== $lead['email'] ? esc_html($lead['email']) : esc_html__('--', 'easy-whatsapp'); ?></td>
								<td><?php echo esc_html($lead['whatsapp_number']); ?></td>
								<td>
									<?php
									$post_id = absint($lead['post_id']);
									if ($post_id > 0) {
										$post_title = get_the_title($post_id);
										$edit_link  = get_edit_post_link($post_id);

										if (! empty($edit_link)) {
											echo '<a href="' . esc_url($edit_link) . '">' . esc_html($post_title ? $post_title : sprintf(__('Post #%d', 'easy-whatsapp'), $post_id)) . '</a>';
										} else {
											echo esc_html($post_title ? $post_title : sprintf(__('Post #%d', 'easy-whatsapp'), $post_id));
										}
									} else {
										echo esc_html__('--', 'easy-whatsapp');
									}
									?>
								</td>
								<td>
									<?php if (! empty($lead['page_url'])) : ?>
										<a href="<?php echo esc_url($lead['page_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View Page', 'easy-whatsapp'); ?></a>
									<?php else : ?>
										<?php esc_html_e('--', 'easy-whatsapp'); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if (! empty($page_links)) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages"><?php echo wp_kses_post($page_links); ?></div>
				</div>
			<?php endif; ?>
			</form>
		</div>
	<?php
	}

	/**
	 * Renders settings page.
	 *
	 * @return void
	 */
	public function render_settings_page()
	{
		if (! current_user_can('manage_options')) {
			return;
		}
	?>
		<div class="wrap">
			<h1><?php esc_html_e('Easy WhatsApp', 'easy-whatsapp'); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields('easy_whatsapp_settings');
				do_settings_sections('easy-whatsapp-settings');
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
	public function render_post_types_field()
	{
		$selected_post_types  = $this->sanitize_post_types_option(get_option(self::OPTION_POST_TYPES, $this->get_default_post_types()));
		$available_post_types = $this->get_available_post_types();

		if (empty($available_post_types)) {
			echo '<p>' . esc_html__('No editable post types found.', 'easy-whatsapp') . '</p>';
			return;
		}

		printf(
			'<input type="hidden" name="%1$s[]" value="" />',
			esc_attr(self::OPTION_POST_TYPES)
		);

		foreach ($available_post_types as $slug => $label) {
			printf(
				'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s <span class="description">(%5$s)</span></label><br />',
				esc_attr(self::OPTION_POST_TYPES),
				esc_attr($slug),
				checked(in_array($slug, $selected_post_types, true), true, false),
				esc_html($label),
				esc_html($slug)
			);
		}

		echo '<p class="description">' . esc_html__('Select one or more post types where the WhatsApp field should appear.', 'easy-whatsapp') . '</p>';
	}

	/**
	 * Returns default post types.
	 *
	 * @return string[]
	 */
	private function get_default_post_types()
	{
		return array('post');
	}

	/**
	 * Returns available post types for settings UI.
	 *
	 * @return array<string, string>
	 */
	private function get_available_post_types()
	{
		$post_type_objects = get_post_types(
			array(
				'show_ui' => true,
			),
			'objects'
		);

		if (! is_array($post_type_objects)) {
			return array();
		}

		$post_types = array();

		foreach ($post_type_objects as $post_type_object) {
			if (! isset($post_type_object->name)) {
				continue;
			}

			$slug = sanitize_key((string) $post_type_object->name);
			if ('' === $slug) {
				continue;
			}

			$label = '';
			if (isset($post_type_object->labels, $post_type_object->labels->singular_name) && is_string($post_type_object->labels->singular_name)) {
				$label = $post_type_object->labels->singular_name;
			} elseif (isset($post_type_object->label) && is_string($post_type_object->label)) {
				$label = $post_type_object->label;
			}

			if ('' === $label) {
				$label = $slug;
			}

			$post_types[$slug] = $label;
		}

		if (! empty($post_types)) {
			asort($post_types, SORT_NATURAL | SORT_FLAG_CASE);
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
	public function sanitize_post_types_option($post_types)
	{
		$available_post_types = array_keys($this->get_available_post_types());

		if (! is_array($post_types)) {
			$post_types = $this->get_default_post_types();
		}

		$sanitized = array();

		foreach ($post_types as $post_type) {
			if (! is_scalar($post_type)) {
				continue;
			}

			$slug = sanitize_key((string) $post_type);
			if ('' === $slug) {
				continue;
			}

			if (! empty($available_post_types) && ! in_array($slug, $available_post_types, true)) {
				continue;
			}

			$sanitized[] = $slug;
		}

		$sanitized = array_values(array_unique($sanitized));

		if (! empty($sanitized)) {
			return $sanitized;
		}

		$fallback = array_values(array_intersect($this->get_default_post_types(), $available_post_types));

		if (! empty($fallback)) {
			return $fallback;
		}

		if (! empty($available_post_types)) {
			return array((string) reset($available_post_types));
		}

		return $this->get_default_post_types();
	}

	/**
	 * Returns the target post types.
	 *
	 * @return string[]
	 */
	public function get_post_types()
	{
		$post_types = $this->sanitize_post_types_option(get_option(self::OPTION_POST_TYPES, $this->get_default_post_types()));
		$post_types = apply_filters('easy_whatsapp_post_types', $post_types);

		if (! is_array($post_types)) {
			$post_types = $this->get_default_post_types();
		}

		$post_types = array_filter(
			array_map(
				'sanitize_key',
				$post_types
			),
			'post_type_exists'
		);

		if (empty($post_types)) {
			$post_types = array_filter(
				array_map(
					'sanitize_key',
					$this->get_default_post_types()
				),
				'post_type_exists'
			);
		}

		if (empty($post_types)) {
			$post_types = array_keys($this->get_available_post_types());
		}

		return array_values(array_unique($post_types));
	}

	/**
	 * Registers the post meta field.
	 *
	 * @return void
	 */
	public function register_post_meta_fields()
	{
		foreach ($this->get_post_types() as $post_type) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array($this, 'sanitize_whatsapp_number'),
					'auth_callback'     => array($this, 'can_edit_meta'),
					'description'       => __('WhatsApp number attached to this content.', 'easy-whatsapp'),
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
	public function can_edit_meta($allowed, $meta_key, $object_id, $user_id, $cap = '')
	{
		if ('edit_post_meta' === $cap || 'add_post_meta' === $cap || 'delete_post_meta' === $cap) {
			return user_can((int) $user_id, 'edit_post', (int) $object_id);
		}

		return true;
	}

	/**
	 * Registers meta box on selected post types.
	 *
	 * @return void
	 */
	public function register_meta_boxes()
	{
		foreach ($this->get_post_types() as $post_type) {
			add_meta_box(
				'easy-whatsapp-number',
				__('WhatsApp Number', 'easy-whatsapp'),
				array($this, 'render_meta_box'),
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
	public function render_meta_box($post)
	{
		$number = get_post_meta($post->ID, self::META_KEY, true);

		wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
	?>
		<p>
			<label for="<?php echo esc_attr(self::FIELD_NAME); ?>">
				<?php esc_html_e('WhatsApp number', 'easy-whatsapp'); ?>
			</label>
		</p>
		<input
			type="text"
			id="<?php echo esc_attr(self::FIELD_NAME); ?>"
			name="<?php echo esc_attr(self::FIELD_NAME); ?>"
			class="widefat"
			value="<?php echo esc_attr($number); ?>"
			placeholder="<?php echo esc_attr_x('+12025550123', 'WhatsApp example number', 'easy-whatsapp'); ?>" />
		<p class="description">
			<?php esc_html_e('Use international format with country code.', 'easy-whatsapp'); ?>
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
	public function save_meta_box($post_id)
	{
		if (! isset($_POST[self::NONCE_NAME])) {
			return;
		}

		$nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME]));
		if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}

		$post_type = get_post_type($post_id);
		if (! in_array($post_type, $this->get_post_types(), true)) {
			return;
		}

		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		$number = '';
		if (isset($_POST[self::FIELD_NAME])) {
			$number = $this->sanitize_whatsapp_number(wp_unslash($_POST[self::FIELD_NAME]));
		}

		if ('' === $number) {
			delete_post_meta($post_id, self::META_KEY);
			return;
		}

		update_post_meta($post_id, self::META_KEY, $number);
	}

	/**
	 * Sanitizes a WhatsApp number.
	 *
	 * @param mixed $value Value to sanitize.
	 *
	 * @return string
	 */
	public function sanitize_whatsapp_number($value)
	{
		if (! is_scalar($value)) {
			return '';
		}

		$normalized = trim((string) $value);
		if ('' === $normalized) {
			return '';
		}

		$normalized = preg_replace('/[^0-9+]/', '', $normalized);
		if (null === $normalized || '' === $normalized) {
			return '';
		}

		$plus_count = substr_count($normalized, '+');
		if ($plus_count > 1) {
			return '';
		}

		if (1 === $plus_count && '+' !== substr($normalized, 0, 1)) {
			return '';
		}

		$digits_only = str_replace('+', '', $normalized);
		if ('' === $digits_only) {
			return '';
		}

		$length = strlen($digits_only);
		if ($length < 6 || $length > 15) {
			return '';
		}

		return $normalized;
	}

	/**
	 * Enqueue frontend scripts and styles on supported post types.
	 *
	 * @return void
	 */
	public function enqueue_frontend_scripts()
	{
		if (is_admin()) {
			return;
		}

		if (! is_singular($this->get_post_types())) {
			return;
		}

		$post_id = get_queried_object_id();
		if (! $post_id) {
			return;
		}

		$number = easy_whatsapp_get_number($post_id);
		if (empty($number)) {
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

		wp_localize_script(
			'easy-whatsapp-floating-button',
			'easyWhatsappData',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce(self::LEAD_NONCE_ACTION),
				'action'  => 'easy_whatsapp_save_lead',
			)
		);
	}

	/**
	 * Saves lead data from modal form via AJAX.
	 *
	 * @return void
	 */
	public function ajax_save_lead()
	{
		check_ajax_referer(self::LEAD_NONCE_ACTION, 'nonce');

		$name = '';
		if (isset($_POST['name'])) {
			$name = sanitize_text_field(wp_unslash($_POST['name']));
		}

		$phone = '';
		if (isset($_POST['phone'])) {
			$phone = sanitize_text_field(wp_unslash($_POST['phone']));
		}

		$email = '';
		if (isset($_POST['email'])) {
			$email = sanitize_email(wp_unslash($_POST['email']));
		}

		$post_id = 0;
		if (isset($_POST['post_id'])) {
			$post_id = absint($_POST['post_id']);
		}

		$whatsapp_number = '';
		if (isset($_POST['whatsapp_number'])) {
			$whatsapp_number = $this->sanitize_whatsapp_number(wp_unslash($_POST['whatsapp_number']));
		}

		$page_url = '';
		if (isset($_POST['page_url'])) {
			$page_url = esc_url_raw(wp_unslash($_POST['page_url']));
		}

		if ('' === $name || '' === $phone || '' === $whatsapp_number) {
			wp_send_json_error(array('message' => __('Missing required fields.', 'easy-whatsapp')), 400);
		}

		if ('' !== $email && ! is_email($email)) {
			wp_send_json_error(array('message' => __('Invalid email address.', 'easy-whatsapp')), 400);
		}

		global $wpdb;

		$inserted = $wpdb->insert(
			self::get_leads_table_name(),
			array(
				'post_id'          => $post_id,
				'whatsapp_number'  => $whatsapp_number,
				'name'             => $name,
				'phone'            => $phone,
				'email'            => $email,
				'page_url'         => $page_url,
				'created_at'       => current_time('mysql'),
			),
			array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
		);

		if (false === $inserted) {
			wp_send_json_error(array('message' => __('Failed to store lead.', 'easy-whatsapp')), 500);
		}

		wp_send_json_success(array('message' => __('Lead saved successfully.', 'easy-whatsapp')));
	}

	/**
	 * Render floating button in footer.
	 *
	 * @return void
	 */
	public function render_floating_button()
	{
		if (is_admin()) {
			return;
		}

		if (! is_singular($this->get_post_types())) {
			return;
		}

		$post_id = get_queried_object_id();
		if (! $post_id) {
			return;
		}

		$number = easy_whatsapp_get_number($post_id);
		if (empty($number)) {
			return;
		}

		// Modern WhatsApp SVG Icon
		$svg_icon = '
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
			<!--!Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2023 Fonticons, Inc.-->
			<path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157.1zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/>
		</svg>';

		printf(
			'<a href="#" class="easy-whatsapp-floating-button animate" data-number="%1$s" data-post-id="%2$d" title="%3$s" aria-haspopup="dialog" aria-controls="easy-whatsapp-modal">%4$s</a>',
			esc_attr($number),
			(int) $post_id,
			esc_attr__('Message us on WhatsApp', 'easy-whatsapp'),
			$svg_icon
		);

		printf(
			'<div id="easy-whatsapp-modal" class="easy-whatsapp-modal" hidden>' .
				'<div class="easy-whatsapp-modal-backdrop" data-easy-whatsapp-close="1"></div>' .
				'<div class="easy-whatsapp-modal-content" role="dialog" aria-modal="true" aria-labelledby="easy-whatsapp-modal-title">' .
				'<div class="easy-whatsapp-loading" hidden aria-live="polite" aria-busy="true">' .
				'<span class="easy-whatsapp-loading-spinner" aria-hidden="true"></span>' .
				'<span class="easy-whatsapp-loading-text">%7$s</span>' .
				'</div>' .
				'<button type="button" class="easy-whatsapp-modal-close" data-easy-whatsapp-close="1" aria-label="%1$s">&times;</button>' .
				'<h2 id="easy-whatsapp-modal-title">%2$s</h2>' .
				'<form class="easy-whatsapp-lead-form" novalidate>' .
				'<label for="easy-whatsapp-name">%3$s</label>' .
				'<input type="text" id="easy-whatsapp-name" name="name" required />' .
				'<label for="easy-whatsapp-phone">%4$s</label>' .
				'<input type="tel" id="easy-whatsapp-phone" name="phone" required />' .
				'<label for="easy-whatsapp-email">%5$s</label>' .
				'<input type="email" id="easy-whatsapp-email" name="email" />' .
				'<p class="easy-whatsapp-form-error" aria-live="polite"></p>' .
				'<button type="submit" class="easy-whatsapp-submit">%6$s</button>' .
				'</form>' .
				'</div>' .
				'</div>',
			esc_attr__('Close popup', 'easy-whatsapp'),
			esc_html__('Contact on WhatsApp', 'easy-whatsapp'),
			wp_kses_post(sprintf('%1$s <span class="easy-whatsapp-required" aria-hidden="true">*</span>', esc_html__('Name', 'easy-whatsapp'))),
			wp_kses_post(sprintf('%1$s <span class="easy-whatsapp-required" aria-hidden="true">*</span>', esc_html__('Phone', 'easy-whatsapp'))),
			esc_html__('Email (optional)', 'easy-whatsapp'),
			esc_html__('Continue to WhatsApp', 'easy-whatsapp'),
			esc_html__('Saving your details...', 'easy-whatsapp')
		);
	}
}
