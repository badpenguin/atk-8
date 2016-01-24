<?php namespace Sintattica\Atk\Relations;

use Sintattica\Atk\Core\Config;
use Sintattica\Atk\Core\Tools;
use Sintattica\Atk\Db\Db;
use Sintattica\Atk\Session\SessionManager;
use Sintattica\Atk\Core\Node;

/**
 * Many-to-many select relation.
 *
 * The relation shows allows you to add one record at a time to a many-to-many
 * relation using auto-completion or a select page. If a position attribute has
 * been set (setPositionAttribute) the order of the items can be changed using
 * simple drag & drop.
 *
 *
 * @author Peter C. Verhage <peter@achievo.org>
 * @package atk
 * @subpackage relations
 */
class ManyToManySelectRelation extends ManyToManyRelation
{

    const AF_MANYTOMANYSELECT_DETAILEDIT = self::AF_SPECIFIC_1;
    const AF_MANYTOMANYSELECT_DETAILADD = self::AF_SPECIFIC_2;
    const AF_MANYTOMANYSELECT_NO_AUTOCOMPLETE = self::AF_SPECIFIC_3;


    const SEARCH_MODE_EXACT = ManyToOneRelation::SEARCH_MODE_EXACT;
    const SEARCH_MODE_STARTSWITH = ManyToOneRelation::SEARCH_MODE_STARTSWITH;
    const SEARCH_MODE_CONTAINS = ManyToOneRelation::SEARCH_MODE_CONTAINS;

    /**
     * The many-to-one relation.
     *
     * @var ManyToOneRelation
     */
    private $m_manyToOneRelation = null;

    /**
     * The name of the attribute/column where the position of the item in
     * the set should be stored.
     *
     * @var string
     */
    private $m_positionAttribute;

    /**
     * The html to be output next to a positional attribute label
     *
     * @var string
     */
    private $m_positionAttributeHtmlModifier;

    /**
     * Constructs a new many-to-many select relation.
     *
     * @param string $name The name of the relation
     * @param string $link The full name of the node that is used as
     *                            intermediairy node. The intermediairy node is
     *                            assumed to have 2 attributes that are named
     *                            after the nodes at both ends of the relation.
     *                            For example, if node 'project' has a M2M relation
     *                            with 'activity', then the intermediairy node
     *                            'project_activity' is assumed to have an attribute
     *                            named 'project' and one that is named 'activity'.
     *                            You can set your own keys by calling setLocalKey()
     *                            and setRemoteKey()
     * @param string $destination The full name of the node that is the other
     *                            end of the relation.
     * @param int $flags Flags for the relation.
     */
    public function __construct($name, $link, $destination, $flags = 0)
    {
        parent::__construct($name, $link, $destination, $flags);

        $relation = new ManyToOneRelation($this->fieldName() . '_m2msr_add', $this->m_destination,
            ManyToOneRelation::AF_MANYTOONE_AUTOCOMPLETE | self::AF_HIDE);
        $relation->setDisabledModes(self::DISABLED_VIEW | self::DISABLED_EDIT);
        $relation->setLoadType(self::NOLOAD);
        $relation->setStorageType(self::NOSTORE);
        $this->m_manyToOneRelation = $relation;
    }

    /**
     * Initialize.
     */
    public function init()
    {
        $this->getOwnerInstance()->add($this->getManyToOneRelation());
    }

    /**
     * Return the many-to-one relation we will use for the selection
     * of new records etc.
     *
     * @return ManyToOneRelation
     */
    protected function getManyToOneRelation()
    {
        return $this->m_manyToOneRelation;
    }

    /**
     * Create the instance of the destination and copy the destination to
     * the the many to one relation
     *
     * If succesful, the instance is stored in the m_destInstance member variable.
     *
     * @return boolean true if succesful, false if something went wrong.
     */
    public function createDestination()
    {
        $result = parent::createDestination();
        $this->getManyToOneRelation()->m_destInstance = $this->m_destInstance;
        return $result;
    }

