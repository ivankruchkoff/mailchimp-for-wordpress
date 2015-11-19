<?php

/**
* This class takes care of all form related functionality
 *
 * @access private
 * @ignore
*/
class MC4WP_Form_Asset_Manager {

	/**
	 * @var MC4WP_Form_Output_Manager
	 */
	protected $output_manager;

	/**
	 * @var bool
	 */
	protected $scripts_loaded = false;

	/**
	 * Constructor
	 *
	 * @param MC4WP_Form_Output_Manager $output_manager
	 */
	public function __construct( MC4WP_Form_Output_Manager $output_manager ) {
		$this->output_manager = $output_manager;
	}

	/**
	 * Init all form related functionality
	 */
	public function initialize() {
		$this->add_hooks();
		$this->register_scripts();
	}

	/**
	 * Add hooks
	 */
	public function add_hooks() {
		// load checkbox css if necessary
		add_action( 'wp_enqueue_scripts', array( $this, 'load_stylesheets' ) );
		add_action( 'mc4wp_output_form', array( $this, 'load_scripts' ) );
		add_action( 'wp_head', array( $this, 'print_dummy_javascript' ) );
		add_action( 'wp_footer', array( $this, 'print_javascript' ), 999 );
	}

	/**
	 * Register the various JS files used by the plugin
	 */
	public function register_scripts() {
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		// register client-side API script
		wp_register_script( 'mc4wp-forms-api', MC4WP_PLUGIN_URL . 'assets/js/forms-api'. $suffix .'.js', array(), MC4WP_VERSION, true );

		// register placeholder script, which will later be enqueued for IE only
		wp_register_script( 'mc4wp-placeholders', MC4WP_PLUGIN_URL . 'assets/js/third-party/placeholders.min.js', array(), MC4WP_VERSION, true );

		/**
		 * Runs right after JavaScript assets for forms have been registered.
		 *
		 * @since 3.0
		 *
		 * @param string $suffix
		 * @ignore
		 */
		do_action( 'mc4wp_register_form_scripts', $suffix );
	}

	/**
	 * Load the various stylesheets
	 */
	public function load_stylesheets( ) {

		$stylesheets = (array) get_option( 'mc4wp_form_stylesheets', array() );

		/**
		 * Filters the stylesheets to be loaded
		 *
		 * Return an empty array if you want to disable the loading of all stylesheets.
		 *
		 * @since 3.0
		 * @param array $stylesheets
		 */
		$stylesheets = (array) apply_filters( 'mc4wp_form_stylesheets', $stylesheets );

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		foreach( $stylesheets as $stylesheet ) {
			$handle = 'mailchimp-for-wp-form-' . $stylesheet;
			$src = 'assets/css/' . $stylesheet . $suffix . '.css';

			// check if it exists, a 404 in WordPress is more expensive than simple filesystem check
			if( file_exists( MC4WP_PLUGIN_DIR . $src ) ) {
				wp_enqueue_style( $handle, MC4WP_PLUGIN_URL . $src, array(), MC4WP_VERSION );
			}
		}

		return true;
	}

	/**
	 * Get configuration object for client-side use.
	 *
	 * @return array
	 */
	public function get_javascript_config() {

		$submitted_form = mc4wp_get_submitted_form();

		if( ! $submitted_form ) {
			return array();
		}

		$config = array(
			'submitted_form' => array(
				'id' => $submitted_form->ID,
				'data' => $submitted_form->data,
				'action' => $submitted_form->config['action']
			)
		);

		if( $submitted_form->has_errors() ) {
			$config['submitted_form']['errors'] = $submitted_form->errors;
		}

		$auto_scroll = 'default';

		/**
		 * Filters the `auto_scroll` setting for when a form is submitted.
		 *
		 * Accepts the following  values:
		 *
		 * - false
		 * - "default"
		 * - "animated"
		 *
		 * @param boolean|string $auto_scroll
		 * @since 3.0
		 */
		$config['auto_scroll'] = apply_filters( 'mc4wp_form_auto_scroll', $auto_scrol );

		return $config;
	}

	/**
	 * Load JavaScript files
	 * @return bool
	 */
	public function load_scripts() {

		if( $this->scripts_loaded ) {
			return false;
		}

		// load API script
		wp_localize_script( 'mc4wp-forms-api', 'mc4wp_forms_config', $this->get_javascript_config() );
		wp_enqueue_script( 'mc4wp-forms-api' );

		// load placeholder polyfill if browser is Internet Explorer
		if( ! empty( $GLOBALS['is_IE'] ) ) {
			wp_enqueue_script( 'mc4wp-placeholders' );
		}

		$this->scripts_loaded = true;
		return true;
	}

	/**
	 * Prints dummy JavaScript which allows people to call `mc4wp.forms.on()` before the JS is loaded.
	 */
	public function print_dummy_javascript() {
		?>
		<script type="text/javascript">
			/* <![CDATA[ */
			window.mc4wp = {
				listeners: [],
				forms: {
					on: function(event,callback) {
						window.mc4wp.listeners.push({
							event: event,
							callback: callback
						});
					}
				}
			};
			/* ]]> */
		</script>
		<?php
	}

	/**
	* Returns the MailChimp for WP form mark-up
	*
	* @return string
	*/
	public function print_javascript() {

		// don't print any scripts if this page has no forms
		if( empty( $this->output_manager->printed_forms ) ) {
			return false;
		}

		// make sure scripts are loaded
		$this->load_scripts();

		// print inline scripts depending on printed fields
		echo '<script type="text/javascript">';
		echo '(function() {';

		// include general form enhancements
		include  dirname( __FILE__ ) . '/views/js/general-form-enhancements.js';

		// include url fix
		if( in_array( 'url', $this->output_manager->printed_field_types ) ) {
			include dirname( __FILE__ ) . '/views/js/url-fields.js';
		}

		// include date polyfill?
		if( in_array( 'date', $this->output_manager->printed_field_types ) ) {
			include dirname( __FILE__ ) . '/views/js/date-fields.js';
		}

		echo '})();';
		echo '</script>';

		/**
		 * Runs right after inline JavaScript is printed, just before the closing </body> tag.
		 *
		 * This function will only run if the current page contains at least one form.
		 *
		 * @ignore
		 */
		do_action( 'mc4wp_print_forms_javascript' );
	}


}
