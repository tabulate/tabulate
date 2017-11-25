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
	 * Schema editing.
	 */
	$(document.body).on("keyup blur", "input.schema-identifier", function() {
		$(this).val($(this).val().replace(/[^a-zA-Z0-9_ ]/g,'')).change();
		$(this).val($(this).val().replace(/ /g,'_')).change();
		$(this).val($(this).val().toLowerCase());
	});
	$("form.tabulate-schema a.add-new-column").click(function() {
		var $tr = $(this).parents("form").find("table.column-definitions tr:last");
		var $newTr = $tr.clone();
		$newTr.find("input").val("").prop("checked", false);
		$newTr.find("option").prop("selected", false);
		$newTr.find("input, select").each(function(){
			// Rename all form element names.
			var colNum = $("form.tabulate-schema table.column-definitions tr").length;
			var oldName = $(this).attr("name");
			var newName = oldName.replace(/columns\[.*\]\[(.*)\]/, "columns["+colNum+"][$1]");
			$(this).attr("name", newName);
		});
		$tr.after($newTr);
		$newTr.find("[name*=name]").focus();
	});
	$(document).on('change', "form.tabulate-schema select[name*='xtype']", function() {
		var xtype = $(this).val();
		var $size = $(this).parents("tr").find("input[name*='size']");
		var $targetTable = $(this).parents("tr").find("select[name*='target_table']");
		var $autoInc = $(this).parents("tr").find("input[name='auto_increment']");
		if (xtype === 'fk') {
			$size.prop("disabled", true);
			$targetTable.prop("disabled", false).prop("required", true);
			$autoInc.prop("disabled", true);
		} else if (xtype === 'integer') {
			$size.prop("disabled", false).prop("required", true);
			$targetTable.prop("disabled", true);
			$autoInc.prop("disabled", false);
		} else if (xtype === 'decimal') {
			$size.prop("disabled", false).prop("required", true);
			$targetTable.prop("disabled", true);
			$autoInc.prop("disabled", true);
		} else if (xtype === 'boolean') {
			$size.prop("disabled", true);
			$targetTable.prop("disabled", true);
			$autoInc.prop("disabled", true);
		} else if (xtype === 'text_short') {
			$size.prop("disabled", false).prop("required", true);
			$targetTable.prop("disabled", true);
			$autoInc.prop("disabled", true);
		} else if (xtype === 'text_long') {
			$size.prop("disabled", true);
			$targetTable.prop("disabled", true);
			$autoInc.prop("disabled", true);
		} else if (xtype === 'date') {
			$size.prop("disabled", true);
			$targetTable.prop("disabled", true);
			$autoInc.prop("disabled", true);
		} else if (xtype === 'datetime') {
			$size.prop("disabled", true);
			$targetTable.prop("disabled", true);
			$autoInc.prop("disabled", true);
		} else if (xtype === 'time') {
			$size.prop("disabled", true);
			$targetTable.prop("disabled", true);
			$autoInc.prop("disabled", true);
		} else if (xtype === 'point') {
			$size.prop("disabled", true);
			$targetTable.prop("disabled", true);
			$autoInc.prop("disabled", true);
		} else if (xtype === 'enum') {
			$size.prop("disabled", false).prop("required", true);
			$targetTable.prop("disabled", true);
			$autoInc.prop("disabled", true);
		} else {
			$size.prop("disabled", false);
			$targetTable.prop("disabled", true);
			$autoInc.prop("disabled", true);
		}
	});
	$("form.tabulate-schema select[name*='xtype']").change();

	/**
	 * Move schema-editing rows up and down, also disabling arrows at the top and bottom where required.
	 */
	$("form.tabulate-schema a.move").click(function() {
		var $tr = $(this).parents("tr");
		$tr.hide();
		if ($(this).hasClass("move-up")) {
			$tr.prev("tr").before($tr);
		}
		if ($(this).hasClass("move-down")) {
			$tr.next("tr").after($tr);
		}
		// Hide the top up arrow and the bottom down arrow.
		hide_arrows($(this).parents("tbody"));
		// Show the moved row.
		$tr.show("slow");
	});
	hide_arrows($("form.tabulate-schema tbody"));
	function hide_arrows($table_body) {
		$table_body.find("a.move").css("visibility", "visible");
		$table_body.find("a.move:first, a.move:last").css("visibility", "hidden");
	}

	/**
	 * Make sure .disabled buttons are properly disabled.
	 */
	$("button.disabled").prop("disabled", true);

	/**
	 * Make sure the WP-API nonce is always set on AJAX requests.
	 */
	$.ajaxSetup({
		headers: { 'X-WP-Nonce': wpApiSettings.nonce }
	});

	/**
	 * Jump between tables.
	 */
	// Get the table list.
	$.getJSON(wpApiSettings.root + "tabulate/tables", function( tableNames ) {
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
			source: wpApiSettings.root + "tabulate/fk/" + $(this).data('fk-table'),
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


	/**
	 * Dynamically add new filters.
	 */
	var $addFilter = $("<a class='button btn btn-default'>Add new filter</a>");
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
		$newrow.find("input[name*='value']").val("");
		$lastrow.after($newrow);
	});


	/**
	 * Change 'is one of' filters to multi-line text input box,
	 * and if over a certain length submit as a POST request.
	 */
	$(".tabulate-filters").on("change", "select[name*='operator']", function(){
		var $oldFilter = $(this).parents("tr").find("[name*='[value]']");
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
	// Fire change manually.
	$(".tabulate-filters select[name*='operator']").change();
	// Change the form method depending on the filter size.
	$(".tabulate-filters").on("change", "textarea", function(){
		if ($(this).val().split(/\r*\n/).length > 50) {
			// Switch to a POST request for long "is one of" filters.
			$(this).parents("form").attr("method", "post");
		} else {
			// Switch back to get for smaller counts.
			$(this).parents("form").attr("method", "get");
		}
	});
	// Fire keyup manually.
	$(".tabulate-filters textarea").change();

	/**
	 * Table filter form submission (also in the shortcode):
	 * Change the controller, action, and page num of the form depending on which button was clicked,
	 * by extracting those from the data attributes of the button element.
	 */
	$(".tabulate-filters button").click(function(e) {
		if ($(this).data("controller")) {
			$(this).parents("form").find("input[name='controller']").val($(this).data("controller"));
		}
		if ($(this).data("action")) {
			$(this).parents("form").find("input[name='action']").val($(this).data("action"));
		}
		if ($(this).data("p")) {
			// Both of these 'page' parameters are used.
			$(this).parents("form").find("input[name='p']").val($(this).data("p"));
			$( this ).parents( "form" ).find( "input[name='tabulate_p']" ).val( $( this ).data( "p" ) );
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

