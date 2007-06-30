<?php
atkimport('atk.utils.atkjson');
atkimport('atk.relations.controls.atkshuttlecontrol');
atkimport('atk.relations.controls.atkshuttlefilter');

class atkExtendableShuttleRelation extends atkManyToManyRelation
{
  protected $m_controlsBySection = array();

  public function __construct($name, $link, $destination, $flags=0)
  {
    parent::__construct($name, $link, $destination, $flags);
    $this->m_controlsBySection[atkShuttleControl::AVAILABLE] = array();
    $this->m_controlsBySection[atkShuttleControl::SELECTED] = array();
  }

  public function addControl($control, $section)
  {
    $control->setSection($section);
    $control->setShuttle($this);
    $this->m_controlsBySection[$section][$control->getName()] = $control;
    $control->init();
  }

  /**
   * Re-renders the section contents based on the current filter values.
   *
   * Using $this->getOwnerInstance()->updateRecord() the current "record" can
   * be retrieved. $record[$this->fieldName()] contains the following entries:
   *
   * - "section" => section ("available" or "selected")
   * - "controls" => control values (see atkShuttleControl::getValue)
   * - "selected" => currently selected records (keys)
   * - "available" => currently available records (keys) (should not be used by this method!)
   */
  public function partial_filter()
  {
    $redraw = false;
    $record = $this->getOwnerInstance()->updateRecord();
    $mode   = $ATK_VARS["atkaction"];
    $prefix = $this->getOwnerInstance()->m_postvars['atkfieldprefix'];

    foreach ($this->m_controlsBySection[$record[$this->fieldName()]["section"]] as $control)
    {
      if (is_a($control, 'atkShuttleFilter'))
      {
        $redraw = true;
        $control->getFilter($record);
      }
    }

    $res = "<script language=\"text/javascript\">";
    foreach ($this->m_controlsBySection[$record[$this->fieldName()]["section"]] as $control)
    {
      if ($control->needsRefresh('filter', $record))
      {
        $res .= "$('".$control->getFormName($prefix)."').innerHTML = ".atkJSON::encode($control->render($record, $mode, $prefix)).";";
      }
    }

    if ($redraw)
    {
      $res .= "$('".$this->getHtmlId($prefix)."_".$record[$this->fieldName()]["section"]."').innerHTML = ".atkJSON::encode($this->_renderSelectBoxes($record[$this->fieldName()]["section"],$record,$mode,$prefix)).";";
    }
    $res .= "</script>";

    return $res;
  }

  function _renderSelectBoxes($side,$record,$mode,$prefix)
  {
    if ($side=='available')
    {
      $rs = $this->_getAvailableFields($record, $mode);
      $name  = $this->getHtmlId($prefix)."[available]";
      $opp   = $this->getHtmlId($prefix).'[cselected][]['.$this->getRemoteKey().']';
      $sel   = 0;
    }
    else
    {
      $rs = $this->_getSelectedFields($record);
      $opp   = $this->getHtmlId($prefix)."[available]";
      $name  = $this->getHtmlId($prefix).'[cselected][]['.$this->getRemoteKey().']';
      $sel   = 1;
    }

    return $this->_renderSelect($name,$rs,$opp,$prefix,$sel);
  }

  /**
   * A new selection has been made. Allows some controls to re-render
   * themselves based on the new selection.
   *
   * Using $this->getOwnerInstance()->updateRecord() the current "record" can
   * be retrieved. $record[$this->fieldName()] contains the following entries:
   *
   * - "action" => "add" or "delete"
   * - "item" => added or deleted record (key)
   * - "controls" => control values (see atkShuttleControl::getValue)
   * - "selected" => currently selected records (keys)
   * - "available" => currently available records (keys)
   */
  function partial_selection()
  {
    $record = $this->getOwnerInstance()->updateRecord();
    $mode   = $ATK_VARS["atkaction"];
    $prefix = $this->getOwnerInstance()->m_postvars['atkfieldprefix'];

    // Also add filters after a selection has been made
    $res = $this->partial_filter();

    $res.= "<script language=\"text/javascript\">";
    foreach ($this->m_controlsBySection[atkShuttleControl::AVAILABLE] as $control)
    {
      if ($control->needsRefresh('selection', $record))
      {
        $res .= "$('".$control->getFormName($prefix)."').innerHTML = ".atkJSON::encode($control->render($record, $mode, $prefix)).";";
      }
    }
    foreach ($this->m_controlsBySection[atkShuttleControl::SELECTED] as $control)
    {
      if ($control->needsRefresh('selection', $record))
      {
        $res .= "$('".$control->getFormName($prefix)."').innerHTML = ".atkJSON::encode($control->render($record, $mode, $prefix)).";";
      }
    }
    $res .= "</script>";

    return $res;
  }

