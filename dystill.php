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
        $this->register_action("plugin.dystill.get_rules", array($this, "get_rules"));
        
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
     * This returns all rules and actions for the current user via AJAX.
     * 
     * @return	void
     */
    public function get_rules() {
        // Pull the user out of user data
        $username = $this->rc->user->data["username"];
        
        // Query the database for that user's rules
        $db = new rcube_mdb2($this->dsn);
        $db->db_connect("r");
        $res = $db->query(sprintf("select * from filters_actions inner join filters using (filter_id) where email='%s'",
            $db->escapeSimple($username)
        ));
        
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
        
        // We don't need the IDs, so drop them.
        $rules = array_values($rules);
        
        // Return that the to the JS callback.
        $this->rc->output->command('plugin.dystill.get_rules_callback', array('rules' => $rules));
    }
}

?>
