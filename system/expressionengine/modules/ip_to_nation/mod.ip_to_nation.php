<?php

/*
=====================================================
 ExpressionEngine - by EllisLab
-----------------------------------------------------
 http://expressionengine.com/
-----------------------------------------------------
 Copyright (c) 2003 - 2010, EllisLab, Inc.
=====================================================
 THIS IS COPYRIGHTED SOFTWARE
 PLEASE READ THE LICENSE AGREEMENT
 http://expressionengine.com/docs/license.html
=====================================================
 File: mod.ip_to_nation.php
-----------------------------------------------------
 Purpose: IP to Nation mapping
=====================================================
*/

if ( ! defined('EXT'))
{
	exit('Invalid file request');
}


class Ip_to_nation {

	var $return_data = '';


	function Ip_to_nation()
	{
		// Make a local reference to the ExpressionEngine super object
		$this->EE =& get_instance();
	}


	/** ----------------------------------------
	/**  World flags
	/** ----------------------------------------*/
	function world_flags($ip = '')
	{
		if ($ip == '')
			$ip = $this->EE->TMPL->tagdata;

			$ip = trim($ip);

		if ( ! $this->EE->input->valid_ip($ip))
		{
			$this->return_data = $ip;
			return;
		}

		$query = $this->EE->db->query("SELECT country FROM exp_ip2nation WHERE ip < INET_ATON('".$this->EE->db->escape_str($ip)."') ORDER BY ip DESC LIMIT 0,1");

		if ($query->num_rows() != 1)
		{
			$this->return_data = $ip;
			return;
		}

		$country = $this->get_country($query->row('country') );

		if ($this->EE->TMPL->fetch_param('type') == 'text')
		{
			$this->return_data = $country;
		}
		else
		{
			$this->return_data = '<img src="'.$this->EE->TMPL->fetch_param('image_url').'flag_'.$query->row('country') .'.gif" width="18" height="12" alt="'.$country.'" title="'.$country.'" />';
		}

		return $this->return_data;
	}




	/** ----------------------------------------
	/**  Countries
	/** ----------------------------------------*/
	function get_country($which = '')
	{
		if ( ! isset($this->EE->session->cache['ip_to_nation']['countries']))
		{
			if ( ! include_once(APPPATH.'config/countries.php'))
			{
				$this->EE->TMPL->log_item("IP to Nation Module Error: Countries library file not found");
				return 'Unknown';
			}

			$this->EE->session->cache['ip_to_nation']['countries'] = $countries;
		}

		if ( ! isset($this->EE->session->cache['ip_to_nation']['countries'][$which]))
		{
			return 'Unknown';
		}

		return $this->EE->session->cache['ip_to_nation']['countries'][$which];
	}



}
// END CLASS

/* End of file mod.ip_to_nation.php */
/* Location: ./system/expressionengine/modules/ip_to_nation/mod.ip_to_nation.php */