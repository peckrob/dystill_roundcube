rcmail.addEventListener('init', function(evt) {
    var button = $('<a>')
            .attr("id", "rcmdystill")
            .bind('click', function(e){ return rcmail.command('plugin.dystill.rules', this) })
            .html(rcmail.gettext("rules", "dystill"));

    var tab = $('<span>')
            .attr('id', 'settingstabdystill')
            .addClass('tablink')
            .append(button);
  
    // add and register
    rcmail.add_element(tab, 'tabs');
    rcmail.register_command('plugin.dystill.rules', function() { rcmail.goto_url('plugin.dystill.rules') }, true);
});
