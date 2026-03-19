<?php
/**
 * Admin AI signals log partial.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$signals = ACT_AI_Trader::get_analysis_log( 50 );
?>
<div class="wrap act-dashboard">
	<h1>
		<span class="dashicons dashicons-analytics"></span>
		<?php esc_html_e( 'AI Signals Log', 'ai-crypto-trader' ); ?>
	</h1>

	<?php if ( empty( $signals ) ) : ?>
		<p class="act-empty"><?php esc_html_e( 'No signals yet. Run an analysis cycle from the Dashboard.', 'ai-crypto-trader' ); ?></p>
	<?php else : ?>
		<table class="act-table widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'Asset', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'Signal', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'Confidence', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'Price', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'Reasoning', 'ai-crypto-trader' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $signals as $s ) : ?>
				<tr>
					<td><?php echo esc_html( substr( $s['analysis_time'], 0, 16 ) ); ?></td>
					<td><?php echo esc_html( $s['asset'] ); ?></td>
					<td>
						<span class="act-signal-badge act-signal-<?php echo esc_attr( $s['signal'] ); ?>">
							<?php echo esc_html( strtoupper( $s['signal'] ) ); ?>
						</span>
					</td>
					<td>
						<div class="act-confidence-bar">
							<div class="act-confidence-fill" style="width:<?php echo esc_attr( $s['confidence'] ); ?>%;"></div>
							<span><?php echo esc_html( $s['confidence'] ); ?>%</span>
						</div>
					</td>
					<td>$<?php echo esc_html( number_format( $s['price_at_analysis'], 4 ) ); ?></td>
					<td style="max-width:300px;">
						<?php echo esc_html( $s['reasoning'] ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
