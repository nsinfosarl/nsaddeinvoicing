<?php
/* Copyright (C) 2026 Nicolas Silobre <contact@ns-info.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    nsaddeinvoicing/class/actions_nsaddeinvoicing.class.php
 * \ingroup nsaddeinvoicing
 * \brief   Hooks of the card of the supplier invoices : mass actions on the lines of a draft invoice (change of the reference, deletion)
 */

/**
 * Class ActionsNsaddeinvoicing
 */
class ActionsNsaddeinvoicing
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();

	/**
	 * @var array<string,mixed> Hook results
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook()
	 */
	public $resprints;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return if the mass actions are available on this card : draft supplier invoice (not a situation or template), right to modify it
	 *
	 * @param	array<string,mixed>	$parameters	Hook metadata
	 * @param	?CommonObject		$object		Object
	 * @return	bool
	 */
	private function isAvailable($parameters, $object)
	{
		global $user;

		if (!is_object($object) || empty($object->id) || $object->element !== 'invoice_supplier' || (int) $object->statut !== 0) {
			return false;
		}
		if (empty($parameters['context']) || !in_array('invoicesuppliercard', explode(':', $parameters['context']))) {
			return false;
		}

		return $user->hasRight('fournisseur', 'facture', 'creer') || $user->hasRight('supplier_invoice', 'creer');
	}

	/**
	 * Products available for the supplier of the invoice : one entry for each product and reference of the supplier (price of purchase of the supplier)
	 *
	 * @param	CommonObject	$object	Supplier invoice
	 * @return	array<int,array{key:string,pid:int,ref:string,label:string,reffourn:string}>
	 */
	private function getSupplierProducts($object)
	{
		$list = array();
		$sql = "SELECT DISTINCT p.rowid as pid, p.ref, p.label, pfp.ref_fourn";
		$sql .= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price as pfp";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = pfp.fk_product";
		$sql .= " WHERE pfp.fk_soc = ".((int) $object->socid);
		$sql .= " AND pfp.entity IN (".getEntity('productsupplierprice').")";
		$sql .= " AND p.entity IN (".getEntity('product').")";
		$sql .= " AND p.tobuy = 1";
		$sql .= " ORDER BY pfp.ref_fourn, p.ref";
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$list[] = array('key' => $obj->pid.'|'.$obj->ref_fourn, 'pid' => (int) $obj->pid, 'ref' => (string) $obj->ref, 'label' => (string) $obj->label, 'reffourn' => (string) $obj->ref_fourn);
			}
		}

		return $list;
	}

	/**
	 * Actions of the card : change of the reference of the lines checked, deletion of the lines checked
	 *
	 * @param	array<string,mixed>	$parameters		Hook metadata
	 * @param	CommonObject		$object			Object
	 * @param	string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (!in_array($action, array('nsaddei_changeref', 'nsaddei_deletelines'))) {
			return 0;
		}
		if (!$this->isAvailable($parameters, $object)) {
			accessforbidden();
		}
		$langs->load('nsaddeinvoicing@nsaddeinvoicing');
		$url = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);
		if (GETPOST('token', 'alpha') !== currentToken()) {
			$langs->load('errors');
			setEventMessages($langs->trans('ErrorBadValueForParameter', 'token', 'token'), null, 'errors');
			header('Location: '.$url);
			exit;
		}
		$object->fetch_lines();
		$lineids = array();
		foreach ($object->lines as $line) {
			$lineids[(int) $line->id] = $line;
		}

		if ($action == 'nsaddei_changeref') {
			// New reference chosen for each line : key "<product id>|<reference of the supplier>" checked in the list of the supplier of the invoice
			$allowed = array();
			foreach ($this->getSupplierProducts($object) as $p) {
				$allowed[$p['key']] = $p;
			}
			$chosen = GETPOST('nsaddei_new', 'array:alphanohtml');
			$nb = 0;
			foreach ((array) $chosen as $lineid => $key) {
				$lineid = (int) $lineid;
				if (!isset($lineids[$lineid]) || $key === '' || !isset($allowed[$key])) {
					continue;
				}
				$p = $allowed[$key];
				$sql = "UPDATE ".MAIN_DB_PREFIX."facture_fourn_det SET fk_product = ".((int) $p['pid']).", ref = '".$this->db->escape($p['reffourn'])."'";
				$sql .= " WHERE rowid = ".$lineid." AND fk_facture_fourn = ".((int) $object->id);
				if ($this->db->query($sql)) {
					$nb++;
				} else {
					setEventMessages($this->db->lasterror(), null, 'errors');
				}
			}
			setEventMessages($langs->trans('NsaddeinvoicingChangedRefs', $nb), null, $nb ? 'mesgs' : 'warnings');
		} else {
			$nb = 0;
			foreach ((array) GETPOST('nsaddei_lineids', 'array:int') as $lineid) {
				$lineid = (int) $lineid;
				if (!isset($lineids[$lineid])) {
					continue;
				}
				if ($object->deleteline($lineid) > 0) {
					$nb++;
				} else {
					setEventMessages($object->error, $object->errors, 'errors');
				}
			}
			setEventMessages($langs->trans('NsaddeinvoicingDeletedLines', $nb), null, $nb ? 'mesgs' : 'warnings');
		}
		header('Location: '.$url);
		exit;
	}

	/**
	 * End of the page : checkbox of each line, list of the mass actions above the lines (with Font Awesome 7 pictos) and popups
	 *
	 * @param	array<string,mixed>	$parameters		Hook metadata
	 * @param	?CommonObject		$object			Not given : read in the globals
	 * @param	?string				$action			Not given : read in the globals
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0
	 */
	public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $db;

		$obj = isset($GLOBALS['object']) ? $GLOBALS['object'] : null;
		if (!$this->isAvailable($parameters, $obj) || in_array(GETPOST('action', 'aZ09'), array('edit', 'editline', 'create', 'valid', 'delete'))) {
			return 0;
		}
		$langs->load('nsaddeinvoicing@nsaddeinvoicing');
		dol_include_once('/nsinfo/lib/nsinfo.lib.php');
		if (!function_exists('nsinfoFaIcon')) {
			return 0;
		}
		if (empty($obj->lines)) {
			$obj->fetch_lines();
		}
		$lines = array();
		foreach ($obj->lines as $l) {
			$prod = '';
			if (!empty($l->fk_product)) {
				$p = new Product($this->db);
				if ($p->fetch($l->fk_product) > 0) {
					$prod = $p->ref;
				}
			}
			$lines[(int) $l->id] = array('prod' => $prod, 'refsup' => (string) (!empty($l->ref_supplier) ? $l->ref_supplier : (isset($l->ref) ? $l->ref : '')), 'label' => dol_string_nohtmltag((string) (!empty($l->label) ? $l->label : $l->desc), 1));
		}
		$products = $this->getSupplierProducts($obj);
		$conf = array(
			'lines' => $lines, 'products' => $products,
			'icons' => array('change' => nsinfoFaIcon('arrows-rotate', '#3879B2', 'solid', 'pictofixedwidth'), 'delete' => nsinfoFaIcon('trash-can', '#C62828', 'solid', 'pictofixedwidth'), 'actions' => nsinfoFaIcon('list-check', '#5A2D82', 'solid', 'pictofixedwidth')),
			'txt' => array(
				'actions' => $langs->transnoentitiesnoconv('NsaddeinvoicingActions'), 'change' => $langs->transnoentitiesnoconv('NsaddeinvoicingChangeRef'), 'delete' => $langs->transnoentitiesnoconv('NsaddeinvoicingDeleteLines'),
				'current' => $langs->transnoentitiesnoconv('NsaddeinvoicingCurrentRef'), 'new' => $langs->transnoentitiesnoconv('NsaddeinvoicingNewRef'), 'keep' => $langs->transnoentitiesnoconv('NsaddeinvoicingKeep'),
				'none' => $langs->transnoentitiesnoconv('NsaddeinvoicingNoRef'), 'ok' => $langs->transnoentitiesnoconv('Modify'), 'cancel' => $langs->transnoentitiesnoconv('Cancel'), 'del' => $langs->transnoentitiesnoconv('Delete'),
				'confirmdel' => $langs->transnoentitiesnoconv('NsaddeinvoicingConfirmDelete'), 'noprod' => $langs->transnoentitiesnoconv('NsaddeinvoicingNoSupplierProduct'), 'all' => $langs->transnoentitiesnoconv('NsaddeinvoicingCheckAll'),
			),
			'token' => newToken(), 'url' => $_SERVER['PHP_SELF'].'?id='.((int) $obj->id),
		);
		$js = <<<'JS'
