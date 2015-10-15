<?php namespace Sintattica\Atk\Handlers;
/**
 * This file is part of the ATK distribution on GitHub.
 * Detailed copyright and licensing information can be found
 * in the doc/COPYRIGHT and doc/LICENSE files which should be
 * included in the distribution.
 *
 * @package atk
 * @subpackage handlers
 *
 * @copyright (c)2000-2004 Ivo Jansch
 * @copyright (c)2000-2004 Ibuildings.nl BV
 * @license http://www.achievo.org/atk/licensing ATK Open Source License
 *
 * @version $Revision: 6310 $
 * $Id$
 */

/**
 * Handler for the 'admin' action of a node. It displays a recordlist with
 * existing records, and links to view/edit/delete them (or custom actions
 * if present), and an embedded addform or a link to an addpage (depending
 * on the presence of the NF_ADD_LINK or NF_ADD_DIALOG flag).
 *
 * @author Ivo Jansch <ivo@achievo.org>
 * @package atk
 * @subpackage handlers
 *
 */
class AdminHandler extends ActionHandler
{
    var $m_actionSessionStatus = SESSION_NESTED;

    /**
     * The action method
     */
    function action_admin()
    {
        if (!empty($this->m_partial)) {
            $this->partial($this->m_partial);
            return;
        }

        $page = $this->getPage();
        $page->register_script(Config::getGlobal("atkroot") . "atk/javascript/formsubmit.js");
        $res = $this->renderAdminPage();
        $page->addContent($this->m_node->renderActionPage("admin", $res));
    }

    /**
     * Sets the action session status for actions in the recordlist.
     * (Defaults to SESSION_NESTED).
     *
     * @param Integer $sessionStatus The sessionstatus (for example SESSION_REPLACE)
     */
    function setActionSessionStatus($sessionStatus)
    {
        $this->m_actionSessionStatus = $sessionStatus;
    }

    /**
     * Render the adminpage, including addpage if necessary
     *
     * @return array with result of adminPage and addPage
     */
    function renderAdminPage()
    {
        $res = array();
        if ($this->m_node->hasFlag(NF_NO_ADD) == false && $this->m_node->allowed("add")) {
            if (!$this->m_node->hasFlag(NF_ADD_LINK) && !$this->m_node->hasFlag(NF_ADD_DIALOG)) { // otherwise, in adminPage, an add link will be added.
                // we could get here because of a reject.
                $record = $this->getRejectInfo();

                $res[] = $this->invoke("addPage", $record);
            }
        }
        $res[] = $this->invoke("adminPage");
        return $res;
    }

    /**
     * Draws the form for adding new records.
     *
     * The implementation delegates drawing of the form to the atkAddHandler.
     *
     * @param array $record The record
     * @return String A box containing the add page.
     */
    function addPage($record = null)
    {
        // Reuse the atkAddHandler for the addPage.
        $node = Module::atkGetNode($this->invoke('getAddNodeType'));

        $handler = $node->getHandler("add");
        $handler->setNode($node);
        $handler->setReturnBehaviour(ATK_ACTION_STAY); // have the save action stay on the admin page
        return $handler->invoke("addPage", $record);
    }

    /**
     * Admin page displays records and the actions that can be performed on
     * them (edit, delete)
     *
     * @param array $actions The list of actions displayed next to each
     *                       record. Nodes can implement a
     *                       recordActions($record, &$actions, &$mraactions)
     *                       method to add/remove record-specific actions.
     * @return String A box containing the admin page (without the add form,
     *                which is added later.
     */
    function adminPage($actions = "")
    {
        $ui = $this->getUi();

        $vars = array(
            "title" => $this->m_node->actionTitle($this->getNode()->m_action),
            "content" => $this->renderAdminList()
        );

        if ($this->getRenderMode() == 'dialog') {
            $output = $ui->renderDialog($vars);
        } else {
            $output = $ui->renderBox($vars);
        }

        return $output;
    }

