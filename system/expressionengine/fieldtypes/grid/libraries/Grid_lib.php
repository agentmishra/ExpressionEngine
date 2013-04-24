<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2013, EllisLab, Inc.
 * @license		http://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 2.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine Grid Field Library 
 *
 * @package		ExpressionEngine
 * @subpackage	Libraries
 * @category	Modules
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */

class Grid_lib {

	protected $_fieldtypes = array();
	protected $_validated = array();

	/**
	 * Handles EE_Fieldtype's display_field for displaying the Grid field
	 *
	 * @param	int		Field ID of field to delete
	 * @return	void
	 */
	public function display_field($entry_id, $data, $settings)
	{
		ee()->load->model('grid_model');

		// Get columns just for this field
		$vars['columns'] = ee()->grid_model->get_columns_for_field($settings['field_id']);

		// If $data is an array, we're likely coming back to the form on a
		// validation error
		if (is_array($data))
		{
			$rows = $this->_validated[$settings['field_id']]['value'];
		}
		// Otherwise, we're editing or creating a new entry
		else
		{
			$rows = ee()->grid_model->get_entry_rows($entry_id, $settings['field_id']);
		}

		$vars['rows'] = array();

		// Loop through row data and construct an array of publish field HTML
		// for the supplied field data
		foreach ($rows as $row_id => $row)
		{
			if ( ! is_numeric($row_id))
			{
				$row['row_id'] = $row_id;
			}
			
			foreach ($vars['columns'] as $column)
			{
				$vars['rows'][$row['row_id']]['col_id_'.$column['col_id']] = $this->_publish_field_cell(
					$settings['field_name'],
					$column,
					$row
				);

				if (isset($row['col_id_'.$column['col_id'].'_error']))
				{
					$vars['rows'][$row['row_id']]['col_id_'.$column['col_id'].'_error'] = $row['col_id_'.$column['col_id'].'_error'];
				}
			}
		}

		// Create a blank row for cloning to enter more data
		foreach ($vars['columns'] as $column)
		{
			$vars['blank_row']['col_id_'.$column['col_id']] = $this->_publish_field_cell(
				$settings['field_name'],
				$column
			);
		}

		$vars['field_id'] = $settings['field_name'];

		return ee()->load->view('publish', $vars, TRUE);
	}

	// ------------------------------------------------------------------------
	
	/**
	 * Returns publish field HTML for a given cell
	 *
	 * @param	string	Field name for input field namespacing
	 * @param	array	Column data
	 * @param	array	Data for current row
	 * @return	string	HTML for specified cell's publish field
	 */
	protected function _publish_field_cell($field_name, $column, $row_data = NULL)
	{
		$ft_api = ee()->api_channel_fields;

		// Instantiate fieldtype
		$fieldtype = $ft_api->setup_handler($column['col_type'], TRUE);

		// Assign settings to fieldtype manually so they're available like
		// normal field settings
		$fieldtype->field_id = $column['col_id'];
		$fieldtype->field_name = 'col_id_'.$column['col_id'];
		$fieldtype->settings = $column['col_settings'];

		// Developers can optionally implement grid_display_field, otherwise
		// we will try to use display_field
		$method = $ft_api->check_method_exists('grid_display_field')
			? 'grid_display_field' : 'display_publish_field';

		// Call the fieldtype's field display method and capture the output
		$display_field = $ft_api->apply($method, array($row_data['col_id_'.$column['col_id']]));

		// How we'll namespace new and existing rows
		$row_id = ( ! isset($row_data['row_id'])) ? 'new_row_0' : 'row_id_'.$row_data['row_id'];

		// Return the publish field HTML with namespaced form field names
		return preg_replace(
			'/(<[input|select|textarea][^>]*)name=["\']([^"]*)["\']/',
			'$1name="'.$field_name.'[rows]['.$row_id.'][$2]"',
			$display_field
		);
	}

	// ------------------------------------------------------------------------

