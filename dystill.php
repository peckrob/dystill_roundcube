<?php

/**
 * dystill, a roundcube plugin to provide dystill rule editing
 * 
 * @author		Rob Peck <rob-dystill@robpeck.com>
 * @copyright	2011, Rob Peck.
 * @license		BSD
 * @package		dystill_roundcube
 */
class dystill extends rcube_plugin {
    public $task = 'settings';
    public $skin = "default";
    public $dsn;
    public $rc;
    
    
    /**
     * The init() function is called by roundcube when the the plugin is loaded.
     */
    public function init() {
        $this->rc = rcmail::get_instance();
        
        $this->load_config();
        
        $this->add_texts('localization/', true);
        $this->include_script("dystill.js");
        
        $this->skin  = $this->rc->config->get('skin');
        
        if(!file_exists("plugins/dystill/skins/" . $this->skin)) {
            trigger_error("Skin " . $this->skin . " not found!");
            exit();
        }
        
        $this->include_stylesheet("skins/" . $this->skin . "/dystill.css");
        
        $this->register_action("plugin.dystill.rules", array($this, "rules_init"));
        $this->register_action("plugin.dystill.rules_frame", array($this, "rules_frame"));
        $this->register_action("plugin.dystill.rules_editor", array($this, "rules_editor"));
        $this->register_action("plugin.dystill.get_rules", array($this, "get_rules"));
        $this->register_action("plugin.dystill.get_rule", array($this, "get_rule"));
        $this->register_action("plugin.dystill.get_folders", array($this, "get_folders"));
        $this->register_action("plugin.dystill.edit_rule", array($this, "edit_rule"));
        $this->register_action("plugin.dystill.delete_rule", array($this, "delete_rule"));
        
        $this->dsn = $this->rc->config->get("dystill.db_dsnw",  $this->rc->config->get("db_dsnw"));
    }

    
    public function rules_init() {
        $this->include_script("json2.js");
        $this->rc->output->set_pagetitle($this->gettext('pagetitle'));
        $this->rc->output->send("dystill.rules");
    }
    
    
    public function rules_frame() {
        $this->include_stylesheet("skins/" . $this->skin . "/dystill_rules.css");
        $this->include_script("dystill_rules.js");
        $this->rc->output->send("dystill.rules_frame");
    }
    
    
    /**
     * This returns all rules and actions for the current user via AJAX.
     * 
     * @return	void
     */
    public function get_rules() {
        $rules = $this->_get_rules();
        
        // We don't need the IDs, so drop them.
        $rules = array_values($rules);
        
        // Return that the to the JS callback.
        $this->rc->output->command('plugin.dystill.get_rules_callback', array('rules' => $rules));
    }
    
    
    /**
     * Gets rule information via AJAX
     * 
     * @return	void
     */
    public function get_rule() {
        // Pull out the filter ID
        $filter_id = get_input_value('filter_id', RCUBE_INPUT_POST);
        
        // Get the rule
        $rule = $this->_get_rules($filter_id);
        
        // Strip it out
        if(!empty($rule)) {
            $rule = $rule[$filter_id];
        }
        
        // Send it back
        $this->rc->output->command('plugin.dystill.get_rule_callback', array('rule' => $rule));
    }
    
    
    /**
     * Gets a list of folders via AJAX
     * 
     * @return	void
     */
    public function get_folders() {
        $folders = array_keys($_SESSION["unseen_count"]);
        
        foreach($folders as &$folder) {
            $folder = str_replace(".", "/", $folder);
        }
        
        $this->rc->output->command('plugin.dystill.get_folders_callback', array('folders' => $folders));
    }
    
    
    /**
     * Adds a rule via AJAX.
     * 
     * @return	void
     */
    public function add_rule() {
        // Pull the user out of user data
        $callback = "plugin.dystill.add_rule_callback";
        $username = $this->rc->user->data["username"];
        
        // Pull out all our variables.
        $field = get_input_value('field', RCUBE_INPUT_POST);
        $comparison = (int)get_input_value('comparison', RCUBE_INPUT_POST);
        $value = get_input_value('value', RCUBE_INPUT_POST);
        $active = (int)get_input_value('active', RCUBE_INPUT_POST);
        
        $actions = json_decode(get_input_value('actions', RCUBE_INPUT_POST), true);
        
        // Validate the rule
        if(!$this->_validate($field, $value, $comparison, $actions, $callback)) {
            return;
        }
        
        // Open a DB connection
        $db = new rcube_mdb2($this->dsn);
        $db->db_connect("r");
        
        // Do this in a transaction in case of failure.
        $db->query("start transaction");
        
        //  The master insert query
        $sql = sprintf("insert into filters set field='%s', email='%s', comparison=%d, value='%s', active=%d",
            $db->escapeSimple($field),
            $db->escapeSimple($username),
            $comparison,
            $db->escapeSimple($value),
            $active
        );
        
        // Run and check for failures.
        if(!$db->query($sql)) {
            $db->query("rollback");
            $this->rc->output->command($callback, array('error' => true, "message" => $this->gettext('dberror')));
            return;
        }
        
        // Get the new filter_id
        $filter_id = $db->insert_id();
        
        // Now, loop through the actions and add them.
        foreach($actions as $action) {
            $sql = sprintf("insert into filters_actions set filter_id=%d, action='%s', argument='%s'", 
                $filter_id,
                $db->escapeSimple($action["action"]),
                $db->escapeSimple($action["argument"])
            );
            
            // Query or rollback on failure.
            if(!$db->query($sql)) {
                $db->query("rollback");
                $this->rc->output->command($callback, array('error' => true, "message" => $this->gettext('dberror')));
                return;
            }
        }
        
        // If we've gotten this far, commit.
        $db->query("commit");
        
        // Return status.
        $this->rc->output->command($callback, array('error' => false, "message" => $this->gettext('rulecreated')));
        return;
    }
    
    
    /**
     * Edits a rule via AJAX.
     * 
     * @return	void
     */
    public function edit_rule() {
        // Pull the user out of user data
        $callback = "plugin.dystill.add_rule_callback";
        $username = $this->rc->user->data["username"];
        
        // Pull out all our variables
        $filter_id = get_input_value('filter_id', RCUBE_INPUT_POST);
        $field = get_input_value('field', RCUBE_INPUT_POST);
        $comparison = (int)get_input_value('comparison', RCUBE_INPUT_POST);
        $value = get_input_value('value', RCUBE_INPUT_POST);
        $active = (int)get_input_value('active', RCUBE_INPUT_POST);
        
        $actions = json_decode(get_input_value('actions', RCUBE_INPUT_POST), true);
        
        if($filter_id == "new") {
            return $this->add_rule();
        }
        
        // Open a DB connection
        $db = new rcube_mdb2($this->dsn);
        $db->db_connect("r");
        
        // Security check to be sure the rule exists and is owned by the user
        $sql = sprintf("select * from filters where email='%s' and filter_id=%d",
            $db->escapeSimple($username),
            $filter_id
        );
        
        // Run that
        $res = $db->query($sql);
        
        // Check to be sure it exists
        if(!$db->num_rows($res)) {
            $this->rc->output->command($callback, array('error' => true, "message" => $this->gettext('norule')));
            return;
        }
        
        // Now, validate the rule as they have it edited.
        if(!$this->_validate($field, $value, $comparison, $actions, $callback)) {
            return;
        }

        // Do this in a transaction
        $db->query("start transaction");
        
        // The master query that updates the rule
        $sql = sprintf("update filters set field='%s', email='%s', comparison=%d, value='%s', active=%d where filter_id=%d",
            $db->escapeSimple($field),
            $db->escapeSimple($username),
            $comparison,
            $db->escapeSimple($value),
            $active,
            $filter_id
        );
        
        // Run and check for failures
        if(!$db->query($sql)) {
            $db->query("rollback");
            $this->rc->output->command($callback, array('error' => true, "message" => $this->gettext('dberror')));
            return;
        }
        
        // Delete the old rules
        if(!$db->query(sprintf("delete from filters_actions where filter_id=%d", $filter_id))) {
            $db->query("rollback");
            $this->rc->output->command($callback, array('error' => true, "message" => $this->gettext('dberror')));
            return;
        }
        
        // Add new rules back.
        foreach($actions as $action) {
            $sql = sprintf("insert into filters_actions set filter_id=%d, action='%s', argument='%s'", 
                $filter_id,
                $db->escapeSimple($action["action"]),
                $db->escapeSimple($action["argument"])
            );
            
            if(!$db->query($sql)) {
                $db->query("rollback");
                $this->rc->output->command($callback, array('error' => true, "message" => $this->gettext('dberror')));
                return;
            }
        }
        
        // If we've gotten this far with no errors, commit.
        $db->query("commit");
        
        // Return status.
        $this->rc->output->command($callback, array('error' => false, "message" => $this->gettext('rulecreated')));
        return;
    }
    
    
    /**
     * Deletes a rule via AJAX
     * 
     * @return	void
     */
    public function delete_rule() {
        $filter_id = get_input_value('filter_id', RCUBE_INPUT_POST);
        
        // Pull the user out of user data
        $username = $this->rc->user->data["username"];
        
        // Open a DB connection
        $db = new rcube_mdb2($this->dsn);
        $db->db_connect("r");
        
        // Security check to be sure the rule exists and is owned by the user
        $sql = sprintf("select * from filters where email='%s' and filter_id=%d",
            $db->escapeSimple($username),
            $filter_id
        );
        
        // Run that
        $res = $db->query($sql);
        
        // Now, delete or throw an error
        if($db->num_rows($res)) {
            $db->query("delete from filters where filter_id=$filter_id");
            $db->query("delete from filters_actions where filter_id=$filter_id");
        } else {
            $this->rc->output->command('plugin.dystill.delete_rule_callback', array('error' => true, "message" => $this->gettext('norule')));
            return; 
        }
        
        $this->rc->output->command('plugin.dystill.delete_rule_callback', array('error' => false, "message" => $this->gettext('ruledeleted')));
    }
    
    
    /**
     * Enter description here ...
     * 
     * @param	int		$rule_id	The ID of the filter
     * @return	void
     */
    private function _get_rules($rule_id = null) {
        // Pull the user out of user data
        $username = $this->rc->user->data["username"];
        
        // Open a DB connection
        $db = new rcube_mdb2($this->dsn);
        $db->db_connect("r");
        
        // Create SQL statement
        $sql = sprintf("select * from filters_actions left join filters using (filter_id) where email='%s'",
            $db->escapeSimple($username)
        );
        
        // Only query one?
        if(!is_null($rule_id)) {
            $sql .= sprintf(" and filter_id=%d",
                $rule_id
            );
        }
        
        // Query the database for that user's rules
        $res = $db->query($sql);
        
        // Loop through the rows, build a data structure.
        while($row = $db->fetch_assoc($res)) {
            $rules[$row["filter_id"]]["filter_id"] = $row["filter_id"];
            $rules[$row["filter_id"]]["field"] = $row["field"];
            $rules[$row["filter_id"]]["comparison"] = $row["comparison"];
            $rules[$row["filter_id"]]["value"] = $row["value"];
            $rules[$row["filter_id"]]["active"] = $row["active"];
            
            $rules[$row["filter_id"]]["actions"][] = array(
                "action"    => $row["action"],
                "argument"	=> $row["argument"]
            );
        }
        
        return $rules;
    }
    
    
    /**
     * Helper function to validate new / edited rules.
     * 
     * @param	string	$field		The field for the rule
     * @param 	int 	$value		The type of comparison
     * @param 	string	$comparison	The compariosn vale.
     * @param 	array	$actions	An array of actions to do.
     * @param 	string	$callback	The javascript callback to send.
     * @return	bool
     */
    private function _validate($field, $value, $comparison, $actions, $callback) {
        if(empty($field) && empty($value)) {
            $this->rc->output->command($callback, array('error' => true, "message" => $this->gettext('missingfield')));
            return false;
        }
        
        // TODO: Validate regular expression
        if($comparison == 4 && false) {
            $this->rc->output->command($callback, array('error' => true, "message" => $this->gettext('badregexp')));
            return false;
        }
        
        if(empty($actions)) {
            $this->rc->output->command($callback, array('error' => true, "message" => $this->gettext('missingactions')));
            return false;            
        }
        
        foreach($actions as $action) {
            if(empty($action["argument"]) && in_array($action["action"], array("prependsub", "header", "forward", "copyto", "to"))) {
                $this->rc->output->command($callback, array('error' => true, "message" => $this->gettext('missingarg')));
                return false;   
            }
        }
        
         return true;
    }
}

?>