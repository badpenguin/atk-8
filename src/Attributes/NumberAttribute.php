<?php namespace Sintattica\Atk\Attributes;
/**
 * This file is part of the ATK distribution on GitHub.
 * Detailed copyright and licensing information can be found
 * in the doc/COPYRIGHT and doc/LICENSE files which should be
 * included in the distribution.
 *
 * @package atk
 * @subpackage attributes
 *
 * @copyright (c)2000-2004 Ibuildings.nl BV
 * @license http://www.achievo.org/atk/licensing ATK Open Source License
 *
 * @version $Revision: 6353 $
 * $Id$
 */

/**
 * The atkNumberAttribute can be used for numeric values.
 *
 * @author Ivo Jansch <ivo@achievo.org>
 * @package atk
 * @subpackage attributes
 *
 */
class NumberAttribute extends Attribute
{
    // N.B. $m_size, $m_maxsize and $m_searchsize are relative to only the integral part of number (before decimal separator)

    var $m_decimals = null; // The number of decimals of the number.
    var $m_minvalue = false; // The minimum value of the number.
    var $m_maxvalue = false; // The maximum value of the number.
    var $m_use_thousands_separator = false; // use the thousands separator when formatting a number
    var $m_decimalseparator;
    var $m_thousandsseparator;

    // ids of separators in atk language file
    const SEPARATOR_DECIMAL = 'decimal_separator';
    const SEPARATOR_THOUSAND = 'thousands_separator';
    // default separator
    const DEFAULT_SEPARATOR = '.';

    /**
     * Constructor
     * @param string $name Name of the attribute
     * @param int $flags Flags for this attribute
     * @param mixed $size The size(s) of integral part (before seperator) for this attribute, also accepts an array (see setAttribSize for format)
     * @param int $decimals The number of decimals to use.
     *
     */
    function __construct($name, $flags = 0, $size = 0, $decimals = null)
    {
        parent::__construct($name, $flags | AF_NO_QUOTES, $size); // base class constructor
        $this->m_decimals = $decimals;

        $this->m_decimalseparator = Tools::atktext(self::SEPARATOR_DECIMAL, 'atk');
        $this->m_thousandsseparator = Tools::atktext(self::SEPARATOR_THOUSAND, 'atk');
    }

    /**
     * Returns the number of decimals.
     *
     * @return int decimals
     */
    public function getDecimals()
    {
        return (int)$this->m_decimals;
    }

    /**
     * Sets the number of decimals.
     *
     * @param int $decimals number of decimals
     */
    public function setDecimals($decimals)
    {
        $this->m_decimals = $decimals;
    }

    /**
     * Set the minimum and maximum value of the number. Violations of this range
     *
     * @param int $minvalue Minimum value of the number.
     * @param int $maxvalue Maximum value of the number.
     */
    public function setRange($minvalue, $maxvalue)
    {
        $this->m_minvalue = $minvalue;
        $this->m_maxvalue = $maxvalue;
    }

    /**
     * Retrieves the ORDER BY statement for this attribute's node.
     * Derived attributes may override this functionality to implement other
     * ordering statements using the given parameters.
     *
     * @param Array $extra A list of attribute names to add to the order by
     *                     statement
     * @param String $table The table name (if not given uses the owner node's table name)
     * @param String $direction Sorting direction (ASC or DESC)
     * @return String The ORDER BY statement for this attribute
     */
    function getOrderByStatement($extra = '', $table = '', $direction = 'ASC')
    {
        if (empty($table)) {
            $table = $this->m_ownerInstance->m_table;
        }

        // check for a schema name in $table
        if (strpos($table, '.') !== false) {
            $identifiers = explode('.', $table);

            $tableIdentifier = '';
            foreach ($identifiers as $identifier) {
                $tableIdentifier .= $this->getDb()->quoteIdentifier($identifier) . '.';
            }

            return $tableIdentifier . $this->getDb()->quoteIdentifier($this->fieldName()) . ($direction ? " {$direction}" : "");
        } else {
            return $this->getDb()->quoteIdentifier($table) . "." . $this->getDb()->quoteIdentifier($this->fieldName()) . ($direction ? " {$direction}" : "");
        }
    }

