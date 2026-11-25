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
 *  Classe de description et d'activation du module Nsaddeinvoicing
 */
class modNsaddeinvoicing extends DolibarrModules
{
	/**
	 * Constructeur. Définit les noms, constantes, répertoires, boîtes, droits
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

		// Auteur
		$this->editor_name = 'NS INFO';
		$this->editor_url = 'https://www.ns-info.fr';

		// Valeurs possibles pour version : 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated', 'experimental_deprecated' ou une chaîne de version comme 'x.y.z'
		$this->version = trim(file_get_contents(__DIR__.'/../../VERSION'));
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		$this->picto = 'nsaddeinvoicing@nsaddeinvoicing';

		// Définit quelques fonctionnalités prises en charge par le module (triggers, login, substitutions, menus, css, etc.)
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
		$this->depends = array('modNsinfo');	// Framework NSINFO (vérifié dans init() : activation refusée si non activé)
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("nsaddeinvoicing@nsaddeinvoicing");

		// Prérequis
		$this->phpmin = array(7, 4); // Version minimale de PHP requise par le module
		$this->need_dolibarr_version = array(20, 0); // Version minimale de Dolibarr requise par le module (fonctions de Dolibarr 23 : backport du framework NSINFO)
		$this->need_javascript_ajax = 0;

		// Messages à l'activation
		$this->warnings_activation = array(); // Avertissement à afficher à l'activation du module. array('always'='text') ou array('FR'='textfr','MX'='textmx'...)
		$this->warnings_activation_ext = array(); // Avertissement à afficher à l'activation d'un module externe. array('always'='text') ou array('FR'='textfr','MX'='textmx'...)

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

		// Droits fournis par ce module
		$this->rights = array();
		$r = 0;
		/* BEGIN MODULEBUILDER PERMISSIONS */
		/* END MODULEBUILDER PERMISSIONS */

		// Menus fournis par ce module
		$this->menu = array();
		$r = 0;
		/* BEGIN MODULEBUILDER TOPMENU */
		/* END MODULEBUILDER TOPMENU */

		/* BEGIN MODULEBUILDER LEFTMENU MYOBJECT */
		/* END MODULEBUILDER LEFTMENU MYOBJECT */

		// Profils d'export fournis par ce module
		$r = 0;
		/* BEGIN MODULEBUILDER EXPORT MYOBJECT */
		/* END MODULEBUILDER EXPORT MYOBJECT */

		// Profils d'import fournis par ce module
		$r = 0;
		/* BEGIN MODULEBUILDER IMPORT MYOBJECT */
		/* END MODULEBUILDER IMPORT MYOBJECT */
	}

	/**
	 *  Fonction appelée à l'activation du module.
	 *  La fonction init ajoute dans la base Dolibarr les constantes, boîtes, droits et menus (définis dans le constructeur).
	 *  Elle crée aussi les répertoires de données
	 *
	 * @param string $options Options à l'activation du module ('', 'noboxes')
	 * @return     int                1 si OK, 0 si KO
	 */
	public function init($options = '')
	{
		global $langs;

		// Le framework NSINFO (module nsinfo) doit être installé et activé, en version 23.8.0 au minimum (backport des
		// fonctions de Dolibarr 23 utilisées par les pages générées par ModuleBuilder)
		$nsinfoversion = (string) @file_get_contents(dol_buildpath('/nsinfo/VERSION'));
		if (!isModEnabled('nsinfo') || version_compare(trim($nsinfoversion), '23.8.0', '<')) {
			$langs->load('nsaddeinvoicing@nsaddeinvoicing');
			$this->error = $langs->trans('NsaddeinvoicingErrorNsinfoRequired');
			dol_syslog(get_class($this).'::init '.$this->error, LOG_ERR);
			return 0;
		}

		$result = $this->_load_tables('/nsaddeinvoicing/sql/');
		if ($result < 0) {
			return -1; // Ne pas activer le module si l'erreur 'not allowed' est renvoyée lors du chargement des requêtes SQL du module (_load_table exécute le SQL avec run_sql, paramètre d'erreur autorisé réglé sur 'default')
		}

		// Droits
		$this->remove($options);

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 *  Fonction appelée à la désactivation du module.
	 *  Supprime de la base Dolibarr les constantes, boîtes et droits.
	 *  Les répertoires de données ne sont pas supprimés
	 *
	 * @param string $options Options à la désactivation du module ('', 'noboxes')
	 * @return     int                 1 si OK, 0 si KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
