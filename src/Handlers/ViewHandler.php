<?php namespace Sintattica\Atk\Handlers;

use Sintattica\Atk\Core\Tools;
use Sintattica\Atk\Core\Node;
use Sintattica\Atk\Core\Config;
use Sintattica\Atk\Session\SessionManager;

/**
 * Handler class for a readonly view action. Similar to the edit handler,
 * but all fields are displayed readonly.
 *
 * @author Ivo Jansch <ivo@achievo.org>
 * @package atk
 * @subpackage handlers
 *
 */
class ViewHandler extends ViewEditBase
{
    var $m_buttonsource = null;

    /**
     * The action handler method.
     *
     * @param Bool $renderbox Render this action in a renderbox or just output the HTML
     */
    function action_view($renderbox = true)
    {
        if (!empty($this->m_partial)) {
            $this->partial($this->m_partial);
            return;
        }

        $record = $this->getRecord();

        // allowed to view record?
        if (!$record || !$this->allowed($record)) {
            $this->renderAccessDeniedPage();
            return;
        }

        $page = $this->getPage();
        $page->register_script(Config::getGlobal("assets_url") . "javascript/formsubmit.js");
        $this->notify("view", $record);
        $page->addContent($this->m_node->renderActionPage("admin",
            $this->invoke("viewPage", $record, $this->m_node, $renderbox)));
    }

    /**
     * Returns the view record.
     */
    function getRecordFromDb()
    {
        list($record) = $this->m_node->selectDb($this->m_postvars['atkselector'],
            $this->getNode()->getColumnConfig()->getOrderByStatement(), "", $this->m_node->m_viewExcludes, "", "view");
        return $record;
    }

    /**
     * Get the start of the form.
     *
     * @return String HTML The forms' start
     */
    public function getFormStart($record = null)
    {
        $formstart = '<form name="entryform" id="entryform" action="' . Tools::getDispatchFile() . '" method="get" onsubmit="return globalSubmit(this,false)">';
        $formstart .= SessionManager::formState(SessionManager::SESSION_NESTED);
        $formstart .= '<input type="hidden" name="atkselector" value="' . $this->getNode()->primaryKey($record) . '">';
        $formstart .= '<input type="hidden" class="atksubmitaction" />';
        return $formstart;

    }

    /**
     * Returns an htmlpage displaying all displayable attributes.
     * @param array $record The record to display.
     * @param Node $node The node for which a viewPage is displayed.
     * @param Bool $renderbox Render this action in a renderbox or just output the HTML
     * @return String The html page with a reaonly view of relevant fields.
     */
    function viewPage($record, $node, $renderbox = true)
    {
        $ui = $this->getUi();
        $node->addStyle("style.css");

        if (is_object($ui)) {
            $params = $node->getDefaultActionParams();
            $tab = $node->getActiveTab();
            $innerform = $this->viewForm($record, "view");

            $params["activeTab"] = $tab;
            $params["header"] = $this->invoke("viewHeader", $record);
            $params['title'] = $node->actionTitle($this->m_action, $record);
            $params["content"] = $node->tabulate("view", $innerform);

            $params["formstart"] = $this->getFormStart($record);
            $params["buttons"] = $this->getFormButtons($record);
            $params["formend"] = '</form>';

            $output = $ui->renderAction("view", $params);

            if (!$renderbox) {
                return $output;
            }

            $this->getPage()->setTitle(Tools::atktext('app_shorttitle') . " - " . $node->actionTitle($this->m_action,
                    $record));

            $vars = array("title" => $node->actionTitle($this->m_action, $record), "content" => $output);

            if ($this->getRenderMode() == "dialog") {
                $total = $ui->renderDialog($vars);
            } else {
                $total = $ui->renderBox($vars, $this->m_boxTemplate);
            }

            return $total;
        } else {
            Tools::atkerror("ui object error");
        }
    }

    /**
     * Get the buttons for the current action form.
     *
     * @param array $record
     * @return array Array with buttons
     */
    public function getFormButtons($record = null)
    {
        // If no custom button source is given, get the default Controller.
        if ($this->m_buttonsource === null) {
            $this->m_buttonsource = $this->m_node;
        }

        return $this->m_buttonsource->getFormButtons("view", $record);
    }

    /**
     * Overrideable function to create a header for view mode.
     * Similar to the admin header functionality.
     */
    function viewHeader()
    {
        return "";
    }

