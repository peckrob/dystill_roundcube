<?php

class dystill extends rcube_plugin {
    public $task = 'settings';
    public $rc;
    
    public function init() {
        $this->rc = rcmail::get_instance();
        
        $this->add_texts('localization/', true);
        $this->include_script("dystill.js");
        $this->include_stylesheet("dystill.css");
        
        $this->register_action("plugin.dystill.rules", array($this, "rules_init"));
        $this->register_action("plugin.dystill.rules_frame", array($this, "rules_frame"));
    }

    public function rules_init($args) {
        $this->rc->output->send("dystill.rules");
    }
    
    public function rules_frame($args) {
        echo "test";
        die();
    }
}

?>