    /**
     * Returns a displayable string for this value, to be used in HTML pages.
     *
     * The regular Attribute uses PHP's nl2br() and htmlspecialchars()
     * methods to prepare a value for display, unless $mode is "cvs".
     *
     * @param array $record The record that holds the value for this attribute
     * @param String $mode The display mode ("view" for viewpages, or "list"
     *                     for displaying in recordlists, "edit" for
     *                     displaying in editscreens, "add" for displaying in
     *                     add screens. "csv" for csv files. Applications can
     *                     use additional modes.
     * @return String HTML String
     */
    function display($record, $mode = "")
    {
        if (isset($record[$this->fieldName()]) && $record[$this->fieldName()] !== "") {
            return $this->formatNumber($record[$this->fieldName()]);
        }
        return "";
    }

    /**
     * Replace decimal separator (from the language file "decimal_separator")
     * with the standard separator ('.') so, for instance,  99,95 would become
     * 99.95 when language is set to 'nl'.
     * @static
     * @deprecated
     * @param String $number The number that needs to be converted
     * @return String The converted number
     */
    function standardiseSeparator($number)
    {
        return str_replace(Tools::atktext("decimal_separator"), ".", $number);
    }

    /**
     * Returns a piece of html code for hiding this attribute in an HTML form,
     * while still posting its value. (<input type="hidden">)
     *
     * We have to format the value, so it matches the display value
     * Otherwise the value will be corrupted by removeSeparators
     *
     * @param array $record The record that holds the value for this attribute
     * @param String $fieldprefix The fieldprefix to put in front of the name
     *                            of any html form element for this attribute.
     * @return String A piece of htmlcode with hidden form elements that post
     *                this attribute's value without showing it.
     */
    function hide($record = "", $fieldprefix = "")
    {
        // the next if-statement is a workaround for derived attributes which do
        // not override the hide() method properly. This will not give them a
        // working hide() functionality but at least it will not give error messages.
        if (!is_array($record[$this->fieldName()])) {
            $id = $id = $this->getHtmlId($fieldprefix);
            $result = '<input type="hidden" id="' . $id . '" name="' . $fieldprefix . $this->formName() . '" value="' . htmlspecialchars($this->formatNumber($record[$this->fieldName()])) . '">';
            return $result;
        } else {
            Tools::atkdebug("Warning attribute " . $this->m_name . " has no proper hide method!");
        }
    }

    /**
     * convert a formatted number to a real number
     * @param String $number The number that needs to be converted
     * @param String $decimal_separator override decimal separator
     * @param String $thousands_separator override thousands separator
     * @return String The converted number
     */
    function removeSeparators($number, $decimal_separator = "", $thousands_separator = "")
    {
        if (empty($decimal_separator)) {
            $decimal_separator = $this->m_decimalseparator;
        }
        if (empty($thousands_separator)) {
            $thousands_separator = $this->m_thousandsseparator;
        }

        if ($decimal_separator == $thousands_separator) {
            Tools::atkwarning('invalid thousandsseparator. identical to the decimal_separator');
            $thousands_separator = '';
        }

        if (strstr($number, $decimal_separator) !== false) {
            // check invalid input
            if (substr_count($number, $decimal_separator) > 2) {
                return $number;
            }

            $number = str_replace($thousands_separator, '', $number);
            $number = str_replace($decimal_separator, self::DEFAULT_SEPARATOR, $number);

            if (substr_count($number, self::DEFAULT_SEPARATOR) > 1) {
                $parts = explode(self::DEFAULT_SEPARATOR, $number);
                $decimals = array_pop($parts);
                $number = implode('', $parts) . self::DEFAULT_SEPARATOR . $decimals;
            }
        } else {
            $number = str_replace($thousands_separator, '', $number);
        }

        return $number;
    }

    /**
     * Use the thousands separator when formatting a number
     *
     * @param bool $use_separator
     * @return bool
     */
    public function setUseThousandsSeparator($use_separator)
    {
        $this->m_use_thousands_separator = (bool)$use_separator;
    }

    /**
     * Returns true if we 're using the thousands separator
     * when formatting the number
     *
     * @return bool
     */
    public function getUseThousandsSeparator()
    {
        return $this->m_use_thousands_separator;
    }

