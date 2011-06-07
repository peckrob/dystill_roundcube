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
        
        $this->dsn = $this->rc->config->get("dystill.db_dsnw",  $this->rc->config->get("db_dsnw"));
    }

    
    public function rules_init() {
        $this->rc->output->set_pagetitle($this->gettext('pagetitle'));
        $this->rc->output->send("dystill.rules");
    }
    
    
    public function rules_frame() {
        $this->include_stylesheet("skins/" . $this->skin . "/dystill_rules.css");
        $this->include_script("dystill_rules.js");
        $this->rc->output->send("dystill.rules_frame");
    }
    
    
    /**
     * Called by the frame that displays the rule editor.
     * 
     * @return	void
     */
    public function rules_editor() {
        $this->include_stylesheet("skins/" . $this->skin . "/dystill_rules.css");
        $this->include_script("dystill_rules_editor.js");
        $this->rc->output->send("dystill.rules_editor");
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
        $sql = sprintf("select * from filters_actions inner join filters using (filter_id) where email='%s'",
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
}

?>
