<?php
/**
 * Frontend portfolio shortcode template.
 *
 * @package AI_Crypto_Trader
 * @var array $atts Shortcode attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$holdings  = ACT_Wallet::get_holdings();
$snapshots = ACT_Wallet::get_snapshots( absint( $atts['limit'] ) );
$total_pnl = ACT_Wallet::get_total_pnl();

// Compute rough total (stablecoins only for simplicity on front-end).
$total = 0;
foreach ( $holdings as $h ) {
	if ( in_array( strtoupper( $h['asset'] ), array( 'USDT', 'USD', 'BUSD', 'USDC' ), true ) ) {
		$total += floatval( $h['amount'] );
	}
}
?>
<div class="act-widget act-portfolio">
	<div class="act-summary">
		<div class="act-summary-item">
			<span class="act-summary-label"><?php esc_html_e( 'Stable Balance', 'ai-crypto-trader' ); ?></span>
			<span class="act-summary-value">$<?php echo esc_html( number_format( $total, 2 ) ); ?></span>
		</div>
		<div class="act-summary-item">
			<span class="act-summary-label"><?php esc_html_e( 'Total PnL', 'ai-crypto-trader' ); ?></span>
			<span class="act-summary-value <?php echo $total_pnl >= 0 ? 'act-pnl-positive' : 'act-pnl-negative'; ?>">
				<?php echo ( $total_pnl >= 0 ? '+$' : '-$' ) . esc_html( number_format( abs( $total_pnl ), 2 ) ); ?>
			</span>
		</div>
	</div>

	<?php if ( ! empty( $holdings ) ) : ?>
	<table class="act-portfolio-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Asset', 'ai-crypto-trader' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'ai-crypto-trader' ); ?></th>
				<th><?php esc_html_e( 'Avg Buy Price', 'ai-crypto-trader' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $holdings as $h ) : ?>
			<?php if ( floatval( $h['amount'] ) < 0.000001 ) { continue; } ?>
			<tr>
				<td><strong><?php echo esc_html( $h['asset'] ); ?></strong></td>
				<td><?php echo esc_html( number_format( $h['amount'], 6 ) ); ?></td>
				<td><?php echo $h['avg_buy_price'] > 0 ? '$' . esc_html( number_format( $h['avg_buy_price'], 4 ) ) : '—'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>
