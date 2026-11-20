<?php
/* Copyright (C) 2004-2018  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019  Nicolas ZABOURI         <info@inovea-conseil.com>
 * Copyright (C) 2019-2024  Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026       Nicolas Silobre         <contact@ns-info.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \defgroup   nsaddeinvoicing     Module Nsaddeinvoicing
 *  \brief      Nsaddeinvoicing module descriptor.
 *
 *  \file       htdocs/nsaddeinvoicing/core/modules/modNsaddeinvoicing.class.php
 *  \ingroup    nsaddeinvoicing
 *  \brief      Description and activation file for module Nsaddeinvoicing
 */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module Nsaddeinvoicing
 */
class modNsaddeinvoicing extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;
		$this->numero = 136507;
		$this->rights_class = 'nsaddeinvoicing';
		$this->family = "NS INFO";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "NsaddeinvoicingDescription";
		$this->descriptionlong = "NsaddeinvoicingDescription";

		// Author
		$this->editor_name = 'NS INFO';
		$this->editor_url = 'https://www.ns-info.fr';

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated', 'experimental_deprecated' or a version string like 'x.y.z'
		$this->version = trim(file_get_contents(__DIR__.'/../../VERSION'));
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		$this->picto = 'nsaddeinvoicing@nsaddeinvoicing';

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
			'triggers' => 0,
			'css' => array('/nsaddeinvoicing/css/nsaddeinvoicing.css.php'),
			/* BEGIN MODULEBUILDER HOOKSCONTEXTS */
			'hooks' => array('invoicesuppliercard'),
			/* END MODULEBUILDER HOOKSCONTEXTS */
		);
		$this->dirs = array("/nsaddeinvoicing/temp");
		$this->config_page_url = array("setup.php@nsaddeinvoicing");
		$this->hidden = false;
		$this->depends = array('modNsinfo');	// NSINFO framework (checked in init() : activation refused if not enabled)
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("nsaddeinvoicing@nsaddeinvoicing");

		// Prerequisites
		$this->phpmin = array(7, 4); // Minimum version of PHP required by module
		$this->need_dolibarr_version = array(20, 0); // Minimum version of Dolibarr required by module (functions of Dolibarr 23: backport of the NSINFO framework)
		$this->need_javascript_ajax = 0;

		// Messages at activation
		$this->warnings_activation = array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		$this->warnings_activation_ext = array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)

		$this->const = array();

		if (!isModEnabled("nsaddeinvoicing")) {
			$conf->nsaddeinvoicing = new stdClass();
			$conf->nsaddeinvoicing->enabled = 0;
		}

		// Keep the MODULEBUILDER tags : ModuleBuilder uses them to add objects, permissions, menus...
		/* BEGIN MODULEBUILDER TABS */
		$this->tabs = array();
		/* END MODULEBUILDER TABS */

		/* BEGIN MODULEBUILDER DICTIONARIES */
		$this->dictionaries = array();
		/* END MODULEBUILDER DICTIONARIES */

		/* BEGIN MODULEBUILDER WIDGETS */
		$this->boxes = array();
		/* END MODULEBUILDER WIDGETS */

		/* BEGIN MODULEBUILDER CRON */
		$this->cronjobs = array();
		/* END MODULEBUILDER CRON */

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;
		/* BEGIN MODULEBUILDER PERMISSIONS */
		/* END MODULEBUILDER PERMISSIONS */

		// Menus provided by this module
		$this->menu = array();
		$r = 0;
		/* BEGIN MODULEBUILDER TOPMENU */
		/* END MODULEBUILDER TOPMENU */

		/* BEGIN MODULEBUILDER LEFTMENU MYOBJECT */
		/* END MODULEBUILDER LEFTMENU MYOBJECT */

		// Exports profiles provided by this module
		$r = 0;
		/* BEGIN MODULEBUILDER EXPORT MYOBJECT */
		/* END MODULEBUILDER EXPORT MYOBJECT */

		// Imports profiles provided by this module
		$r = 0;
		/* BEGIN MODULEBUILDER IMPORT MYOBJECT */
		/* END MODULEBUILDER IMPORT MYOBJECT */
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return     int                1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $langs;

		// NSINFO framework (module nsinfo) must be installed and enabled, in version 23.8.0 at least (backport of the
		// functions of Dolibarr 23 used by the pages generated by ModuleBuilder)
		$nsinfoversion = (string) @file_get_contents(dol_buildpath('/nsinfo/VERSION'));
		if (!isModEnabled('nsinfo') || version_compare(trim($nsinfoversion), '23.8.0', '<')) {
			$langs->load('nsaddeinvoicing@nsaddeinvoicing');
			$this->error = $langs->trans('NsaddeinvoicingErrorNsinfoRequired');
			dol_syslog(get_class($this).'::init '.$this->error, LOG_ERR);
			return 0;
		}

		$result = $this->_load_tables('/nsaddeinvoicing/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		// Permissions
		$this->remove($options);

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 *  Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *  Data directories are not deleted
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
