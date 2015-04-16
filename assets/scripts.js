jQuery(document).ready(function ($) {

	/**
	 * Data entry helpers.
	 */
	$("input.datepicker").datepicker({ dateFormat: 'yy-mm-dd' });

	/**
	 * Jump between tables.
	 * Make sure the WP-API nonce is always set on AJAX requests.
	 */
	if (typeof WP_API_Settings !== 'undefined') {
		$.ajaxSetup({
			headers: { 'X-WP-Nonce': WP_API_Settings.nonce }
		});
		$(".tabulate .quick-jump input").autocomplete({
			source: WP_API_Settings.root + "/tabulate/tables",
			select: function( event, ui ) {
				event.preventDefault();
				$(this).prop( "disabled", true );
				$(".tabulate .quick-jump input").val( ui.item.label );
				console.log(ui.item.label);
				var url = tabulate.admin_url + "&controller=table&table=" + ui.item.value;
				$(location).attr( 'href', url );
			}
		});
	}


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

	// Handle the en masse checking and un-checking from the top row.
	$("tr.select-all input").click(function() {
		colIndex = $(this).closest("td").index() + 1;
		capability = $(this).data("capability");
		$cells = $(".tabulate-grants tbody td:nth-child(" + colIndex + ")");
		$boxen = $cells.find("input[data-capability='" + capability + "']");
		$boxen.prop("checked", $(this).prop("checked"));
	});
	// Handle the en masse checking and un-checking from the left column.
	$("td.select-all input").click(function() {
		rowIndex = $(this).closest("tr").index() + 1;
		capability = $(this).data("capability");
		$cells = $(".tabulate-grants tbody tr:nth-child(" + rowIndex + ") td");
		$boxen = $cells.find("input[data-capability='" + capability + "']");
		$boxen.prop("checked", $(this).prop("checked"));
	});

});