jQuery(function ($) {
	var C = window.nsaddei, table = $("#tablelines");
	if (!table.length) { return; }
	// Checkbox of each line (first column) and of the header
	table.find("tr").each(function () {
		var tr = $(this), m = /^row-(\d+)$/.exec(tr.attr("id") || ""), tag = tr.children("th").length && !m ? "th" : "td", html = "";
		if (tr.hasClass("liste_titre") && !m) { html = "<input type=\"checkbox\" class=\"nsaddei-all\" title=\"" + C.txt.all + "\">"; }
		else if (m) { html = "<input type=\"checkbox\" class=\"nsaddei-check\" value=\"" + m[1] + "\">"; }
		tr.prepend("<" + tag + " class=\"nsaddei-sel center\" style=\"width: 24px;\">" + html + "</" + tag + ">");
	});
	// List of the mass actions (menu with Font Awesome 7 pictos), shown when at least one line is checked
	var bar = $("<div class=\"nsaddei-bar\" style=\"display: none; margin: 4px 0 8px 0; position: relative;\"></div>");
	var btn = $("<a href=\"#\" class=\"butAction nsaddei-btn\" style=\"margin: 0;\"></a>").html(C.icons.actions + C.txt.actions + " <span class=\"nsaddei-nb\"></span> <span class=\"opacitymedium\">&#9662;</span>");
	var menu = $("<ul class=\"nsaddei-menu\" style=\"display: none; position: absolute; z-index: 1000; list-style: none; margin: 2px 0 0 0; padding: 4px 0; background: var(--colorbackbody, #fff); border: 1px solid rgba(128,128,128,.35); border-radius: 8px; box-shadow: 0 4px 14px rgba(20,24,43,.18); min-width: 240px;\"></ul>");
	menu.append($("<li><a href=\"#\" data-act=\"change\" style=\"display: block; padding: 6px 14px; text-decoration: none;\">" + C.icons.change + C.txt.change + "</a></li>"));
	menu.append($("<li><a href=\"#\" data-act=\"delete\" style=\"display: block; padding: 6px 14px; text-decoration: none;\">" + C.icons.delete + C.txt.delete + "</a></li>"));
	bar.append(btn).append(menu); table.before(bar);
	var checked = function () { return table.find("input.nsaddei-check:checked").map(function () { return this.value; }).get(); };
	var refresh = function () { var n = checked().length; bar.toggle(n > 0); bar.find(".nsaddei-nb").text(n ? "(" + n + ")" : ""); if (!n) { menu.hide(); } table.find("input.nsaddei-all").prop("checked", n > 0 && n === table.find("input.nsaddei-check").length); };
	table.on("change", "input.nsaddei-check", refresh);
	table.on("change", "input.nsaddei-all", function () { table.find("input.nsaddei-check").prop("checked", this.checked); refresh(); });
	btn.on("click", function (e) { e.preventDefault(); menu.toggle(); });
	$(document).on("click", function (e) { if (!$(e.target).closest(".nsaddei-bar").length) { menu.hide(); } });
	var esc = function (s) { return $("<div>").text(s == null ? "" : s).html(); };
	var post = function (action, fields) {
		var f = $("<form method=\"POST\" style=\"display: none;\"></form>").attr("action", C.url).append($("<input type=\"hidden\" name=\"token\">").val(C.token)).append($("<input type=\"hidden\" name=\"action\">").val(action));
		$.each(fields, function (i, kv) { f.append($("<input type=\"hidden\">").attr("name", kv[0]).val(kv[1])); });
		$("body").append(f); f.submit();
	};
	menu.on("click", "a", function (e) {
		e.preventDefault(); menu.hide();
		var ids = checked(), act = $(this).data("act");
		if (!ids.length) { return; }
		if (act === "delete") {
			var d = $("<div></div>").html("<p>" + esc(C.txt.confirmdel.replace("%s", ids.length)) + "</p>");
			d.dialog({modal: true, width: 440, resizable: false, title: C.txt.delete, buttons: [
				{text: C.txt.del, click: function () { post("nsaddei_deletelines", ids.map(function (i) { return ["nsaddei_lineids[]", i]; })); }},
				{text: C.txt.cancel, click: function () { $(this).dialog("close"); }}]});
			return;
		}
		// Change of the reference : current reference of each line, and new reference among the products of the supplier of the invoice
		var opts = "<option value=\"\">" + esc(C.txt.keep) + "</option>";
		$.each(C.products, function (i, p) { opts += "<option value=\"" + esc(p.key) + "\">" + esc((p.reffourn ? p.reffourn + " - " : "") + p.ref + (p.label ? " - " + p.label : "")) + "</option>"; });
		var html = "<table class=\"noborder centpercent\"><tr class=\"liste_titre\"><td>" + esc(C.txt.current) + "</td><td>" + esc(C.txt.new) + "</td></tr>";
		$.each(ids, function (i, id) {
			var l = C.lines[id] || {}, cur = (l.refsup ? l.refsup + " " : "") + (l.prod ? "(" + l.prod + ")" : ""), lab = l.label ? "<br><span class=\"opacitymedium small\">" + esc(l.label.substr(0, 80)) + "</span>" : "";
			html += "<tr class=\"oddeven\"><td>" + (cur ? esc(cur) : "<span class=\"opacitymedium\">" + esc(C.txt.none) + "</span>") + lab + "</td><td><select class=\"flat nsaddei-new minwidth300\" id=\"nsaddei_new_" + id + "\" name=\"nsaddei_new_" + id + "\" data-line=\"" + id + "\">" + opts + "</select></td></tr>";
		});
		html += "</table>" + (C.products.length ? "" : "<p class=\"warning\">" + esc(C.txt.noprod) + "</p>");
		var d2 = $("<div></div>").html(html);
		d2.dialog({modal: true, width: 760, resizable: false, title: C.txt.change, buttons: [
			{text: C.txt.ok, click: function () { var f = []; d2.find("select.nsaddei-new").each(function () { if ($(this).val()) { f.push(["nsaddei_new[" + $(this).data("line") + "]", $(this).val()]); } }); if (f.length) { post("nsaddei_changeref", f); } else { $(this).dialog("close"); } }},
			{text: C.txt.cancel, click: function () { $(this).dialog("close"); }}],
			open: function () { if ($.fn.select2) { d2.find("select").select2({dropdownParent: d2.parent(), width: "100%"}); } }});
	});
	refresh();
});
JS;
		print '<script nonce="'.getNonce().'">window.nsaddei='.json_encode($conf).';'.$js.'</script>';

		return 0;
	}
}
