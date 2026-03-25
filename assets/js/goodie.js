(function () {
	function updateSelectedCount() {
		var countNode = document.querySelector('[data-selected-count]');
		var checkboxes = document.querySelectorAll('.goodie-product-option input[type="checkbox"]:checked');

		if (!countNode || !window.goodieCollections || !window.goodieCollections.i18n) {
			return;
		}

		countNode.textContent = window.goodieCollections.i18n.selectedCount.replace('%d', checkboxes.length);
	}

	function trackCollectionCreated() {
		if (!window.goodieCollections || !window.goodieCollections.collectionCreated) {
			return;
		}

		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			event: window.goodieCollections.eventName || 'goodie_collection_created',
			collectionId: window.goodieCollections.collectionId || 0,
			collectionName: window.goodieCollections.collectionName || ''
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var productInputs = document.querySelectorAll('.goodie-product-option input[type="checkbox"]');

		trackCollectionCreated();
		updateSelectedCount();

		productInputs.forEach(function (input) {
			input.addEventListener('change', updateSelectedCount);
		});
	});
}());
