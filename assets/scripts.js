jQuery(document).ready(function ($) {

	/**
	 * Data entry helpers.
	 */
	$("input[data-column-type='date']").datepicker({ dateFormat: 'yy-mm-dd' });
	$("input[data-column-type='date']").mask("9999-99-99", { placeholder:"yyyy-mm-dd" } );
	$("input[data-column-type='time']").mask("99:99:99", { placeholder:"hh:mm:ss" } );
	$("input[data-column-type='year']").mask("9999");


	/**
	 * Set up the bits that use WP_API.
	 * Make sure the WP-API nonce is always set on AJAX requests.
	 */
	if (typeof WP_API_Settings !== 'undefined') {
		$.ajaxSetup({
			headers: { 'X-WP-Nonce': WP_API_Settings.nonce }
		});

		/**
		 * Jump between tables.
		 */
		$(".tabulate .quick-jump input").autocomplete({
			source: WP_API_Settings.root + "/tabulate/tables",
			select: function( event, ui ) {
				event.preventDefault();
				$(this).prop( "disabled", true );
				$(".tabulate .quick-jump input").val( ui.item.label );
				var url = tabulate.admin_url + "&controller=table&table=" + ui.item.value;
				$(location).attr( 'href', url );
			}
		});

		/**
		 * Handle foreign-key select lists (autocomplete when greater than N options).
		 */
		$(".tabulate input.foreign-key").each(function() {
			// Autocomplete.
			$(this).autocomplete({
				source: WP_API_Settings.root + "/tabulate/fk/" + $(this).data('fk-table'),
				select: function( event, ui ) {
					event.preventDefault();
					$(this).val(ui.item.label);
					$(this).closest("td").find("input.foreign-key-actual-value").val(ui.item.value);
				}
			});
			// Clear actual-value if emptied.
			$(this).change(function(){
				if ($(this).val().length === 0) {
					$(this).closest("td").find("input.foreign-key-actual-value").val("");
				}
			});
		});

	} // if (typeof WP_API_Settings !== 'undefined')


	/**
	 * Dynamically add new filters.
	 */
	var $addFilter = $("<a class='button'>Add new filter</a>");
	$(".tabulate-filters td.buttons").append($addFilter);
	$addFilter.click(function () {
		var filterCount = $(this).parents("table").find("tr.tabulate-filter").size();
		$lastrow = $(this).parents("table").find("tr.tabulate-filter:last");
		$newrow = $lastrow.clone();
		$newrow.find("select, input").each(function () {
			var newName = $(this).attr("name").replace(/\[[0-9]+\]/, "[" + filterCount + "]")
			$(this).attr("name", newName);
		});
		$newrow.find("td:first").html("&hellip;and");
		$lastrow.after($newrow);
	});


	/**
	 * Add 'select all' checkboxen to the grants' table.
	 */
	// Copy an existing cell and remove its checkbox's names etc.
	$copiedCell = $(".tabulate-grants td.capabilities:first").clone();
	$copiedCell.find("input").attr("name", "");
	$copiedCell.find("input").removeAttr("checked");
	// For each select-all cell in the top row.
	$(".tabulate-grants tr.select-all td.target").each(function(){
		$(this).html($copiedCell.html());
	});
	// For each select-all cell in the left column.
	$(".tabulate-grants td.select-all").each(function(){
		$(this).html($copiedCell.html());
	});
	// Change the colour of checked boxen.
	$("form.tabulate-grants label.checkbox input").on('change', function() {
		if ($(this).prop("checked")) {
			$(this).closest("label").addClass("checked")
		} else {
			$(this).closest("label").removeClass("checked")
		}
	}).change();
	// Handle the en masse checking and un-checking from the top row.
	$("tr.select-all input").click(function() {
		colIndex = $(this).closest("td").index() + 1;
		capability = $(this).data("capability");
		$cells = $(".tabulate-grants tbody td:nth-child(" + colIndex + ")");
		$boxen = $cells.find("input[data-capability='" + capability + "']");
		$boxen.prop("checked", $(this).prop("checked")).change();
	});
	// Handle the en masse checking and un-checking from the left column.
	$("td.select-all input").click(function() {
		rowIndex = $(this).closest("tr").index() + 1;
		capability = $(this).data("capability");
		$cells = $(".tabulate-grants tbody tr:nth-child(" + rowIndex + ") td");
		$boxen = $cells.find("input[data-capability='" + capability + "']");
		$boxen.prop("checked", $(this).prop("checked")).change();
	});

});

