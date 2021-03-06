<?php

/**
 * Registers, validates and displays options on the Settings page. 
 */
class PluginOptions {

	private $default_field_args;

	/**
	 * Field arguments keyed by a field identifier.  These arguments determine
	 * how a field is displayed and validated.
	 *
	 * @var array
	 */
	protected $field_args_by_id;

	/**
	 * The name to use to save and retrieve the options.
	 *
	 * @var string
	 */
	protected $options_name;

	/**
	 * Reference to the render method.
	 *
	 * @var array
	 */
	protected $render_field_method;

	/**
	 * The uri of the plugin in the format of '/wp-content/plugins/<plugin directory>'.
	 * Accounts for situations where someone may install the plugin in a different
	 * directory from which it is named.
	 *
	 * @var string
	 */
	protected $plugin_uri;

	/**
	 * Unique string that identifies this plugin.
	 *
	 * @var string
	 */
	protected $uid;

	function __construct() {
		$this->default_field_args = array(
			'class'         => 'regular-text',
			'default_value' => '',
			'label'         => '',
			'name'          => '',
			'type'          => 'text',
			'options'       => '',
		);

		$this->plugin_uri = plugins_url() . substr(dirname(__FILE__), strrpos(dirname(__FILE__), DIRECTORY_SEPARATOR));

		add_action( 'admin_init', array( &$this, 'initialize' ) );
		add_action( 'admin_menu', array( &$this, 'addPage' ) );

		$this->render_field_method = array( $this, 'renderField' );
	}

	/**
	 * Makes the page visible under the Settings menu.  Override this method in your class implementation
	 * and use the add_options_page method.
	 */
	public function addPage() {

	}

	public function initialize() {
		$this->field_args_by_id = array();
	}

	public function setFieldArgsById( $id, array &$field_args ) {
		$this->field_args_by_id[$id] = $field_args;
	}

	/**
	 * Renders a field on the screen.
	 *
	 * @param array $args	An array of field arguments.
	 */
	public function renderField( $args = array() ) {
		$class         = '';
		$default_value = '';
		$id            = '';
		$input_name    = '';
		$name          = '';
		$type          = '';
		$value         = '';

		$arg_keys      = array( 'class', 'default_value', 'id', 'name', 'type' );

		foreach ( $arg_keys as  $arg_key ) {
			if ( isset( $args[$arg_key] ) ) {
				$$arg_key = $args[$arg_key];
			}
		}

		$options = get_option( $this->options_name );

		if ( isset( $options[$name] ) ) {
			$value = $options[$name];
		}

		if ( empty( $value ) ) {
			$value = $default_value;
		}

		
		if ( empty( $this->options_name) ) {
			$input_name = $name;
		}
		else {
			$input_name = $this->options_name . '[' . $name . ']';
		}

		switch ( $type ) {
			case 'text':
			case 'password':

				echo "<input id='$id' class='$class' name='$input_name' type='$type' value='$value' />";

				break;

			case 'checkbox':

				if ( ( isset( $args['options'] ) ) && ( is_array( $args['options'] ) ) ) {
					foreach ($args['options'] as $option) {
						$checked = '';

						if ( is_array( $value ) ) {
							if ( in_array($option['value'], $value ) ) {
								$checked = "checked='checked'";
							}
						}
						else if ( $option['value'] == $value ) {
							$checked = "checked='checked'";
						}

						echo "<input id='$id' class='$class' name='{$input_name}[]' type='$type' value='{$option['value']}' $checked /> {$option['label']}";
					}
				}

				break;

			case 'select':
			case 'select-one':

				echo "<select id='$id' class='$class' name='$input_name'>";

				if ( ( isset( $args['options'] ) ) && ( is_array( $args['options'] ) ) ) {
					foreach ($args['options'] as $option) {
						$selected = '';

						if ( is_array( $value ) ) {
							if ( in_array($option['value'], $value ) ) {
								$selected = "selected='selected'";
							}
						}
						else if ( $option['value'] == $value ) {
							$selected = "selected='selected'";
						}

						echo "<option value='{$option['value']}' $selected>{$option['label']}</option>";

					}
				}

				echo "</select>";
				break;

			default:

				break;

		}
	}

