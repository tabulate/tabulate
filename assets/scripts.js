jQuery(document).ready(function ($) {

	// Dynamically add new filters.
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

});

