rcmail.addEventListener('init', function(evt) {
    // Create a tab to be added to the tab bar
    var button = $('<a>')
            .attr("id", "rcmdystill")
            .bind('click', function(e){ return rcmail.command('plugin.dystill.rules', this) })
            .html(rcmail.gettext("rules", "dystill"));

    var tab = $('<span>')
            .attr('id', 'settingstabdystill')
            .addClass('tablink')
            .append(button);
    
    // If we're on the right page, select the tab.
    if(rcmail.env.action == "plugin.dystill.rules") {
        tab.addClass('tablink-selected');
    }
  
    // add and register
    rcmail.add_element(tab, 'tabs');
    rcmail.register_command('plugin.dystill.rules', function() { rcmail.goto_url('plugin.dystill.rules') }, true);
    rcmail.register_command('plugin.dystill.add_rule', add_rule, true);
    rcmail.register_command('plugin.dystill.edit_rule', edit_rule, true);
    rcmail.register_command('plugin.dystill.delete_rule', delete_rule, true);
    rcmail.http_post('plugin.dystill.get_folders', "");
    
    // Bind the buttons
    $("#deletebtn").bind("click", function(e){
        if(confirm("Are you sure you want to delete this rule?")) {
            return rcmail.command('plugin.dystill.delete_rule', this);
        } 
        
        return false;
    });
    
    $("#savebtn").bind("click", function(e) {
        return rcmail.command('plugin.dystill.edit_rule', this);
    });
});


rcmail.addEventListener('plugin.dystill.get_rule_callback', function(e) {
    $("#dystill_value", top.document).val(e.rule["value"]);
    $("#dystill_comparison", top.document).val(e.rule["comparison"]);
    $("#dystill_field", top.document).val(e.rule["field"]);
    $("#dystill_filter_id", top.document).val(e.rule["filter_id"]);
    
    var checkboxes = ["markasread", "flag", "delete", "blocknote", "block"];
    for(var i in checkboxes) {
        $("#dystill_" + checkboxes[i], top.document).attr("checked", "");
    }
    var fields = ["to", "copyto", "prependsub", "header", "forward"];
    for(var i in fields) {
        $("#dystill_" + fields[i], top.document).val("");
    }
    
    if(e.rule["actions"] != undefined) {
        for(var i in e.rule["actions"]) {
            var action = e.rule["actions"][i];
            
            switch(action["action"]) {
                case "to":
                case "copyto":
                case "prependsub":
                case "header":
                case "forward":
                    $("#dystill_" + action["action"], top.document).val(action["argument"]);
                    break;
                    
                case "markasread":
                case "flag":
                case "delete":
                case "block":
                case "blocknote":
                    $("#dystill_" + action["action"], top.document).attr("checked", "checked");
                    break;
            }
        }
    }
});

rcmail.addEventListener('plugin.dystill.get_folders_callback', function(e) {
    $("#dystill_to")
        .find('option')
        .remove();
    $("#dystill_to").append($("<option></option>"));
    $("#dystill_copyto")
        .find('option')
        .remove();
    $("#dystill_copyto").append($("<option></option>"));
    
    for(var i in e.folders) {
        var folder = e.folders[i];
        $("#dystill_to")
            .append(
                $("<option></option>")
                    .attr("value",folder)
                    .text(folder));
        
        $("#dystill_copyto")
            .append(
                $("<option></option>")
                    .attr("value",folder)
                    .text(folder));
    }
});

function edit_rule() {
    var post_fields = $(".serialize").serialize();
    
    var check_actions = ["to", "copyto", "forward", "prependsub", "header"];
    var cb_actions = ["markasread", "block", "blocknote", "delete", "flag"];
    var actions = [];
    
    for(var i in check_actions) {
        var ac = check_actions[i];
        
        if($("#dystill_" + ac).val() != "") {
            actions[actions.length] = {
                "action": ac,
                "argument": $("#dystill_" + ac).val()
            }
        }
    }
    
    for(var i in cb_actions) {
        var ac = cb_actions[i];
        
        if($("#dystill_" + ac).attr("checked") == true) {
            actions[actions.length] = {
                "action": ac,
                "argument": ""
            }
        }
    }
    
    post_fields += "&actions=" + urlencode(JSON.stringify(actions));
    
    rcmail.http_post("plugin.dystill.edit_rule", post_fields);
}

rcmail.addEventListener("plugin.dystill.edit_rule_callback", function(e) {
    if(e.error == true) {
        rcmail.display_message(e.message, "error");
    } else {
        rcmail.display_message(e.message, "confirmation");
        $("#prefs-frame").each(function() {
            this.contentWindow.location.reload(true);
        })
    }
});

function delete_rule() {
    var filter_id = $("#dystill_filter_id").val();
    
    rcmail.http_post("plugin.dystill.delete_rule", "filter_id=" + filter_id);
}

rcmail.addEventListener("plugin.dystill.delete_rule_callback", function(e) {
    if(e.error == true) {
        rcmail.display_message(e.message, "error");
    } else {
        rcmail.display_message(e.message, "confirmation");
        $("#prefs-frame").each(function() {
            this.contentWindow.location.reload(true);
        })
    }
});

function add_rule() {
    var post_fields = $(".serialize").serialize();
    
    var check_actions = ["to", "copyto", "forward", "prependsub", "header"];
    var cb_actions = ["markasread", "block", "blocknote", "delete", "flag"];
    var actions = [];
    
    for(var i in check_actions) {
        var ac = check_actions[i];
        
        if($("#dystill_" . ac).val() != "") {
            actions[actions.length] = {
                "action": ac,
                "argument": $("#dystill_" . ac).val()
            }
        }
    }
    
    for(var i in cb_actions) {
        var ac = cb_actions[i];
        
        if($("#dystill_" . ac).attr("checked") == true) {
            actions[actions.length] = {
                "action": ac,
                "argument": ""
            }
        }
    }
    
    post_fields += "&actions=" . urlencode(JSON.stringify(actions));
    
    rcmail.http_post("plugin.dystill.edit_rule", post_fields);
}

rcmail.addEventListener("plugin.dystill.add_rule_callback", function(e) {
    if(e.error == true) {
        rcmail.display_message(e.message, "error");
    } else {
        rcmail.display_message(e.message, "confirmation");
        $("#prefs-frame").each(function() {
            this.contentWindow.location.reload(true);
        })
    }
});