    /**
     * Renders the recordlist for the admin mode
     *
     * @param Array $actions An array with the actions for the admin mode
     * @return String The HTML for the admin recordlist
     */
    function renderAdminList($actions = "")
    {
        $this->getNode()->addStyle("style.css");

        $grid = DataGrid::create($this->getNode(), 'admin');

        if (is_array($actions)) {
            $grid->setDefaultActions($actions);
        }

        $this->modifyDataGrid($grid, DataGrid::CREATE);

        if ($this->redirectToSearchAction($grid)) {
            return '';
        }

        $params = array();
        $params["header"] = $this->invoke("adminHeader") . $this->getHeaderLinks();
        $params["list"] = $grid->render();
        $params["footer"] = $this->invoke("adminFooter");
        $output = $this->getUi()->renderList("admin", $params);
        return $output;
    }

    /**
     * Update the admin datagrid.
     *
     * @return string new grid html
     */
    public function partial_datagrid()
    {
        try {
            $grid = DataGrid::resume($this->getNode());

            $this->modifyDataGrid($grid, DataGrid::RESUME);
        } catch (Exception $e) {
            $grid = DataGrid::create($this->getNode());

            $this->modifyDataGrid($grid, DataGrid::CREATE);
        }

        if ($this->redirectToSearchAction($grid)) {
            return '';
        }

        return $grid->render();
    }

    /**
     * If a search action has been defined and a search only returns one result
     * the user will be automatically redirected to the search action.
     *
     * @param DataGrid $grid data grid
     * @return boolean redirect active?
     */
    protected function redirectToSearchAction($grid)
    {
        $node = $this->getNode();
        $search = $grid->getPostvar('atksearch');

        // check if we are searching and a search action has been defined
        if (!is_array($search) || count($search) == 0 || !is_array($node->m_search_action) || count($node->m_search_action) == 0) {
            return false;
        }

        // check if there is only a single record in the result
        $grid->loadRecords();
        if ($grid->getCount() != 1) {
            return false;
        }

        $records = $grid->getRecords();

        foreach ($node->m_search_action as $action) {
            if (!$node->allowed($action, $records[0])) {
                continue;
            }

            // reset search so we can back to the normal admin screen if we want
            $grid->setPostvar('atksearch', array());

            $url = Tools::session_url(Tools::dispatch_url($node->atkNodeType(), $action,
                array('atkselector' => $node->primaryKey($records[0]))), SESSION_NESTED);

            if ($grid->isUpdate()) {
                $script = 'document.location.href = ' . JSON::encode($url) . ';';
                $node->getPage()->register_loadscript($script);
            } else {
                $node->redirect($url);
            }

            return true;
        }

        return false;
    }

    /**
     * Function that is called when creating an adminPage.
     *
     * The default implementation returns an empty string, but developers can
     * override this function in their custom handlers or directly in the
     * node class.
     *
     * @return String A string that is displayed above the recordlist.
     */
    function adminHeader()
    {
        return "";
    }

    /**
     * Function that is called when creating an adminPage.
     *
     * The default implementation returns an empty string, but developers can
     * override this function in their custom handlers or directly in the
     * node class.
     *
     * @return String A string that is displayed below the recordlist.
     */
    function adminFooter()
    {
        return "";
    }

    /**
     * Get the importlink to add to the admin header
     *
     * @return String HTML code with link to the import action of the node (if allowed)
     */
    function getImportLink()
    {
        $link = "";
        if ($this->m_node->allowed("add") && !$this->m_node->hasFlag(NF_READONLY) && $this->m_node->hasFlag(NF_IMPORT)) {
            $link .= Tools::href(Tools::dispatch_url($this->m_node->atkNodeType(), "import"),
                Tools::atktext("import", "atk", $this->m_node->m_type), SESSION_NESTED);
        }
        return $link;
    }

