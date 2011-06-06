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
});
