<?php
/**
 * Frontend AI signals shortcode template.
 *
 * @package AI_Crypto_Trader
 * @var array $atts Shortcode attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$signals = ACT_AI_Trader::get_analysis_log( absint( $atts['limit'] ) );
?>
<div class="act-widget act-signals">
	<?php if ( empty( $signals ) ) : ?>
		<p><?php esc_html_e( 'No signals yet.', 'ai-crypto-trader' ); ?></p>
	<?php else : ?>
	<table class="act-signals-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'ai-crypto-trader' ); ?></th>
				<th><?php esc_html_e( 'Asset', 'ai-crypto-trader' ); ?></th>
				<th><?php esc_html_e( 'Signal', 'ai-crypto-trader' ); ?></th>
				<th><?php esc_html_e( 'Confidence', 'ai-crypto-trader' ); ?></th>
				<th><?php esc_html_e( 'Reasoning', 'ai-crypto-trader' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $signals as $s ) : ?>
			<tr>
				<td><?php echo esc_html( substr( $s['analysis_time'], 0, 10 ) ); ?></td>
				<td><?php echo esc_html( $s['asset'] ); ?></td>
				<td>
					<span class="act-badge-<?php echo esc_attr( $s['signal'] ); ?>">
						<?php echo esc_html( strtoupper( $s['signal'] ) ); ?>
					</span>
				</td>
				<td><?php echo esc_html( $s['confidence'] ); ?>%</td>
				<td><?php echo esc_html( wp_trim_words( $s['reasoning'], 20 ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>