    /**
     * Get the exportlink to add to the admin header
     *
     * @return String HTML code with link to the export action of the node (if allowed)
     */
    function getExportLink()
    {
        $link = "";
        if ($this->m_node->allowed("view") && $this->m_node->allowed("export") && $this->m_node->hasFlag(NF_EXPORT)) {
            $filter = '';
            if (count($this->m_node->m_fuzzyFilters) > 0) {
                $filter = implode(' AND ',
                    str_replace('[table]', $this->m_node->getTable(), $this->m_node->m_fuzzyFilters));
            }

            $link .= Tools::href(Tools::dispatch_url($this->m_node->atkNodeType(), "export",
                array('atkfilter' => $filter)), Tools::atktext("export", "atk", $this->m_node->m_type),
                SESSION_NESTED);
        }
        return $link;
    }

    /**
     *
     * This function returns the nodetype that should be used for creating
     * the add form or add link above the admin grid. This defaults to the
     * node for this handler. Override this method in your handler or directly
     * in your node to set a custom nodetype.
     */
    public function getAddNodeType()
    {
        return $this->m_node->atkNodeType();
    }

    /**
     * Get the add link to add to the admin header
     *
     * @return String HTML code with link to the add action of the node (if allowed)
     */
    function getAddLink()
    {
        $node = Module::atkGetNode($this->invoke('getAddNodeType'));

        if (!$node->hasFlag(NF_NO_ADD) && $node->allowed("add")) {
            $label = $node->text("link_" . $node->m_type . "_add", null, "", "", true);
            if (empty($label)) {
                // generic text
                $label = Tools::atktext("add", "atk");
            }

            $add = $node->hasFlag(NF_ADD_DIALOG);
            $addorcopy = $node->hasFlag(NF_ADDORCOPY_DIALOG) &&
                AddOrCopyHandler::hasCopyableRecords($node);


            if ($add || $addorcopy) {
                $action = $node->hasFlag(NF_ADDORCOPY_DIALOG) ? 'addorcopy' : 'add';

                $dialog = new Dialog($node->atkNodeType(), $action, 'dialog');
                $dialog->setModifierObject($node);
                $dialog->setSessionStatus(SESSION_PARTIAL);
                $onClick = $dialog->getCall();

                return '
			      <a href="javascript:void(0)" onclick="' . $onClick . '; return false;" class="valignMiddle">' . $label . '</a>
			    ';
            } elseif ($node->hasFlag(NF_ADD_LINK)) {
                $addurl = $this->invoke('getAddUrl', $node);
                return Tools::atkHref($addurl, $label, SESSION_NESTED);
            }
        }

        return "";
    }

    /**
     * This function renders the url that is used by
     * AdminHandler::getAddLink().
     *
     * @return string The url for the add link for the admin page
     */
    public function getAddUrl()
    {
        $node = Module::atkGetNode($this->invoke('getAddNodeType'));
        return Tools::atkSelf() . '?atknodetype=' . $node->atkNodeType() . '&atkaction=add';
    }

    /**
     * Get all links to add to the admin header
     *
     * @return String String with the HTML code of the links (each link separated with |)
     */
    function getHeaderLinks()
    {
        $links = array();
        $addlink = $this->getAddLink();
        if ($addlink != "") {
            $links[] = $addlink;
        }
        $importlink = $this->getImportLink();
        if ($importlink != "") {
            $links[] = $importlink;
        }
        $exportlink = $this->getExportLink();
        if ($exportlink != "") {
            $links[] = $exportlink;
        }
        $result = implode(" | ", $links);

        if (strlen(trim($result)) > 0) {
            $result .= '<br/>';
        }

        return $result;
    }

    /**
     * Dialog handler.
     */
    function partial_dialog()
    {
        $this->setRenderMode('dialog');
        $result = $this->renderAdminPage();
        return $this->m_node->renderActionPage("admin", $result);
    }

    /**
     * Attribute handler.
     *
     * @param string $partial full partial
     */
    function partial_attribute($partial)
    {
        list($type, $attribute, $partial) = explode('.', $partial);

        $attr = $this->m_node->getAttribute($attribute);
        if ($attr == null) {
            Tools::atkerror("Unknown / invalid attribute '$attribute' for node '" . $this->m_node->atkNodeType() . "'");
            return '';
        }

        return $attr->partial($partial, 'admin');
    }

}