	public function validate($data, $field_id)
	{
		// Empty field
		if ( ! isset($data['rows']))
		{
			return TRUE;
		}

		if (isset($this->_validated[$field_id]))
		{
			return $this->_validated[$field_id];
		}

		$ft_api = ee()->api_channel_fields;

		ee()->load->model('grid_model');

		$columns = ee()->grid_model->get_columns_for_field($field_id);

		$final_values = array();
		$errors = FALSE;

		foreach ($data['rows'] as $row_id => $row)
		{
			foreach ($columns as $column)
			{
				$col_id = 'col_id_'.$column['col_id'];

				if ( ! isset($row[$col_id]))
				{
					$row[$col_id] = NULL;
				}

				foreach ($row as $key => $value)
				{
					$_POST[$key] = $value;
				}

				// Instantiate fieldtype
				$fieldtype = $ft_api->setup_handler($column['col_type'], TRUE);

				// Assign settings to fieldtype manually so they're available like
				// normal field settings
				$fieldtype->field_id = $column['col_id'];
				$fieldtype->field_name = 'col_id_'.$column['col_id'];
				$fieldtype->settings = $column['col_settings'];

				// Developers can optionally implement grid_validate, otherwise we
				// will try to use validate
				$method = $ft_api->check_method_exists('grid_validate')
					? 'grid_validate' : 'validate';

				// Call the fieldtype's validate method and capture the output
				$validate = $ft_api->apply($method, array($row[$col_id]));

				$error = $validate;
				$value = $row[$col_id];

				if (is_array($validate))
				{
					extract($validate, EXTR_OVERWRITE);
				}

				$final_values[$row_id][$col_id] = $value;

				if (is_string($error) && ! empty($error))
				{
					$final_values[$row_id][$col_id] = $row[$col_id];
					$final_values[$row_id][$col_id.'_error'] = $error;
					$errors = lang('grid_validation_error');
				}

				foreach ($row as $key => $value)
				{
					unset($_POST[$key]);
				}
			}
		}

		$this->_validated[$field_id] = array('value' => $final_values, 'error' => $errors);

		return $this->_validated[$field_id];
	}

	// ------------------------------------------------------------------------

	public function save($data, $field_id)
	{
		$validated = $this->validate($data, $field_id);

		if ($validated['error'] === FALSE)
		{
			// Save
		}

		return TRUE;
	}

	// ------------------------------------------------------------------------

	/**
	 * Gets a list of installed fieldtypes and filters them for ones enabled
	 * for Grid
	 *
	 * @return	array	Array of Grid-enabled fieldtypes
	 */
	public function get_grid_fieldtypes()
	{
		if ( ! empty($this->_fieldtypes))
		{
			return $this->_fieldtypes;
		}

		// Shorten some line lengths
		$ft_api = ee()->api_channel_fields;

		$this->_fieldtypes = $ft_api->fetch_installed_fieldtypes();

		foreach ($this->_fieldtypes as $field_name => $data)
		{
			$ft_api->setup_handler($field_name);

			// We'll check the existence of certain methods to determine whether
			// or not this fieldtype is ready for Grid
			if ( ! $ft_api->check_method_exists('grid_display_settings'))
			{
				unset($this->_fieldtypes[$field_name]);
			}
		}

		ksort($this->_fieldtypes);

		return $this->_fieldtypes;
	}

	// ------------------------------------------------------------------------
	
