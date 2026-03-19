<?php
/**
 * Frontend trade history shortcode template.
 *
 * @package AI_Crypto_Trader
 * @var array $atts Shortcode attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$trades = ACT_Wallet::get_trade_history( absint( $atts['limit'] ) );
?>
<div class="act-widget act-trade-history">
	<?php if ( empty( $trades ) ) : ?>
		<p><?php esc_html_e( 'No trades yet.', 'ai-crypto-trader' ); ?></p>
	<?php else : ?>
	<table class="act-trades-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'ai-crypto-trader' ); ?></th>
				<th><?php esc_html_e( 'Pair', 'ai-crypto-trader' ); ?></th>
				<th><?php esc_html_e( 'Side', 'ai-crypto-trader' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'ai-crypto-trader' ); ?></th>
				<th><?php esc_html_e( 'Price', 'ai-crypto-trader' ); ?></th>
				<th><?php esc_html_e( 'PnL', 'ai-crypto-trader' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $trades as $t ) : ?>
			<tr>
				<td><?php echo esc_html( substr( $t['trade_time'], 0, 10 ) ); ?></td>
				<td><?php echo esc_html( $t['pair'] ); ?></td>
				<td>
					<span class="act-badge-<?php echo esc_attr( $t['side'] ); ?>">
						<?php echo esc_html( strtoupper( $t['side'] ) ); ?>
					</span>
				</td>
				<td><?php echo esc_html( number_format( $t['amount'], 6 ) ); ?></td>
				<td>$<?php echo esc_html( number_format( $t['price'], 2 ) ); ?></td>
				<td>
					<?php
					$pnl = floatval( $t['profit_loss'] );
					if ( 'sell' === $t['side'] ) {
						$class = $pnl >= 0 ? 'act-pnl-positive' : 'act-pnl-negative';
						echo '<span class="' . esc_attr( $class ) . '">'
							. ( $pnl >= 0 ? '+$' : '-$' )
							. esc_html( number_format( abs( $pnl ), 2 ) )
							. '</span>';
					} else {
						echo '—';
					}
					?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>