    /**
     * Get the thousands separator
     *
     * @return String with the thousands separator
     */
    public function getThousandsSeparator()
    {
        return $this->m_thousandsseparator;
    }

    /**
     * Set the thousands separator
     *
     * @param string $separator The thousands separator
     */
    public function setThousandsSeparator($separator)
    {
        $this->m_thousandsseparator = $separator;
    }

    /**
     * Get the decimal separator
     *
     * @return String with the decimal separator
     */
    public function getDecimalSeparator()
    {
        return $this->m_decimalseparator;
    }

    /**
     * Set the decimal separator
     *
     * @param string $separator The decimal separator
     */
    public function setDecimalSeparator($separator)
    {
        $this->m_decimalseparator = $separator;
    }

    /**
     * Formats the number based on setting in the language file
     *
     * @param float $number number
     * @param string $decimalSeparator override decimal separator
     * @param string $thousandsSeparator override thousands separator
     *
     * @return string nicely formatted number
     */
    protected function formatNumber($number, $decimalSeparator = "", $thousandsSeparator = "", $mode = "")
    {
        $decimalSeparator = $decimalSeparator == null ? $this->m_decimalseparator : $decimalSeparator;
        $thousandsSeparator = $thousandsSeparator == null ? $this->m_thousandsseparator : $thousandsSeparator;
        // (never shows the thousands separator in add/edit mode)
        $thousandsSeparator = ($this->m_use_thousands_separator && !in_array($mode,
                array('add', 'edit'))) ? $thousandsSeparator : '';

        if ($decimalSeparator == $thousandsSeparator) {
            Tools::atkwarning('invalid thousandsseparator. identical to the decimal_separator');
            $thousandsSeparator = '';
        }

        // NOTE: we don't use number_format because this sometimes causes rounding issues
        //       if a float can not be properly represented (see http://nl.php.net/manual/en/function.number-format.php#93893)

        $tmp1 = abs(round((float)$number, $this->getDecimals()));
        $tmp1 .= $this->getDecimals() > 0 && strpos($tmp1, '.') === false ? '.' : '';
        $tmp1 .= str_repeat('0', max($this->getDecimals() - strlen(substr($tmp1, strpos($tmp1, '.') + 1)), 0));

        while (($tmp2 = preg_replace('/(?<!.)(\d+)(\d\d\d)/', '\1 \2', $tmp1)) != $tmp1) {
            $tmp1 = $tmp2;
        }

        $r = strtr($tmp1, array(' ' => $thousandsSeparator, '.' => $decimalSeparator));
        if ($number < 0) {
            $r = '-' . $r;
        }
        return $r;
    }

    /**
     * Replace standard decimal separator ('.') with the one from the language
     * file so, for instance, 99.95 would be converted to 99,95 when language
     * is set to 'nl'.
     * @static
     * @deprecated
     * @param String $number The number that needs to be converted
     * @return String The converted number
     */
    function translateSeparator($number)
    {
        return str_replace(".", Tools::atktext("decimal_separator"), $number);
    }

    /**
     * Validates if value is numeric
     * @param array $record Record that contains value to be validated.
     *                 Errors are saved in this record
     * @param string $mode can be either "add" or "update"
     * @return $record
     */
    function validate(&$record, $mode)
    {
        if (!is_numeric($record[$this->fieldName()]) && $record[$this->fieldName()] != "") {
            Tools::triggerError($record, $this->fieldName(), 'error_notnumeric');
        }
        if (($this->m_maxvalue !== false) && ($record[$this->fieldName()] > $this->m_maxvalue)) {
            Tools::triggerError($record, $this->fieldName(), 'above_maximum_value');
        }
        if (($this->m_minvalue !== false) && ($record[$this->fieldName()] < $this->m_minvalue)) {
            Tools::triggerError($record, $this->fieldName(), 'below_minimum_value');
        }
    }

    /**
     * Convert values from an HTML form posting to an internal value for
     * this attribute.
     *
     * If the user entered a number in his native language, he may have used
     * a different decimal separator, which we first convert to the '.'
     * standard separator (ATK uses the regular dot notation internally)
     *
     * @param array $postvars The array with html posted values ($_POST, for
     *                        example) that holds this attribute's value.
     * @return String The internal value
     */
    function fetchValue($postvars)
    {
        if ($this->isPosted($postvars)) {
            return $this->removeSeparators(Tools::atkArrayNvl($postvars, $this->fieldName(), ""));
        }
    }