    /**
     * Order selected records in the same way as the selected keys. We only do
     * this if the position attribute has been set.
     *
     * @param array $selectedRecords selected records
     * @param array $selectedKey selected keys
     */
    private function orderSelectedRecords(&$selectedRecords, $selectedKeys)
    {
        $orderedRecords = array();

        foreach ($selectedKeys as $key) {
            foreach ($selectedRecords as $record) {
                if ($key == $this->getDestination()->primaryKey($record)) {
                    $orderedRecords[] = $record;
                }
            }
        }

        $selectedRecords = $orderedRecords;
    }

    /**
     * Return a piece of html code to edit the attribute.
     *
     * @param array $record The record that holds the value for this attribute.
     * @param String $fieldprefix The fieldprefix to put in front of the name
     *                            of any html form element for this attribute.
     * @param String $mode The mode we're in ('add' or 'edit')
     *
     * @return string piece of html code
     */
    public function edit($record, $fieldprefix = "", $mode = "")
    {
        if ($this->hasFlag(self::AF_MANYTOMANYSELECT_NO_AUTOCOMPLETE)) {
            $this->getManyToOneRelation()->removeFlag(ManyToOneRelation::AF_MANYTOONE_AUTOCOMPLETE);
        }

        $this->createDestination();

        $this->createLink();


        $this->getOwnerInstance()->getPage()->register_script(Config::getGlobal('atkroot') . 'atk/javascript/class.' . strtolower(__CLASS__) . '.js');
        $this->getOwnerInstance()->addStyle('atkmanytomanyselectrelation.css');

        $id = $this->getHtmlId($fieldprefix);
        $selectId = "{$id}_selection";

        $selectedKeys = $this->getSelectedKeys($record, $id);

        $selectedRecords = array();
        if (count($selectedKeys) > 0) {
            $selector = '(' . implode(') OR (', $selectedKeys) . ')';
            $selectedRecords = $this->getDestination()->select($selector)->includes($this->getDestination()->descriptorFields());
            $this->orderSelectedRecords($selectedRecords, $selectedKeys);
        }

        $result = '<input type="hidden" name="' . $this->getHtmlId($fieldprefix) . '" value="" />' . // Post an empty value if none selected (instead of not posting anything)
            '<div class="atkmanytomanyselectrelation">';

        if (($this->hasFlag(self::AF_MANYTOMANYSELECT_DETAILADD)) && ($this->m_destInstance->allowed("add"))) {
            $addLink = ' ' . $this->getAddActionLink($record, $fieldprefix);
        }

        $selField = '';
        foreach ($selectedRecords as $selectedRecord) {
            $selField .= $this->renderSelectedRecord($selectedRecord, $fieldprefix);
        }
        $addField = $this->renderAdditionField($record, $fieldprefix, $mode);

        if ($selField || $addField) {
            $result .= '<ul id="' . $selectId . '" class="atkmanytomanyselectrelation-selection" style="width:100% !important">';
            $result .= $selField;
            if ($addField) {
                $result .= '<li class="atkmanytomanyselectrelation-addition">' . $addField . '</li>';
            }
            $result .= '</ul>';
        } else {
            if (!$addLink) {
                $result .= '<i>' . $this->text('none') . '</i>';
            }
        }

        if ($addLink) {
            $result .= $addLink;
        }

        $result .= '</div>';

        if ($this->hasPositionAttribute()) {
            $this->getOwnerInstance()->getPage()->register_loadscript("ATK.ManyToManySelectRelation.makeItemsSortable('{$selectId}');");
        }

        return $result;
    }