  function edit($record, $fieldprefix, $mode)
  {
    // Add onchange handler
    $mode == "add" ? "add" : "edit";
    $url = addslashes(partial_url($this->m_ownerInstance->atkNodeType(), $mode, "attribute.".$this->getHtmlId($fieldprefix).".selection", array("atkfieldprefix" => $fieldprefix)));
    $this->addOnChangeHandler("shuttle_refresh('$url', '".$this->getHtmlId($fieldprefix).'[cselected][]['.$this->getRemoteKey().']'."', '".$prefix.$this->fieldName()."[section]', el);");
    $this->_renderChangeHandler($fieldprefix);

    // Get controls for 'available' side
    foreach ($this->m_controlsBySection[atkShuttleControl::AVAILABLE] as $control)
    {
      $ava_controls[] = $control->render($record, $mode, $fieldprefix);
    }

    // Get controls for 'selected' side
    foreach ($this->m_controlsBySection[atkShuttleControl::SELECTED] as $control)
    {
      $sel_controls[] = $control->render($record, $mode, $fieldprefix);
    }

    // Get selected records
    $right = $this->_getSelectedFields($record);
    for($i=0, $_i=count($right); $i<$_i; $i++)
    {
      $selected_options[$right[$i][$this->m_destInstance->primaryKeyField()]] = $this->m_destInstance->descriptor($right[$i]);
    }

    // Get available records
    $left  = $this->_getAvailableFields($record, $mode);
    for($i=0, $_i=count($left); $i<$_i; $i++)
    {
      $available_options[$left[$i][$this->m_destInstance->primaryKeyField()]] = $this->m_destInstance->descriptor($left[$i]);
    }

    $leftname  = $this->getHtmlId($fieldprefix)."[available]";
    $rightname = $this->getHtmlId($fieldprefix).'[cselected][]['.$this->getRemoteKey().']';
    $name      = $this->getHtmlId($fieldprefix).'[selected][]['.$this->getRemoteKey().']';

    // Build jsonned value for selected fields
    foreach ($right as $fld)
    {
      $vals[] = $fld[$this->m_destInstance->primaryKeyField()];
    }
    $value = atkJSON::encode($vals);
    if ($value=="null") $value="[]";

    // on submit, we must select all items in the right selector, as unselected items will not be posted.
    $page = &$this->m_ownerInstance->getPage();
    $page->register_script(atkconfig("atkroot")."atk/javascript/class.atkextendableshuttlerelation.js");
    $page->register_script(atkconfig("atkroot")."atk/javascript/json/json.js");
    $page->register_submitscript("shuttle_selectAll('".$rightname."');");

    $ui = ATKUI::getInstance();
    return $ui->render('extendableshuttle.tpl',array(
                                'leftname'=>$leftname,
                                'rightname'=>$rightname,
                                'name'=>$name,
                                'htmlid'=>$this->getHtmlId($fieldprefix),
                                'remotekey'=>$this->getRemoteKey(),
                                'value'=>$value,
                                'ava_controls'=>$ava_controls,
                                'sel_controls'=>$sel_controls,
                                'available_options'=>$available_options,
                                'selected_options'=>$selected_options,
    ));

    return $output;
  }

  function load($notused, $record)
  {
    $res = parent::load($notused, $record);
    $ret['selected'] = $res;
    return $ret;
  }

  function fetchValue($postvars)
  {
    $ret = array();
    $vals = atkJSON::decode($postvars[$this->fieldName()]['selected'][0][$this->getRemoteKey()],true);
    if (is_array($vals))
    {
      foreach ($vals as $val)
      {
        $ret[][$this->getRemoteKey()] = $val;
      }
    }
    $postvars[$this->fieldName()]['selected'] = $ret;
    return $postvars[$this->fieldName()];
  }

