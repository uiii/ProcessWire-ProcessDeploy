
$(document).ready(function() {
	// instantiate the WireTabs
	var $form = $("#ProcessDeployEdit"); 
	if($form.size() > 0 && $('li.WireTab').size() > 1) {
		$form.WireTabs({
			items: $(".Inputfields li.WireTab"),
			id: 'FieldDeployTabs',
			rememberTabs: false,
			skipRememberTabIDs: ['delete']
		});

		$form.find('[name=submit]').hide();
	}

	$(document).on('wiretabclick', function($event, $tab) {
		if ($tab.attr('id') === 'deploy') {
			$form.find('[name=submit]').hide();
		} else {
			$form.find('[name=submit]').show();
		}
	});
});