    /**
     * Converts the internal attribute value to one that is understood by the
     * database.
     *
     * For the regular Attribute, this means escaping things like
     * quotes and slashes. Derived attributes may reimplement this for their
     * own conversion.
     * This is the exact opposite of the db2value method.
     *
     * @param array $rec The record that holds this attribute's value.
     * @return String The database compatible value
     */
    function value2db($rec)
    {
        if ((!isset($rec[$this->fieldName()]) || strlen($rec[$this->fieldName()]) == 0) && !$this->hasFlag(AF_OBLIGATORY)) {
            return null;
        }
        if ($this->getDecimals() > 0) {
            return round((float)$rec[$this->fieldName()], $this->getDecimals());
        } else {
            return isset($rec[$this->fieldName()]) ? $rec[$this->fieldName()] : null;
        }
    }

    /**
     * Retrieve the list of searchmodes supported by the attribute.
     *
     * Note that not all modes may be supported by the database driver.
     * Compare this list to the one returned by the databasedriver, to
     * determine which searchmodes may be used.
     *
     * @return array List of supported searchmodes
     */
    function getSearchModes()
    {
        // exact match and substring search should be supported by any database.
        // (the LIKE function is ANSI standard SQL, and both substring and wildcard
        // searches can be implemented using LIKE)
        // Possible values
        //"regexp","exact","substring", "wildcard","greaterthan","greaterthanequal","lessthan","lessthanequal"
        return array("exact", "between", "greaterthan", "greaterthanequal", "lessthan", "lessthanequal");
    }

    /**
     * Return the database field type of the attribute.
     *
     * Note that the type returned is a 'generic' type. Each database
     * vendor might have his own types, therefor, the type should be
     * converted to a database specific type using $db->fieldType().
     *
     * @return String The 'generic' type of the database field for this
     *                attribute.
     */
    function dbFieldType()
    {
        return ($this->getDecimals() > 0 ? "decimal" : "number");
    }

    /**
     * Return the size of the field in the database.
     *
     * If 0 is returned, the size is unknown. In this case, the
     * return value should not be used to create table columns.
     *
     * Ofcourse, the size does not make sense for every field type.
     * So only interpret the result if a size has meaning for
     * the field type of this attribute. (For example, if the
     * database field is of type 'date', the size has no meaning)
     *
     * Note that derived attributes might set a dot separated size,
     * for example to store decimal numbers. The number after the dot
     * should be interpreted as the number of decimals.
     *
     * @return int The database field size
     */
    function dbFieldSize()
    {
        return $this->m_maxsize . ($this->getDecimals() > 0 ? "," . $this->getDecimals() : "");
    }

    /**
     * Apply database metadata for setting the attribute size.
     */
    function fetchMeta($metadata)
    {
        // N.B. size, maxsize and searchsize are relative to only the integral part of number (before decimal separator)

        $attribname = strtolower($this->fieldName());

        // maxsize and decimals
        // (if the value is explicitly set, but the database simply can't handle it, we use the smallest one)
        if (isset($metadata[$attribname])) {

            if (strpos($metadata[$attribname]['len'], ',') !== false) {
                list($metaSize, $metaDecimals) = explode(',', $metadata[$attribname]['len']);
                $metaSize = (int)$metaSize;
                $metaDecimals = (int)$metaDecimals;

                // decimals
                if ($this->m_decimals === null) {
                    $this->m_decimals = $metaDecimals;
                } else {
                    $this->m_decimals = min($this->m_decimals, $metaDecimals);
                }
            } else {
                $metaSize = $metadata[$attribname]['len'];
            }

            // maxsize
            if ($this->m_maxsize > 0) {
                $this->m_maxsize = min($this->m_maxsize, $metaSize);
            } else {
                $this->m_maxsize = $metaSize;
            }
        }

        // size
        if (!$this->m_size) {
            $this->m_size = min($this->m_maxsize, $this->maxInputSize());
        }

        // searchsize
        if (!$this->m_searchsize) {
            $this->m_searchsize = min($this->m_maxsize, $this->maxSearchInputSize());
        }
    }

