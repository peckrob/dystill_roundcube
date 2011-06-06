var rules_map = {
        0: rcmail.gettext('starts_with', 'dystill'),
        1: rcmail.gettext('ends_with', 'dystill'),
        2: rcmail.gettext('contains', 'dystill'),
        3: rcmail.gettext('is', 'dystill'),
        4: rcmail.gettext('regexp', 'dystill'),
}

rcmail.addEventListener("init", function(e) {
    rcmail.http_post('plugin.dystill.get_rules', "");
});

rcmail.addEventListener('plugin.dystill.get_rules_callback', function(e) {
    if(e.rules != undefined && typeof(e.rules) == "object") {
        // Remove the current rules before rebuilding
        $("table.rules_table tbody tr").remove();
        
        // Loop through the rules, add them
        for(var i in e.rules) {
            zrule = e.rules[i];
            var rule = "";
            var actions = new Array();
            
            rule = zrule["field"] + " " + rules_map[zrule["comparison"]] + " " + zrule["value"];
            
            for(var z in zrule["actions"]) {
                actions[actions.length] = rcmail.gettext(zrule["actions"][z]["action"], "dystill") + " " + zrule["actions"][z]["argument"];
            }
            
            actions = actions.join(", ");
            
            $('table.rules_table > tbody:last')
                .append("<tr><td>" + rule + "</td><td>" + actions + "</td></tr>");
        }
    }
});