    /**
     * Return the selected keys for a given record
     *
     * @param array $record The record that holds the value for this attribute.
     * @param String $id is the html id of the relation
     * @param int $uniqueFilter is the type of array_unique filter to use on
     *            the results. Use boolean false to dissable
     *
     * @return array of selected keys in the order they were submitted
     */
    public function getSelectedKeys($record, $id, $enforceUnique = true)
    {
        // Get Existing selected records
        $selectedKeys = $this->getSelectedRecords($record);

        // Get records added this time
        if (isset($record[$this->getManyToOneRelation()->fieldName()]) && is_array($record[$this->getManyToOneRelation()->fieldName()])) {
            $selectedKeys[] = $this->getDestination()->primaryKey($record[$this->getManyToOneRelation()->fieldName()]);
        }

        // Get New Selection records
        if (isset($this->getOwnerInstance()->m_postvars[$id . '_newsel'])) {
            $selectedKeys[] = $this->getOwnerInstance()->m_postvars[$id . '_newsel'];
        }

        // Ensure we're only adding an item once
        if ($enforceUnique && is_array($selectedKeys) && count($selectedKeys)) {
            $selectedKeys = array_unique($selectedKeys);
        }

        return $selectedKeys;
    }

    /**
     * Load function.
     *
     * @param Db $db database instance.
     * @param array $record record
     * @param string $mode load mode
     */
    public function load($db, $record, $mode)
    {
        if (!$this->hasPositionAttribute()) {
            return parent::load($db, $record, $mode);
        }

        $this->createLink();
        $where = $this->_getLoadWhereClause($record);
        $link = $this->getLink();
        return $link->select()->where($where)->orderBy($link->getTable() . '.' . $this->getPositionAttribute())->getAllRows();
    }

    /**
     * Perform the create action on a record that is new
     *
     * @param array $selectedKey the selected keys
     * @param array $selectedRecord the selected record
     * @param array $ownerRecord the owner record
     * @param int $index the index of the item in the set
     * @access protected
     * @return array the newly created record
     */
    protected function _createRecord($selectedKey, $selectedRecord, $ownerRecord, $index)
    {
        $record = parent::_createRecord($selectedKey, $selectedRecord, $ownerRecord, $index);

        if ($this->hasPositionAttribute()) {
            $record[$this->getPositionAttribute()] = $index + 1;
        }

        return $record;
    }

