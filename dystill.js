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
    rcmail.http_post('plugin.dystill.get_folders', "");
});


rcmail.addEventListener('plugin.dystill.get_rule_callback', function(e) {
    $("#dystill_value", top.document).val(e.rule["value"]);
    $("#dystill_comparison", top.document).val(e.rule["comparison"]);
    $("#dystill_header", top.document).val(e.rule["field"]);
});

rcmail.addEventListener('plugin.dystill.get_folders_callback', function(e) {
    $("#dystill_to")
        .find('option')
        .remove();
    
    for(var i in e.folders) {
        var folder = e.folders[i];
        $("#dystill_to")
            .append(
                $("<option></option>")
                    .attr("value",folder)
                    .text(folder));

    }
});