  function store($notused, &$record, $mode)
  {
    $rec = $record[$this->fieldName()];
    $record[$this->fieldName()] = $record[$this->fieldName()]['selected'];
    $res = parent::store($notused, $record, $mode);
    $record[$this->fieldName()]["selected"] = $rec["selected"];
    return $res;
  }

  function display($record, $mode="")
  {
    $record[$this->fieldName()] = $record[$this->fieldName()]['selected'];
    return parent::display($record,$mode);
  }

  /**
   * Render the multiselect list control
   * @access private
   * @param String $name The name of the list control
   * @param array $recordset The list of records to render in the control
   * @param String $opposite The name of the list control connected to this list control for shuttle actions
   * @param String $prefix The prefix which is needed for determining the correct JS name
   * @param bool $isSelected Whether or not this is the selectbox with the selectedItems (needed for onchangecode)
   * @return String piece of html code
   */
  function _renderSelect($name, $recordset, $opposite, $prefix, $isSelected)
  {
    if ($isSelected) {
      $onchangecode = $this->getHtmlId($prefix).'_onChange(\'selected\');';
      $action = "del";
    } else {
      $onchangecode = $this->getHtmlId($prefix).'_onChange(\'available\');';
      $action = "add";
    }

    $valName = $this->getHtmlId($fieldprefix).'[selected][]['.$this->getRemoteKey().']';
    $result = '<select class="shuttle_select" id="'.$name.'" name="'.$name.'" multiple size="10" onDblClick="shuttle_move(\''.$name.'\', \''.$opposite.'\',\''.$action.'\',\''.$valName.'\');'.$onchangecode.'">';
    for($i=0, $_i=count($recordset); $i<$_i; $i++)
    {
      $result.= '<option value="'.$recordset[$i][$this->m_destInstance->primaryKeyField()].'">'.$this->m_destInstance->descriptor($recordset[$i]).'</option>';
    }
    $result.= '</select>';
    return $result;
  }

  /**
   * Get array with all selected fields from record
   * @access private
   * @param array $record The record with the currently selected fields
   * @return array available records
   */
  function _getSelectedFieldsFromRecord($record)
  {
    $selectedPk = array();
    $this->createLink();
    $this->createDestination();

    if (isset($record[$this->m_name]["selected"]) && is_array($record[$this->m_name]["selected"]))
    {
      foreach ($record[$this->m_name]["selected"] as $rec)
      {
        if(is_array($rec[$this->getRemoteKey()]))
          $selectedPk[] = $this->m_destInstance->primaryKey($rec[$this->getRemoteKey()]);
        else
          $selectedPk[] = $this->m_destInstance->primaryKey(array($this->m_destInstance->primaryKeyField()=>$rec[$this->getRemoteKey()]));
      }
    }

    return $selectedPk;
  }

  /**
   * Get array with all selected fields
   * @param array $record The record with the currently selected fields
   * @param String $mode for which mode we are rendering
   * @return array selected records
   */
  function _getSelectedFields($record, $mode='add')
  {
    $res = array();
    $recs = $this->_getSelectableRecords($record, $mode);
    $selectedFields = $this->_getSelectedFieldsFromRecord($record);

    foreach ($recs as $rec)
    {
      if (in_array($this->m_destInstance->primaryKey($rec),$selectedFields))
      {
        $res[] = $rec;
      }
    }
    return $res;
  }

  /**
   * Get array with all available fields (which are not already selected)
   * @param array $record The record with the currently selected fields
   * @param String $mode for which mode we are rendering
   * @return array available records
   */
  function _getAvailableFields($record, $mode='add')
  {
    $res = array();
    $recs = $this->_getSelectableRecords($record, $mode);
    $selectedFields = $this->_getSelectedFieldsFromRecord($record);

    foreach ($recs as $rec)
    {
      if (!in_array($this->m_destInstance->primaryKey($rec),$selectedFields))
      {
        $res[] = $rec;
      }
    }
    return $res;
  }
}