    /**
     * Returns a piece of html code that can be used in a form to edit this
     * attribute's value.
     * @param array $record Array with values
     * @param string $fieldprefix The attribute must use this to prefix its form elements (used for
     *                     embedded forms)
     * @param string $mode The mode we're in ('add' or 'edit')
     * @return Piece of htmlcode
     */
    function edit($record = "", $fieldprefix = "", $mode = "")
    {
        $id = $this->getHtmlId($fieldprefix);
        if (count($this->m_onchangecode)) {
            $onchange = 'onChange="' . $id . '_onChange(this);"';
            $this->_renderChangeHandler($fieldprefix);
        } else {
            $onchange = '';
        }

        $this->registerJavaScriptObservers($id);

        $size = $this->m_size;
        $maxsize = $this->m_maxsize;
        if ($this->getDecimals() > 0) {
            $size += ($this->getDecimals() + 1);
            $maxsize += ($this->getDecimals() + 1); // make room for the number of decimals
            // TODO we should also consider the sign symbol (for signed type)
        }

        $value = "";
        if (isset($record[$this->fieldName()]) && strlen($record[$this->fieldName()]) > 0) {
            $value = $this->formatNumber($record[$this->fieldName()], '', '', $mode);
        }

        $id = $fieldprefix . $this->fieldName();
        $this->registerKeyListener($id, KB_CTRLCURSOR | KB_UPDOWN);
        $result = '<input type="text" id="' . $id . '" ' . $this->getCSSClassAttribute(array('form-control')) . ' name="' . $id . '" value="' . $value . '"' .
            ($size > 0 ? ' size="' . $size . '"' : '') .
            ($maxsize > 0 ? ' maxlength="' . $maxsize . '"' : '') . ' ' . $onchange . ' />';

        return $result;
    }

    /**
     * Returns a piece of html code that can be used to search for an
     * attribute's value.
     *
     * @param array $record Array with values
     * @param boolean $extended if set to false, a simple search input is
     *                          returned for use in the searchbar of the
     *                          recordlist. If set to true, a more extended
     *                          search may be returned for the 'extended'
     *                          search page. The Attribute does not
     *                          make a difference for $extended is true, but
     *                          derived attributes may reimplement this.
     * @param string $fieldprefix The fieldprefix of this attribute's HTML element.
     *
     * @return String A piece of html-code
     */
    function search($record = "", $extended = false, $fieldprefix = "")
    {
        $value = "";
        if (isset($record[$this->fieldName()])) {
            $value = $record[$this->fieldName()];
        }

        $searchsize = $this->m_searchsize;
        if ($this->m_decimals > 0) { // (don't use getDecimals to avoid fatal error when called from atkExpressionAttribute)
            $searchsize += ($this->m_decimals + 1); // make room for the number of decimals
            // TODO we should also consider the sign symbol (for signed type)
        }

        if (!$extended) {
            if (is_array($value)) { // values entered in the extended search
                // TODO we would need to know the searchmode for better handling...
                if ($value["from"] !== "" && $value["to"] !== "") {
                    $value = $value["from"] . "/" . $value["to"];
                } else {
                    if ($value["from"] !== "") {
                        $value = $value["from"];
                    } else {
                        if ($value["to"] !== "") {
                            $value = $value["to"];
                        } else {
                            $value = "";
                        }
                    }
                }
            }

            $id = $this->getSearchFieldName($fieldprefix);

            $this->registerKeyListener($id, KB_CTRLCURSOR | KB_UPDOWN);
            $result = '<input type="text" id="' . $id . '" class="form-control ' . get_class($this) . '" name="' . $id . '" value="' . htmlentities($value) . '"' .
                ($searchsize > 0 ? ' size="' . $searchsize . '"' : '') . '>';

        } else {
            $id = $this->getSearchFieldName($fieldprefix) . '[from]';
            $this->registerKeyListener($id, KB_CTRLCURSOR | KB_UPDOWN);

            if (is_array($value)) {
                $valueFrom = $value['from'];
                $valueTo = $value['to'];
            } else {
                $valueFrom = $valueTo = $value;
            }

            $result = '<div class="form-inline">';
            $result .= '<input type="text" id="' . $id . '" class="form-control ' . get_class($this) . '" name="' . $id . '" value="' . htmlentities($valueFrom) . '"' .
                ($searchsize > 0 ? ' size="' . $searchsize . '"' : '') . '>';
            $id = $this->getSearchFieldName($fieldprefix) . '[to]';
            $this->registerKeyListener($id, KB_CTRLCURSOR | KB_UPDOWN);
            $result .= " (" . Tools::atktext("until") . ' <input type="text" id="' . $id . '" class="form-control ' . get_class($this) . '" name="' . $id . '" value="' . htmlentities($valueTo) . '"' .
                ($searchsize > 0 ? ' size="' . $searchsize . '"' : '') . '>)';
            $result .= '</div>';
        }

        return $result;
    }

