<?PHP
/**
 * This file is part of the ATK distribution on GitHub.
 * Detailed copyright and licensing information can be found
 * in the doc/COPYRIGHT and doc/LICENSE files which should be
 * included in the distribution.
 *
 * @package atk
 * @subpackage menu
 *
 * @copyright (c)2000-2004 Ibuildings.nl BV
 * @license http://www.achievo.org/atk/licensing ATK Open Source License
 *
 * @version $Revision: 6320 $
 * $Id$
 */

/**
 * Implementation of the plaintext menu.
 *
 * @author Ber Dohmen <ber@ibuildings.nl>
 * @author Sandy Pleyte <sandy@ibuildings.nl>
 * @package atk
 * @subpackage menu
 */
class atkPlainMenu extends atkmenuinterface
{
    var $m_height;

    /**
     * Constructor
     *
     * @return atkPlainMenu
     */
    function atkPlainMenu()
    {
        $this->m_height = "50";
    }

    /**
     * Render the menu
     * @return String HTML fragment containing the menu.
     */
    function render()
    {
        $page = &atkTools::atkinstance("atk.ui.atkpage");
        $theme = &atkTools::atkinstance("atk.ui.atktheme");
        $page->addContent($this->getMenu());

        return $page->render("Menu", true);
    }

    /**
     * Get the menu
     *
     * @return string The menu
     */
    function getMenu()
    {
        global $ATK_VARS, $g_menu, $g_menu_parent;
        $atkmenutop = atkTools::atkArrayNvl($ATK_VARS, "atkmenutop", "main");
        $theme = &atkTools::atkinstance('atk.ui.atktheme');
        $page = &atkTools::atkinstance('atk.atkpage');

        $menu = $this->getHeader($atkmenutop);
        if (is_array($g_menu[$atkmenutop])) {
            usort($g_menu[$atkmenutop], array("atkplainmenu", "menu_cmp"));
            $menuitems = array();
            for ($i = 0; $i < count($g_menu[$atkmenutop]); $i++) {
                if ($i == count($g_menu[$atkmenutop]) - 1) {
                    $delimiter = "";
                } else {
                    $delimiter = atkConfig::getGlobal("menu_delimiter");
                }
                $name = $g_menu[$atkmenutop][$i]["name"];
                $menuitems[$i]["name"] = $name;
                $url = $g_menu[$atkmenutop][$i]["url"];
                $enable = $this->isEnabled($g_menu[$atkmenutop][$i]);
                $modname = $g_menu[$atkmenutop][$i]["module"];

                $menuitems[$i]["enable"] = $enable;

                /* delimiter ? */
                if ($name == "-")
                    $menu .= $delimiter;

                /* submenu ? */
                else if (empty($url) && $enable) {
                    $url = $theme->getAttribute('menufile', atkConfig::getGlobal("menufile", 'menu.php')) . '?atkmenutop=' . $name;
                    $menu .= atkTools::href($url, $this->getMenuTranslation($name, $modname), SESSION_DEFAULT) . $delimiter;
                } else if (empty($url) && !$enable) {
                    //$menu .=text("menu_$name").$config_menu_delimiter;
                }
                /* normal menu item */ else if ($enable)
                    $menu .= atkTools::href($url, $this->getMenuTranslation($name, $modname), SESSION_NEW, false, $theme->getAttribute('menu_params', atkConfig::getGlobal('menu_params', 'target="main"'))) . $delimiter;
                else {
                    //$menu .= text("menu_$name").$config_menu_delimiter;
                }
                $menuitems[$i]["url"] = atkTools::session_url($url);
            }
        }
        /* previous */
        if ($atkmenutop != "main") {
            $parent = $g_menu_parent[$atkmenutop];
            $menu .= atkConfig::getGlobal("menu_delimiter");
            $menu .= atkTools::href($theme->getAttribute('menufile', atkConfig::getGlobal("menufile", 'menu.php')) . '?atkmenutop=' . $parent, atkTools::atktext("back_to", "atk") . ' ' . $this->getMenuTranslation($parent, $modname), SESSION_DEFAULT) . $delimiter;
        }
        $menu.=$this->getFooter($atkmenutop);
        $page->register_style($theme->stylePath("style.css"));
        $page->register_script(atkConfig::getGlobal("atkroot") . "atk/javascript/menuload.js");
        $ui = &atkTools::atkinstance("atk.ui.atkui");

        return $ui->renderBox(array("title" => $this->getMenuTranslation($atkmenutop, $modname),
                "content" => $menu,
                "menuitems" => $menuitems), "menu");
    }

    /**
     * Compare two menuitems
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    function menu_cmp($a, $b)
    {
        if ($a["order"] == $b["order"])
            return 0;
        return ($a["order"] < $b["order"]) ? -1 : 1;
    }

    /**
     * Get the height for this menu
     *
     * @return int The height of the menu
     */
    function getHeight()
    {
        return $this->m_height;
    }

    /**
     * Get the menu position
     *
     * @return int The menu position (MENU_RIGHT, MENU_TOP, MENU_BOTTOM or MENU_LEFT)
     */
    function getPosition()
    {
        switch (atkConfig::getGlobal("menu_pos", "left")) {
            case "right": return MENU_RIGHT;
            case "top": return MENU_TOP;
            case "bottom": return MENU_BOTTOM;
        }
        return MENU_LEFT;
    }

    /**
     * Is this menu scrollable?
     *
     * @return int MENU_SCROLLABLE or MENU_UNSCROLLABLE 
     */
    function getScrollable()
    {
        return MENU_SCROLLABLE;
    }

    /**
     * Is this menu multilevel?
     *
     * @return int MENU_MULTILEVEL or MENU_NOMULTILEVEL
     */
    function getMultilevel()
    {
        return MENU_MULTILEVEL;
    }

}