    /**
     * Perform the update action on a record that's been changed
     *
     * @param array $record the record that has been changed
     * @param int $index the index of the item in the set
     * @access protected
     * @return boolean true if the update was performed successfuly, false if there were issues
     */
    protected function _updateRecord($record, $index)
    {
        // If the parent class didn't manage to update the record
        // then don't attempt to perform this update
        if (!parent::_updateRecord($record, $index)) {
            return false;
        }

        if ($this->hasPositionAttribute()) {
            $record[$this->getPositionAttribute()] = $index + 1;
            if (!$this->getLink()->updateDb($record, true, '', array($this->getPositionAttribute()))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Render selected record.
     *
     * @param array $record selected record
     * @param string $fieldprefix field prefix
     */
    protected function renderSelectedRecord($record, $fieldprefix)
    {
        $name = $this->getHtmlId($fieldprefix) . '[][' . $this->getRemoteKey() . ']';
        $key = $record[$this->getDestination()->primaryKeyField()];

        // Get the descriptor and ensure it's presentible
        $descriptor = nl2br(
            htmlentities(
                $this->getdestination()->descriptor($record)
            )
        );

        // Build the record
        $result = '
      <li class="atkmanytomanyselectrelation-selected">
      <input type="hidden" name="' . $name . '" value="' . htmlentities($key) . '"/>
      <span>' . $descriptor . '</span>
      ' . $this->renderSelectedRecordActions($record) . '
      </li>
      ';

        return $result;
    }

    /*
     * Renders the action links for a given record
     *
     * @param array $record is the selected record
     * @return string the actions in their html link form
     */

    protected function renderSelectedRecordActions($record)
    {
        $actions = array();

        if ($this->hasFlag(self::AF_MANYTOMANY_DETAILVIEW) && !$this->hasFlag(self::AF_MANYTOMANYSELECT_DETAILEDIT) && $this->getDestination()->allowed("view")) {
            $actions[] = 'view';
        }

        if ($this->hasFlag(self::AF_MANYTOMANYSELECT_DETAILEDIT) && $this->getDestination()->allowed('edit', $record)) {
            $actions[] = 'edit';
        }

        if (!$this->getLink()->hasFlag(Node::NF_NO_DELETE)) {
            $actions[] = 'delete';
        }

        $this->recordActions($record, $actions);

        // Call the renderButton action for those actions
        $actionLinks = array();
        foreach ($actions as $action) {
            $actionLink = $this->getActionLink($action, $record);
            if ($actionLink != null) {
                $actionLinks[] = $actionLink;
            }
        }

        $htmlActionLinks = '';
        if (count($actionLink)) {
            $htmlActionLinks = '&nbsp;' . implode(' ', $actionLinks);
        }
        return $htmlActionLinks;
    }

    /**
     * This method returns the HTML for the link of a certain action
     *
     * @param string $action
     * @param array $record
     * @return string
     */
    protected function getActionLink($action, $record)
    {
        $actionMethod = "get{$action}ActionLink";

        if (method_exists($this, $actionMethod)) {
            return $this->$actionMethod($record);
        } else {
            Tools::atkwarning('Missing ' . $actionMethod . ' method on manytomanyselectrelation. ');
        }
    }

    protected function getAddActionLink($record, $fieldprefix, $params = array())
    {
        $filter = $this->parseFilter($this->getManyToOneRelation()->m_destinationFilter, $record);
        $params = array_merge($params,
            array('atkfilter' => $filter, "atkpkret" => $this->getHtmlId($fieldprefix) . "_newsel"));
        $link = SessionManager::href(Tools::dispatch_url($this->m_destination, "add", $params), $this->getAddLabel(),
            SessionManager::SESSION_NESTED, true, 'class="atkmanytomanyselectrelation-link"');
        return $link;
    }

    /**
     * The default edit link
     *
     * @param array $record
     * @return string
     */
    protected function getEditActionLink($record)
    {
        return SessionManager::href(Tools::dispatch_url($this->getdestination()->atknodetype(), 'edit',
            array('atkselector' => $this->getdestination()->primarykey($record))), $this->text('edit'), SessionManager::SESSION_NESTED,
            true, 'class="atkmanytomanyselectrelation-link"');
    }

    /**
     * The default view link
     *
     * @param array $record
     * @return string
     */
    protected function getViewActionLink($record)
    {
        return SessionManager::href(Tools::dispatch_url($this->getdestination()->atknodetype(), 'view',
            array('atkselector' => $this->getdestination()->primarykey($record))), $this->text('view'), SessionManager::SESSION_NESTED,
            true, 'class="atkmanytomanyselectrelation-link"');
    }

    /**
     * The default delete link
     *
     * @param array $record
     * @return string
     */
    protected function getDeleteActionLink($record)
    {
        return '<a href="javascript:void(0)" class="atkmanytomanyselectrelation-link" onclick="ATK.ManyToManySelectRelation.deleteItem(this); return false;">' . $this->text('remove') . '</a>';
    }

    /**
     * Function that is called for each record in a recordlist, to determine
     * what actions may be performed on the record.
     *
     * @param array $record The record for which the actions need to be
     *                      determined.
     * @param array &$actions Reference to an array with the already defined
     *                  actions.
     */
    function recordActions($record, &$actions)
    {

    }

    /**
     * Render addition field.
     *
     * @param string $fieldprefix field prefix
     * @param string $mode
     */
    protected function renderAdditionField($record, $fieldprefix, $mode)
    {
        if ($this->getLink()->hasFlag(Node::NF_NO_ADD)) {
            return '';
        }

        $url = Tools::partial_url($this->getOwnerInstance()->atkNodeType(), $mode,
            'attribute.' . $this->fieldName() . '.selectedrecord', array('fieldprefix' => $fieldprefix));

        $relation = $this->getManyToOneRelation();

        $hasPositionAttribute = $this->hasPositionAttribute() ? 'true' : 'false';
        $relation->addOnChangeHandler("ATK.ManyToManySelectRelation.add(el, '{$url}', {$hasPositionAttribute});");

        $relation->setNoneLabel($this->text('select_none_obligatory'));
        $result = $relation->edit($record, $fieldprefix, $mode);
        if ($result == $this->text('select_none_obligatory')) {
            $result = '';
        }

        return $result;
    }

    /**
     * Partial selected record.
     */
    public function partial_selectedrecord()
    {
        $this->createDestination();
        $this->createLink();

        $fieldprefix = $this->getOwnerInstance()->m_postvars['fieldprefix'];
        $selector = $this->getOwnerInstance()->m_postvars['selector'];

        if (empty($selector)) {
            return '';
        }

        $record = $this->getDestination()->select($selector)->includes($this->getDestination()->descriptorFields())->getFirstRow();

        return $this->renderSelectedRecord($record, $fieldprefix);
    }

    /**
     * Set the searchfields for the autocompletion. By default the
     * descriptor fields are used.
     *
     * @param array $searchFields
     */
    public function setAutoCompleteSearchFields($searchFields)
    {
        $this->getManyToOneRelation()->setAutoCompleteSearchFields($searchFields);
    }

    /**
     * Set the searchmode for the autocompletion:
     * exact, startswith(default) or contains.
     *
     * @param array $mode
     */
    public function setAutoCompleteSearchMode($mode)
    {
        $this->getManyToOneRelation()->setAutoCompleteSearchMode($mode);
    }

    /**
     * Set the case-sensitivity for the autocompletion search (true or false).
     *
     * @param array $caseSensitive
     */
    public function setAutoCompleteCaseSensitive($caseSensitive)
    {
        $this->getManyToOneRelation()->setAutoCaseSensitive($caseSensitive);
    }

    /**
     * Sets the minimum number of characters before auto-completion kicks in.
     *
     * @param int $chars
     */
    public function setAutoCompleteMinChars($chars)
    {
        $this->getManyToOneRelation()->setAutoCompleteMinChars($chars);
    }

    /**
     * Adds a filter value to the destination filter.
     *
     * @param string $filter
     */
    public function addDestinationFilter($filter)
    {
        return $this->getManyToOneRelation()->addDestinationFilter($filter);
    }

    /**
     * Sets the destination filter.
     *
     * @param string $filter
     */
    public function setDestinationFilter($filter)
    {
        return $this->getManyToOneRelation()->setDestinationFilter($filter);
    }

    /**
     * Set the positional attribute/column of the many to many join. It is the column
     * in the join table that denotes the position of the item in the set.
     *
     * @param string $attr the position attribute/column name of the join
     * @param string $htmlIdentifier is the html string to add to the end of the label.
     *        Defaults to an up down image.
     * @return void
     */
    public function setPositionAttribute($attr, $htmlIdentifier = null)
    {
        $this->m_positionAttribute = $attr;
        $this->m_positionAttributeHtmlModifier = $htmlIdentifier;
    }

    /**
     * Get the positional attribute of the many to many join. It is the column
     * in the join table that denotes the position of the item in the set.
     *
     * @access public
     * @return string the position column name of the join
     */
    public function getPositionAttribute()
    {
        return $this->m_positionAttribute;
    }

    /**
     * Check if positon attribute is set
     *
     * @return boolean true if the position attribute has been set
     */
    public function hasPositionAttribute()
    {
        return $this->getPositionAttribute() != null;
    }

    /**
     * Get the HTML label of the attribute.
     *
     * The difference with the label() method is that the label method always
     * returns the HTML label, while the getLabel() method is 'smart', by
     * taking the self::AF_NOLABEL and self::AF_BLANKLABEL flags into account.
     *
     * @param array $record The record holding the value for this attribute.
     * @param string $mode The mode ("add", "edit" or "view")
     * @return String The HTML compatible label for this attribute, or an
     *                empty string if the label should be blank, or NULL if no
     *                label at all should be displayed.
     */
    function getLabel($record = array(), $mode = '')
    {
        $additional = '';
        if ($this->hasPositionAttribute()) {
            if (is_null($this->m_positionAttributeHtmlModifier)) {
                $additional = ' <img src="' . Config::getGlobal('atkroot') . 'atk/images/up-down.gif"
          width="10" height="10" 
          alt="Sortable Ordering" 
          title="Sortable Ordering" />';
            } else {
                $additional = $this->m_positionAttributeHtmlModifier;
            }
        }

        return parent::getLabel($record, $mode) . $additional;
    }

}