/* Romanian initialisation for the jQuery UI date picker plugin. */
/* Written by Andy. */
(function($) {
	$.datepicker.regional['ro'] = {
		renderer: $.datepicker.defaultRenderer,
		monthNames: ['Ianuarie','Februarie','Martie','Aprilie','Mai','Iunie',
		'Iulie','August','Septembrie','Octombrie','Noiembrie','Decembrie'],
		monthNamesShort: ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun',
		'Iul', 'Aug', 'Sep', 'Oct', 'Noi', 'Dec'],
		dayNames: ['Duminica', 'Luni', 'Marti', 'Miercuri', 'Joi', 'Vineri', 'Sambata'],
		dayNamesShort: ['Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sam'],
		dayNamesMin: ['Du','Lu','Ma','Mi','Jo','Vi','Sa'],
		dateFormat: 'dd/mm/yyyy',
		firstDay: 1,
		prevText: '&#x3c;Ant.', prevStatus: '',
		prevJumpText: '&#x3c;&#x3c;', prevJumpStatus: '',
		nextText: 'Urm.&#x3e;', nextStatus: '',
		nextJumpText: '&#x3e;&#x3e;', nextJumpStatus: '',
		currentText: 'Curent', currentStatus: '',
		todayText: 'Azi', todayStatus: '',
		clearText: 'Sterge', clearStatus: '',
		closeText: 'Gata', closeStatus: '',
		yearStatus: '', monthStatus: '',
		weekText: 'Sap', weekStatus: '',
		dayStatus: 'DD d MM',
		defaultStatus: '',
		isRTL: false
	};
	$.extend($.datepicker.defaults, $.datepicker.regional['ro']);
})(jQuery);
