<?php
/**
 * Admin trade history partial.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$per_page = 50;
$page     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
$offset   = ( $page - 1 ) * $per_page;
$trades   = ACT_Wallet::get_trade_history( $per_page, $offset );
$total_pnl = ACT_Wallet::get_total_pnl();
?>
<div class="wrap act-dashboard">
	<h1>
		<span class="dashicons dashicons-list-view"></span>
		<?php esc_html_e( 'Trade History', 'ai-crypto-trader' ); ?>
	</h1>

	<div class="act-kpi-row">
		<div class="act-kpi-card <?php echo $total_pnl >= 0 ? 'positive' : 'negative'; ?>">
			<span class="act-kpi-label"><?php esc_html_e( 'Total Realised PnL', 'ai-crypto-trader' ); ?></span>
			<span class="act-kpi-value">
				<?php echo $total_pnl >= 0 ? '+$' : '-$'; ?><?php echo esc_html( number_format( abs( $total_pnl ), 2 ) ); ?>
			</span>
		</div>
	</div>

	<?php if ( empty( $trades ) ) : ?>
		<p class="act-empty"><?php esc_html_e( 'No trades yet.', 'ai-crypto-trader' ); ?></p>
	<?php else : ?>
		<table class="act-table widefat striped">
			<thead>
				<tr>
					<th>#</th>
					<th><?php esc_html_e( 'Time', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'Pair', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'Side', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'Price', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'Total', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'Fee', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'PnL', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'Strategy', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'AI Conf.', 'ai-crypto-trader' ); ?></th>
					<th><?php esc_html_e( 'Notes', 'ai-crypto-trader' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $trades as $t ) : ?>
				<tr>
					<td><?php echo esc_html( $t['id'] ); ?></td>
					<td><?php echo esc_html( substr( $t['trade_time'], 0, 16 ) ); ?></td>
					<td><?php echo esc_html( $t['pair'] ); ?></td>
					<td>
						<span class="act-signal-badge act-signal-<?php echo esc_attr( $t['side'] ); ?>">
							<?php echo esc_html( strtoupper( $t['side'] ) ); ?>
						</span>
					</td>
					<td><?php echo esc_html( number_format( $t['amount'], 6 ) ); ?></td>
					<td>$<?php echo esc_html( number_format( $t['price'], 4 ) ); ?></td>
					<td>$<?php echo esc_html( number_format( $t['total_value'], 2 ) ); ?></td>
					<td>$<?php echo esc_html( number_format( $t['fee'], 4 ) ); ?></td>
					<td class="<?php echo floatval( $t['profit_loss'] ) >= 0 ? 'act-positive' : 'act-negative'; ?>">
						<?php
						$pnl = floatval( $t['profit_loss'] );
						echo 'sell' === $t['side']
							? ( $pnl >= 0 ? '+$' : '-$' ) . esc_html( number_format( abs( $pnl ), 2 ) )
							: '—';
						?>
					</td>
					<td><?php echo esc_html( $t['strategy'] ); ?></td>
					<td><?php echo esc_html( $t['ai_confidence'] ); ?>%</td>
					<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $t['notes'] ); ?>">
						<?php echo esc_html( wp_trim_words( $t['notes'], 10 ) ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