	/**
	 * Given POSTed column settings, adds new columns to the database and
	 * figures out if any columns need deleting
	 *
	 * @param	array	POSTed column settings from field settings page
	 * @return	void
	 */
	public function apply_settings($settings)
	{
		ee()->load->model('grid_model');
		$new_field = ee()->grid_model->create_field($settings['field_id']);

		// We'll use the order of the posted fields to determine the column order
		$count = 0;

		// Keep track of column IDs that exist so we can compare it against
		// other columns in the DB to see which we should delete
		$col_ids = array();

		// Go through ALL posted columns for this field
		foreach ($settings['grid']['cols'] as $col_field => $column)
		{
			// Handle checkbox defaults
			$column['required'] = isset($column['required']) ? 'y' : 'n';
			$column['searchable'] = isset($column['searchable']) ? 'y' : 'n';

			$column['settings'] = $this->_save_settings($column);
			$column['settings']['field_required'] = $column['required'];

			$column_data = array(
				'field_id'			=> $settings['field_id'],
				'col_order'			=> $count,
				'col_type'			=> $column['type'],
				'col_label'			=> $column['label'],
				'col_name'			=> $column['name'],
				'col_instructions'	=> $column['instr'],
				'col_required'		=> $column['required'],
				'col_search'		=> $column['searchable'],
				'col_settings'		=> json_encode($column['settings'])
			);

			// Attempt to get the column ID; if the field name contains 'new_',
			// it's a new field, otherwise extract column ID
			$col_id = (strpos($col_field, 'new_') === FALSE)
				? str_replace('col_id_', '', $col_field) : FALSE;

			$col_ids[] = ee()->grid_model->save_col_settings($column_data, $col_id);

			$count++;
		}

		// Delete columns that were not including in new field settings
		if ( ! $new_field)
		{
			$columns = ee()->grid_model->get_columns_for_field($settings['field_id'], FALSE);

			$old_cols = array();
			foreach ($columns as $column)
			{
				$old_cols[$column['col_id']] = $column['col_type'];
			}

			// Compare columns in DB to ones we gathered from the settings array
			$cols_to_delete = array_diff(array_keys($old_cols), $col_ids);

			// If any columns are missing from the new settings, delete them
			if ( ! empty($cols_to_delete))
			{
				ee()->grid_model->delete_columns($cols_to_delete, $old_cols, $settings['field_id']);
			}
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Calls grid_save_settings() on fieldtypes to do any extra processing on
	 * saved field settings
	 *
	 * @param	array	Column settings data
	 * @return	array	Processed settings
	 */
	protected function _save_settings($column)
	{
		$ft_api = ee()->api_channel_fields;

		$ft_api->setup_handler($column['type']);

		if ($ft_api->check_method_exists('grid_save_settings'))
		{
			return $ft_api->apply('grid_save_settings', array($column['settings']));
		}

		return $column['settings'];
	}

	// ------------------------------------------------------------------------
	
	/**
	 * Returns rendered HTML for a column on the field settings page
	 *
	 * @param	array	Array of single column settings from the grid_columns table
	 * @return	string	Rendered column view for settings page
	 */
	public function get_column_view($column = NULL)
	{
		$fieldtypes = $this->get_grid_fieldtypes();

		// Create a dropdown-frieldly array of available fieldtypes
		$fieldtypes_dropdown = array();
		foreach ($fieldtypes as $key => $value)
		{
			$fieldtypes_dropdown[$key] = $value['name'];
		}

		$field_name = (empty($column)) ? 'new_0' : 'col_id_'.$column['col_id'];

		$column['settings_form'] = (empty($column))
			? $this->get_settings_form('text') : $this->get_settings_form($column['col_type'], $column);

		return ee()->load->view(
			'col_tmpl',
			array(
				'field_name'	=> $field_name,
				'column'		=> $column,
				'fieldtypes'	=> $fieldtypes_dropdown
			),
			TRUE
		);
	}

	// ------------------------------------------------------------------------

	/**
	 * Returns rendered HTML for the custom settings form of a grid column type
	 *
	 * @param	string	Name of fieldtype to get settings form for
	 * @param	array	Column data from database to populate settings form
	 * @return	array	Rendered HTML settings form for given fieldtype and
	 * 					column data
	 */
	public function get_settings_form($type, $column = NULL)
	{
		$ft_api = ee()->api_channel_fields;

		$ft_api->setup_handler($type);

		// Returns blank settings form for a specific fieldtype
		if (empty($column))
		{
			$ft_api->setup_handler($type);

			return $this->_view_for_col_settings(
				$type,
				$ft_api->apply('grid_display_settings', array(array()))
			);
		}

		// Otherwise, return the prepopulated settings form based on column settings
		return $this->_view_for_col_settings(
			$type,
			$ft_api->apply('grid_display_settings', array($column['col_settings'])),
			$column['col_id']
		);
	}

	// ------------------------------------------------------------------------

	/**
	 * Returns rendered HTML for the custom settings form of a grid column type,
	 * helper method for Grid_lib::get_settings_form
	 *
	 * @param	string	Name of fieldtype to get settings form for
	 * @param	array	Column data from database to populate settings form
	 * @param	int		Column ID for field naming
	 * @return	array	Rendered HTML settings form for given fieldtype and
	 * 					column data
	 */
	protected function _view_for_col_settings($col_type, $col_settings, $col_id = NULL)
	{
		$settings_view = ee()->load->view(
			'col_settings_tmpl',
			array(
				'col_type'		=> $col_type,
				'col_settings'	=> (empty($col_settings)) ? array() : $col_settings
			),
			TRUE
		);
		
		$col_id = (empty($col_id)) ? 'new_0' : 'col_id_'.$col_id;

		// Namespace form field names
		return preg_replace(
			'/(<[input|select|textarea][^>]*)name=["\']([^"]*)["\']/',
			'$1name="grid[cols]['.$col_id.'][settings][$2]"',
			$settings_view
		);
	}
}

/* End of file Grid_lib.php */
/* Location: ./system/expressionengine/modules/grid/libraries/Grid_lib.php */