    /**
     * Process the search value
     *
     * @param string $value The search value
     * @param string $searchmode The searchmode to use. This can be any one
     *                              of the supported modes, as returned by this
     *                              attribute's getSearchModes() method.
     * @return string with the processed search value
     */
    function processSearchValue($value, &$searchmode)
    {
        if (!is_array($value)) {
            // quicksearch
            $value = trim($value);
            if (strpos($value, '/') !== false) { // from/to searches
                list($from, $to) = explode('/', $value);
                $from = self::removeSeparators(trim($from));
                $to = self::removeSeparators(trim($to));
                $from = is_numeric($from) ? (float)$from : '';
                $to = is_numeric($to) ? (float)$to : '';
                $processed = array('from' => $from, 'to' => $to);
                $searchmode = 'between';
            } else { // single value
                $value = self::removeSeparators($value);
                if (is_numeric($value)) {
                    $processed['from'] = (float)$value;
                } else {
                    $processed = array();
                }
            }
        } else {
            // assumes array('from'=><intval>, 'to'=><intval>)
            foreach ($value as $key => $search) {
                $processed[$key] = self::removeSeparators($search);
            }
        }

        return $processed;
    }

    /**
     * Get the between search condition
     *
     * @param Query $query The query object where the search condition should be placed on
     * @param string $fieldname The name of the field in the database
     * @param string $value The processed search value
     * @return query where clause for searching
     */
    function getBetweenCondition(&$query, $fieldname, $value)
    {
        if ($value["from"] !== "" && $value["to"] !== "") {
            if ($value["from"] > $value["to"]) {
                // User entered fields in wrong order. Let's fix that.
                $tmp = $value["from"];
                $value["from"] = $value["to"];
                $value["to"] = $tmp;
            }
            return $query->betweenCondition($fieldname, $this->escapeSQL($value["from"]),
                $this->escapeSQL($value["to"]));
        } else {
            if ($value["from"] !== "" && $value["to"] === "") {
                return $query->greaterthanequalCondition($fieldname, $value["from"]);
            } else {
                if ($value["from"] === "" && $value["to"] !== "") {
                    return $query->lessthanequalCondition($fieldname, $value["to"]);
                }
            }
        }

        return false;
    }

    /**
     * Creates a searchcondition for the field,
     * was once part of searchCondition, however,
     * searchcondition() also immediately adds the search condition.
     *
     * @param Query $query The query object where the search condition should be placed on
     * @param String $table The name of the table in which this attribute
     *                              is stored
     * @param mixed $value The value the user has entered in the searchbox
     * @param String $searchmode The searchmode to use. This can be any one
     *                              of the supported modes, as returned by this
     *                              attribute's getSearchModes() method.
     * @return String The searchcondition to use.
     */
    function getSearchCondition(&$query, $table, $value, $searchmode)
    {
        $value = $this->processSearchValue($value, $searchmode);

        if ($searchmode != 'between') {
            if ($value['from'] !== '') {
                $value = $value['from'];
            } else {
                if ($value['to'] !== '') {
                    $value = $value['to'];
                } else {
                    return false;
                }
            }
            return parent::getSearchCondition($query, $table, $value, $searchmode);
        } else {
            $fieldname = $table . "." . $this->fieldName();
            return $this->getBetweenCondition($query, $fieldname, $value);
        }
    }
}