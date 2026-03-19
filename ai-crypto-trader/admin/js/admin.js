/* global actAdmin */
(function ($) {
	'use strict';

	// Run analysis cycle.
	$('#act-run-cycle').on('click', function () {
		var $btn    = $(this);
		var $status = $('#act-cycle-status');

		$btn.prop('disabled', true);
		$status.text(actAdmin.strings.running);

		$.post(actAdmin.ajaxUrl, {
			action: 'act_run_cycle',
			nonce:  actAdmin.nonce,
		})
		.done(function (response) {
			if (response.success) {
				var results = response.data.results;
				var summary = [];
				if (results && typeof results === 'object') {
					$.each(results, function (pair, r) {
						summary.push(pair + ': ' + r.signal.toUpperCase() + ' (' + r.action + ')');
					});
				}
				$status.text(summary.length ? summary.join(' | ') : actAdmin.strings.done);
				setTimeout(function () { location.reload(); }, 2000);
			} else {
				$status.text(actAdmin.strings.error);
			}
		})
		.fail(function () {
			$status.text(actAdmin.strings.error);
		})
		.always(function () {
			$btn.prop('disabled', false);
		});
	});

	// Reset paper wallet.
	$('#act-reset-wallet').on('click', function () {
		if (!window.confirm(actAdmin.strings.confirm_reset)) {
			return;
		}
		var $btn = $(this);
		$btn.prop('disabled', true);

		$.post(actAdmin.ajaxUrl, {
			action: 'act_reset_wallet',
			nonce:  actAdmin.nonce,
		})
		.done(function (response) {
			if (response.success) {
				location.reload();
			} else {
				alert(response.data ? response.data.message : actAdmin.strings.error);
				$btn.prop('disabled', false);
			}
		})
		.fail(function () {
			alert(actAdmin.strings.error);
			$btn.prop('disabled', false);
		});
	});

	// Manual trade form.
	$('#act-manual-trade-form').on('submit', function (e) {
		e.preventDefault();
		var $status = $('#act-trade-status');
		var pair    = $('#act-trade-pair').val().trim();
		var side    = $('#act-trade-side').val();
		var amount  = parseFloat($('#act-trade-amount').val());

		if (!pair || !side || isNaN(amount) || amount <= 0) {
			$status.text('Please fill in all fields.');
			return;
		}

		$status.text(actAdmin.strings.running);

		$.post(actAdmin.ajaxUrl, {
			action: 'act_manual_trade',
			nonce:  actAdmin.nonce,
			pair:   pair,
			side:   side,
			amount: amount,
		})
		.done(function (response) {
			if (response.success) {
				$status.css('color', '#00650a').text(response.data.message);
				$('#act-manual-trade-form')[0].reset();
				setTimeout(function () { location.reload(); }, 2000);
			} else {
				$status.css('color', '#8c1515').text(
					response.data ? response.data.message : actAdmin.strings.error
				);
			}
		})
		.fail(function () {
			$status.css('color', '#8c1515').text(actAdmin.strings.error);
		});
	});

}(jQuery));
