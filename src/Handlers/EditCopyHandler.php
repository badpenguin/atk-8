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
 * @copyright (c)2000-2004 Ibuildings.nl BV
 * @license http://www.achievo.org/atk/licensing ATK Open Source License
 *
 * @version $Revision: 6095 $
 * $Id$
 */

/**
 * Handler for the 'editcopy' action of a node. It copies the selected
 * record, and then redirects to the edit action for the copied record.
 *
 * @author Peter C. Verhage <peter@ibuildings.nl>
 * @package atk
 * @subpackage handlers
 *
 */
class EditCopyHandler extends ActionHandler
{

    /**
     * The action method.
     */
    function action_editcopy()
    {
        Tools::atkdebug("node::action_editcopy()");

        $record = $this->getCopyRecord();
        // allowed to editcopy record?
        if (!$this->allowed($recordset)) {
            $this->renderAccessDeniedPage();
            return;
        }

        $db = $this->m_node->getDb();
        if (!$this->m_node->copyDb($record)) {
            $db->rollback();
            $location = $this->m_node->feedbackUrl("editcopy", ACTION_FAILED, $record, $db->getErrorMsg());
            $this->m_node->redirect($location);
        } else {
            $db->commit();
            $this->clearCache();
            $location = Tools::session_url(Tools::dispatch_url($this->m_node->atknodetype(), "edit",
                array("atkselector" => $this->m_node->primaryKey($record))), SESSION_REPLACE);
            $this->m_node->redirect($location);
        }
    }

    /**
     * Get the selected record from
     *
     * @return the record to be copied
     */
    protected function getCopyRecord()
    {
        $selector = $this->m_postvars['atkselector'];
        $recordset = $this->m_node->selectDb($selector, "", "", "", "", "copy");
        if (count($recordset) > 0) {
            return $recordset[0];
        } else {
            Tools::atkdebug("Geen records gevonden met selector: $selector");
            $this->m_node->redirect();
        }
    }

}
