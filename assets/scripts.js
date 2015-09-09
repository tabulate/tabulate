jQuery(document).ready(function ($) {

	/**
	 * Data entry helpers.
	 */
	$("input[data-column-type='datetime']").mask("9999-99-99 99:99:99", { placeholder:"yyyy-mm-dd hh:mm:ss" } );
	$("input[data-column-type='datetime']").datetimepicker( { dateFormat: 'yy-mm-dd', timeFormat: 'HH:mm:ss' } );
	$("input[data-column-type='date']").datepicker({ dateFormat: 'yy-mm-dd' });
	$("input[data-column-type='date']").mask("9999-99-99", { placeholder:"yyyy-mm-dd" } );
	$("input[data-column-type='time']").mask("99:99:99", { placeholder:"hh:mm:ss" } );
	$("input[data-column-type='time']").timepicker( { timeFormat: 'HH:mm:ss', timeOnly: true } );
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
		// Get the table list.
		$.getJSON(WP_API_Settings.root + "/tabulate/tables", function( tableNames ) {
			for ( var t in tableNames ) {
				var table = tableNames[t];
				var url = tabulate.admin_url + "&controller=table&table=" + table.value;
				var $li = $("<li><a href='" + url + "'>" + table.label + "</a></li>");
				$li.hide();
				$("#tabulate-quick-jump").append($li);
			}
		});
		// Show the table list.
		$("#tabulate-quick-jump label").click(function(event) {
			event.preventDefault();
			//event.stopPropagation();
			var $quickJump = $(this).parents("#tabulate-quick-jump");
			$quickJump.toggleClass('expanded');
			if ($quickJump.hasClass('expanded')) {
				$quickJump.find("li[class!='filter']").show();
				$quickJump.find("input").focus().keyup();
			} else {
				$quickJump.find("li[class!='filter']").hide();
			}
		});
		// Close the table list by clicking anywhere else.
		$(document).click(function(e) {
			if ($(e.target).parents('#tabulate-quick-jump').length == 0) {
				$('#tabulate-quick-jump.expanded label').click();
			}
		});
		// Filter the table list.
		$("#tabulate-quick-jump input").keyup(function() {
			var s = $(this).val().toLowerCase();
			$(this).parents("#tabulate-quick-jump").find("li[class!='filter']").each(function(){
				var t = $(this).text().toLowerCase();
				if (t.indexOf(s) == -1) {
					$(this).hide();
				} else {
					$(this).show();
				}
			});
		});


		/**
		 * Handle foreign-key select lists (autocomplete when greater than N options).
		 */
		$(".tabulate .foreign-key .form-control:input").each(function() {
			// Autocomplete.
			$(this).autocomplete({
				source: WP_API_Settings.root + "/tabulate/fk/" + $(this).data('fk-table'),
				select: function( event, ui ) {
					event.preventDefault();
					$(this).val(ui.item.label);
					$(this).closest(".foreign-key").find(".actual-value").val(ui.item.value);
					$(this).closest(".foreign-key").find(".input-group-addon").text(ui.item.value);
				}
			});
			// Clear actual-value if emptied.
			$(this).change(function(){
				if ($(this).val().length === 0) {
					$(this).closest(".foreign-key").find(".actual-value").val("");
					$(this).closest(".foreign-key").find(".input-group-addon").text("");
				}
			});
			// Clear entered text if no value was selected.
			$(this).on("blur", function() {
				if ($(this).closest(".foreign-key").find(".actual-value").val().length === 0) {
					$(this).val("");
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
	 * Change 'is one of' filters to multi-line text input box.
	 */
	$(".tabulate-filters").on("change", "select[name*='operator']", function(){
		var $oldFilter = $(this).parents("tr").find("[name*='value']");
		var newType = $oldFilter.is("input") ? "textarea" : "input";
		var requiresMulti = ($(this).val() === 'in' || $(this).val() === 'not in');
		var $newFilter = $("<"+newType+" name='"+$oldFilter.attr("name")+"'/>");
		$newFilter.val($oldFilter.val());

		if ($oldFilter.is("input") && requiresMulti) {
			// If changing TO a multi-line value.
			$newFilter.attr("rows", 2);
			$oldFilter.replaceWith($newFilter);

		} else if ($oldFilter.is("textarea") && !requiresMulti) {
			// If changing AWAY FROM a multi-line value.
			$newFilter.attr("type", "text");
			$oldFilter.replaceWith($newFilter);

		}
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


	/**
	 * Enable point-selection for the editing form field.
	 */
	$(".tabulate-record .point-column").each(function() {
		var $formField = $(this).find(":input");
		var attrib = 'Map data &copy; <a href="http://openstreetmap.org">OSM</a> contributors <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>';
		var centre = [-32.05454466592707, 115.74644923210144]; // Fremantle!
		var map = L.map($(this).attr("id")+"-map").setView(centre, 16);
		L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: attrib }).addTo(map);
		var marker;

		// If already has a value.
		if ($formField.val()) {
			omnivore.wkt.parse($formField.val()).eachLayer(function(m) {
				addMarker(m.getLatLng());
				marker.update();
			});
		}
		// On click. Dragging is handled below.
		map.on('click', function(e) {
			addMarker(e.latlng);
		});
		// Add a marker at the specified location.
		function addMarker(latLng) {
			if (map.hasLayer(marker)) {
				map.removeLayer(marker);
			}
			marker = L.marker(latLng, { clickable:true, draggable:true });
			marker.on("add", recordNewCoords).on("dragend", recordNewCoords);
			marker.addTo(map);
			map.panTo(marker.getLatLng());
		}
		function recordNewCoords(e) {
			var wkt = "POINT("+marker.getLatLng().lng+" "+marker.getLatLng().lat+")";
			$formField.val(wkt);
		}
	});

});

