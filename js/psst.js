var psst = psst || {};

psst.confirmation = psst.confirmation || {};

psst.confirmation = (function ($) {

	var self;

	return {

		/**
		 * Initialize our modal
		 * Add our events
		 */
		init: function () {
			var clipboard = new ClipboardJS('.psst');

			$(document).foundation();

			clipboard.on('success', function(e) {
				$( '#copy-secret' ).foundation('show');
			});

			clipboard.on('error', function(e) {
				console.error('Action:', e.action);
				console.error('Trigger:', e.trigger);
			});
		}
	};

})(jQuery);

psst.confirmation.init();