    /**
     * Get the view page
     *
     * @param Array $record The record
     * @param String $mode The mode we're in (defaults to "view")
     * @param String $template The template to use for the view form
     * @return String HTML code of the page
     */
    function viewForm($record, $mode = "view", $template = "")
    {
        $node = $this->m_node;

        // get data, transform into form, return
        $data = $node->viewArray($mode, $record);

        // get active tab
        $tab = $node->getActiveTab();
        // get all tabs of current mode
        $tabs = $node->getTabs($mode);

        $fields = array();
        $attributes = array();

        // For all attributes we use the display() function to display the
        // attributes current value. This may be overridden by supplying
        // an <attributename>_display function in the derived classes.
        for ($i = 0, $_i = count($data["fields"]); $i < $_i; $i++) {
            $field = &$data["fields"][$i];
            $tplfield = array();

            $classes = array();
            if ($field["sections"] == "*") {
                $classes[] = "alltabs";
            } else {
                if ($field["html"] == "section") {
                    // section should only have the tab section classes
                    foreach ($field["tabs"] as $section) {
                        $classes[] = "section_" . str_replace('.', '_', $section);
                    }
                } else {
                    if (is_array($field["sections"])) {
                        foreach ($field["sections"] as $section) {
                            $classes[] = "section_" . str_replace('.', '_', $section);
                        }
                    }
                }
            }

            $tplfield["class"] = implode(" ", $classes);
            $tplfield["tab"] = $tplfield["class"]; // for backwards compatibility
            // visible sections, both the active sections and the tab names (attribute that are
            // part of the anonymous section of the tab)
            $visibleSections = array_merge($this->m_node->getActiveSections($tab, $mode), $tabs);

            // Todo fixme: initial_on_tab kan er uit, als er gewoon bij het opstarten al 1 keer showTab aangeroepen wordt (is netter dan aparte initial_on_tab check)
            // maar, let op, die showTab kan pas worden aangeroepen aan het begin.
            $tplfield["initial_on_tab"] = ($field["tabs"] == "*" || in_array($tab, $field["tabs"])) &&
                (!is_array($field["sections"]) || count(array_intersect($field['sections'], $visibleSections)) > 0);

            // Give the row an id if it doesn't have one yet
            if (!isset($field["id"]) || empty($field["id"])) {
                $field['id'] = Tools::getUniqueID("anonymousattribrows");
            }

            // ar_ stands voor 'attribrow'.
            $tplfield["rowid"] = "ar_" . $field['id']; // The id of the containing row

            /* check for separator */
            if ($field["html"] == "-" && $i > 0 && $data["fields"][$i - 1]["html"] != "-") {
                $tplfield["line"] = "<hr>";
            } /* double separator, ignore */ elseif ($field["html"] == "-") {

            } /* sections */ elseif ($field["html"] == "section") {
                $tplfield["line"] = $this->getSectionControl($field, $mode);
            } /* only full HTML */ elseif (isset($field["line"])) {
                $tplfield["line"] = $field["line"];
            } /* edit field */ else {
                if ($field["attribute"]->m_ownerInstance->getNumbering()) {
                    $this->_addNumbering($field, $tplfield, $i);
                }

                /* does the field have a label? */
                if ((isset($field["label"]) && $field["label"] !== "Attribute::AF_NO_LABEL") || !isset($field["label"])) {
                    if ($field["label"] == "") {
                        $tplfield["label"] = "";
                    } else {
                        $tplfield["label"] = $field["label"];
                    }
                } else {
                    $tplfield["label"] = "Attribute::AF_NO_LABEL";
                }

                // Make the attribute and node names available in the template.
                $tplfield['attribute'] = $field["attribute"]->fieldName();
                $tplfield['node'] = $field["attribute"]->m_ownerInstance->atkNodeType();

                /* html source */
                $tplfield["widget"] = $field["html"];
                $editsrc = $field["html"];

                /* tooltip */
                $tooltip = $field["attribute"]->getToolTip();
                if ($tooltip) {
                    $tplfield["tooltip"] = $tooltip;
                    $editsrc .= $tooltip . "&nbsp;";
                }

                $tplfield['id'] = str_replace('.', '_', $node->atknodetype() . '_' . $field["id"]);

                $tplfield["full"] = $editsrc;

                $column = $field['attribute']->getColumn();
                $tplfield["column"] = $column;
            }
            $fields[] = $tplfield; // make field available in numeric array
            $params[$field["name"]] = $tplfield; // make field available in associative array
            $attributes[$field["name"]] = $tplfield; // make field available in associative array
        }
        $ui = $this->getUi();

        $tabTpl = $this->_getTabTpl($node, $tabs, $mode, $record);

        if ($template) {
            $innerform = $ui->render($template, array("fields" => $fields, 'attributes' => $attributes));
        } else {
            if (count(array_unique($tabTpl)) > 1) {
                $tabForm = $this->_renderTabs($fields, $tabTpl);
                $innerform = implode(null, $tabForm);
            } else {
                $innerform = $ui->render($node->getTemplate("view", $record, $tab),
                    array("fields" => $fields, 'attributes' => $attributes));
            }
        }
        return $innerform;
    }

    /**
     * The dialog partial
     *
     * @return String HTML code for the view dialog
     */
    function partial_dialog()
    {
        return $this->renderViewDialog();
    }

    /**
     * Render view dialog.
     *
     * @param array $record
     * @return string html
     */
    function renderViewDialog($record = null)
    {
        if ($record == null) {
            $record = $this->getRecord();
        }

        $this->setRenderMode('dialog');
        $result = $this->m_node->renderActionPage("view", $this->invoke("viewPage", $record, $this->m_node));
        return $result;
    }

}