	/**
	 * Perform field input validation on the supported type of validation. The type of validation
	 * is driven by the field_args_by_id property.
	 *
	 * Supported elements are:
	 *
	 * <ul>
	 * 	<li>required - The field must have a value.</li>
	 *	<li>regex - The field must match the regular expression if populated.</li>
	 *	<li>filter - The field must satisfy the PHP filter_var function if populated.</li>
	 * </ul>
	 *
	 * @param string $input_name Form input name.
	 * @param string $input_value Form input value.
	 */
	protected function validateValue( $input_name, $input_value ) {
		if ( ( isset( $this->field_args_by_id[$input_name]['validate'] ) ) && ( is_array( $this->field_args_by_id[$input_name]['validate'] ) ) ) {
			foreach ( $this->field_args_by_id[$input_name]['validate'] as $validation_function => $validation_param) {
				switch ( $validation_function ) {
					case 'required':
						$is_valid = $this->validateRequired( $input_name, $input_value );
						break;

					case 'regex':
						$is_valid = $this->validateRegex( $input_name, $input_value );
						break;

					case 'filter':
						$is_valid = $this->validateFilter( $input_name, $input_value );
						break;

					default:
						break;	
				}

				if ( $is_valid === false ) {
					break;
				}
			}
		}
	}

	/**
	 * Ensures that a form field has a value.
	 *
	 * @param string $input_name Form field to validate.
	 * @param string $input_value Form value.
	 * @return bool True if the field has a value.  False if its value is missing.
	 */
	private function validateRequired( $input_name, $input_value ) {
		$is_valid = true;

		if ( empty( $input_value ) ) {
			$is_valid = false;		
			$label = $this->field_args_by_id[$input_name]['label'];

			add_settings_error( $input_name, $input_name . '_error', $label . ': A value is required.' );
		}

		return $is_valid;
	}

	/**
	 * Ensures that a form field meets a regular expression criteria if it
	 * contains a value.  If the validation fails, an error message is shown using the 'regex_error'
	 * value in the field_args_by_id property.
	 *
	 * @param string $input_name Form field to validate.
	 * @param string $input_value Form value.
	 * @return bool True if the field satisfies the regular expression.  False if it does not.
	 */
	private function validateRegex( $input_name, $input_value ) {
		$is_valid = true;

		if ( !empty( $input_value ) ) {
			$field_args = array();
			if ( isset( $this->field_args_by_id[$input_name] ) ) {		
				$field_args = $this->field_args_by_id[$input_name];
			}

			if ( isset( $field_args['validate']['regex'] ) ) {
				$match_count = preg_match( $field_args['validate']['regex'], $input_value );

				if ( ( $match_count === 0 ) || ( $match_count === false ) ) {
					$is_valid = false;		
					$label = $field_args['label'];
					$error_message = 'Enter in the correct format.';

					if ( isset( $field_args['validate']['regex_error'] ) ) {
						$error_message = $field_args['validate']['regex_error'];
					}

					add_settings_error( $input_name, $input_name . '_error', $label . ': ' . $error_message );
				}
			}
		}

		return $is_valid;
	}

	/**
	 * Ensures that a form field meets the requirements of filter_var function (requires PHP 5.2 or higher).
	 * The requirements of the filter_var function are determined by 'filter' value set in the field_args_by_id
	 * property.
	 *
	 * If the validation fails, an error message is shown using the 'filter_error'
	 * value in the field_args_by_id property.
	 *
	 * @param string $input_name Form field to validate.
	 * @param string $input_value Form value.
	 * @return bool True if the field satisfies the filter_var.  False if it does not.
	 */
	private function validateFilter( $input_name, $input_value ) {
		$is_valid = true;

		if ( !empty( $input_value ) ) {
			$field_args = array();
			if ( isset( $this->field_args_by_id[$input_name] ) ) {		
				$field_args = $this->field_args_by_id[$input_name];
			}

			if ( isset( $field_args['validate']['filter'] ) ) {
				if ( function_exists( 'filter_var' ) ) {
					$filter_result = filter_var( $input_value, $field_args['validate']['filter'] );

					if ( $filter_result === false ) {
						$is_valid = false;
						$label = $field_args['label'];

						$error_message = 'Enter in the correct format.';

						if ( isset( $field_args['validate']['filter_error'] ) ) {
							$error_message = $field_args['validate']['filter_error'];
						}

						add_settings_error( $input_name, $input_name . '_error', $label . ': ' . $error_message );
					}
				}
				else {
					add_settings_error( $input_name, $input_name . '_error', 'PHP version 5.2 or higher is required.' );
				}
			}
		}

		return $is_valid;
	}

}

?>
