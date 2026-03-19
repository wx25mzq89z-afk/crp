<?php
/**
 * Admin dashboard partial.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings  = (array) get_option( 'act_settings', array() );
$holdings  = ACT_Wallet::get_holdings();
$snapshots = ACT_Wallet::get_snapshots( 30 );
$total_pnl = ACT_Wallet::get_total_pnl();
$recent_trades = ACT_Wallet::get_trade_history( 5 );
$recent_signals = ACT_AI_Trader::get_analysis_log( 5 );
$next_run  = wp_next_scheduled( ACT_Scheduler::TRADING_HOOK );

// Build quick portfolio value.
$prices = array();
foreach ( $holdings as $h ) {
	if ( in_array( strtoupper( $h['asset'] ), array( 'USDT', 'USD', 'BUSD', 'USDC' ), true ) ) {
		$prices[ $h['asset'] ] = 1.0;
	}
}
$total_value = ACT_Wallet::get_total_value( $prices );
?>
<div class="wrap act-dashboard">
	<h1 class="act-page-title">
		<span class="dashicons dashicons-chart-line"></span>
		<?php esc_html_e( 'AI Crypto Trader — Dashboard', 'ai-crypto-trader' ); ?>
	</h1>

	<?php if ( empty( $settings['openai_api_key'] ) ) : ?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<?php
			printf(
				/* translators: %s: settings page URL */
				esc_html__( 'No OpenAI API key configured. The plugin is using rule-based fallback analysis. %s', 'ai-crypto-trader' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=ai-crypto-trader-settings' ) ) . '">' . esc_html__( 'Configure settings →', 'ai-crypto-trader' ) . '</a>'
			);
			?>
		</p>
	</div>
	<?php endif; ?>

	<?php if ( empty( $settings['trading_enabled'] ) ) : ?>
	<div class="notice notice-info is-dismissible">
		<p>
			<?php
			printf(
				/* translators: %s: settings page URL */
				esc_html__( 'Automated trading is currently DISABLED. %s', 'ai-crypto-trader' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=ai-crypto-trader-settings' ) ) . '">' . esc_html__( 'Enable in Settings →', 'ai-crypto-trader' ) . '</a>'
			);
			?>
		</p>
	</div>
	<?php endif; ?>

	<!-- KPI Cards -->
	<div class="act-kpi-row">
		<div class="act-kpi-card">
			<span class="act-kpi-label"><?php esc_html_e( 'Portfolio Value', 'ai-crypto-trader' ); ?></span>
			<span class="act-kpi-value" id="act-total-value">
				$<?php echo esc_html( number_format( $total_value, 2 ) ); ?>
			</span>
		</div>
		<div class="act-kpi-card <?php echo $total_pnl >= 0 ? 'positive' : 'negative'; ?>">
			<span class="act-kpi-label"><?php esc_html_e( 'Total PnL', 'ai-crypto-trader' ); ?></span>
			<span class="act-kpi-value">
				<?php echo $total_pnl >= 0 ? '+' : ''; ?>$<?php echo esc_html( number_format( $total_pnl, 2 ) ); ?>
			</span>
		</div>
		<div class="act-kpi-card">
			<span class="act-kpi-label"><?php esc_html_e( 'Open Positions', 'ai-crypto-trader' ); ?></span>
			<span class="act-kpi-value"><?php echo esc_html( ACT_Risk_Manager::count_open_positions() ); ?></span>
		</div>
		<div class="act-kpi-card">
			<span class="act-kpi-label"><?php esc_html_e( 'Next Analysis', 'ai-crypto-trader' ); ?></span>
			<span class="act-kpi-value" style="font-size:14px;">
				<?php echo $next_run ? esc_html( human_time_diff( time(), $next_run ) ) : esc_html__( 'Not scheduled', 'ai-crypto-trader' ); ?>
			</span>
		</div>
		<div class="act-kpi-card">
			<span class="act-kpi-label"><?php esc_html_e( 'Mode', 'ai-crypto-trader' ); ?></span>
			<span class="act-kpi-value" style="font-size:14px;">
				<?php
				$exchange = isset( $settings['exchange_name'] ) ? strtoupper( $settings['exchange_name'] ) : 'PAPER';
				echo 'PAPER' === $exchange ? '<span class="act-badge paper">PAPER</span>' : '<span class="act-badge live">LIVE</span>';
				?>
			</span>
		</div>
	</div>

	<!-- Action Buttons -->
	<div class="act-actions-row">
		<button id="act-run-cycle" class="button button-primary">
			<span class="dashicons dashicons-controls-play" style="vertical-align:middle;"></span>
			<?php esc_html_e( 'Run Analysis Now', 'ai-crypto-trader' ); ?>
		</button>
		<button id="act-reset-wallet" class="button button-secondary" style="margin-left:8px;">
			<span class="dashicons dashicons-trash" style="vertical-align:middle;"></span>
			<?php esc_html_e( 'Reset Paper Wallet', 'ai-crypto-trader' ); ?>
		</button>
		<span id="act-cycle-status" style="margin-left:12px;font-style:italic;color:#666;"></span>
	</div>

	<!-- Portfolio Chart + Holdings -->
	<div class="act-two-col">
		<div class="act-panel act-panel-chart">
			<h2><?php esc_html_e( 'Portfolio Value Over Time', 'ai-crypto-trader' ); ?></h2>
			<?php if ( count( $snapshots ) < 2 ) : ?>
				<p class="act-empty"><?php esc_html_e( 'Not enough data yet. Run the analysis a few times to see the chart.', 'ai-crypto-trader' ); ?></p>
			<?php else : ?>
				<canvas id="act-portfolio-chart" height="200"></canvas>
				<script>
				(function(){
					var snapshots = <?php echo wp_json_encode( array_reverse( $snapshots ) ); ?>;
					var labels = snapshots.map(function(s){ return s.snapshot_time.substring(0,10); });
					var values = snapshots.map(function(s){ return parseFloat(s.total_value_usd); });
					var ctx = document.getElementById('act-portfolio-chart').getContext('2d');
					new Chart(ctx, {
						type: 'line',
						data: {
							labels: labels,
							datasets: [{
								label: 'Portfolio Value (USD)',
								data: values,
								borderColor: '#2271b1',
								backgroundColor: 'rgba(34,113,177,0.08)',
								fill: true,
								tension: 0.3,
							}]
						},
						options: {
							responsive: true,
							plugins: { legend: { display: false } },
							scales: {
								y: { beginAtZero: false, ticks: { callback: function(v){ return '$'+v.toFixed(2); } } }
							}
						}
					});
				})();
				</script>
			<?php endif; ?>
		</div>

		<div class="act-panel">
			<h2><?php esc_html_e( 'Current Holdings', 'ai-crypto-trader' ); ?></h2>
			<?php if ( empty( $holdings ) ) : ?>
				<p class="act-empty"><?php esc_html_e( 'No holdings yet. Reset the wallet to seed an initial balance.', 'ai-crypto-trader' ); ?></p>
			<?php else : ?>
				<table class="act-table">
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
							<td><?php echo esc_html( number_format( $h['amount'], 8 ) ); ?></td>
							<td><?php echo $h['avg_buy_price'] > 0 ? '$' . esc_html( number_format( $h['avg_buy_price'], 4 ) ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<!-- Manual Trade + Recent Signals -->
	<div class="act-two-col">
		<div class="act-panel">
			<h2><?php esc_html_e( 'Manual Trade', 'ai-crypto-trader' ); ?></h2>
			<form id="act-manual-trade-form">
				<table class="form-table" style="max-width:400px;">
					<tr>
						<th><?php esc_html_e( 'Pair', 'ai-crypto-trader' ); ?></th>
						<td>
							<input type="text" id="act-trade-pair" placeholder="BTC/USDT" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Side', 'ai-crypto-trader' ); ?></th>
						<td>
							<select id="act-trade-side">
								<option value="buy"><?php esc_html_e( 'Buy', 'ai-crypto-trader' ); ?></option>
								<option value="sell"><?php esc_html_e( 'Sell', 'ai-crypto-trader' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Amount', 'ai-crypto-trader' ); ?></th>
						<td>
							<input type="number" id="act-trade-amount" step="0.0001" min="0.0001" placeholder="0.01" class="regular-text" />
						</td>
					</tr>
				</table>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Execute Trade', 'ai-crypto-trader' ); ?>
				</button>
				<p id="act-trade-status" style="margin-top:8px;font-style:italic;"></p>
			</form>
		</div>

		<div class="act-panel">
			<h2><?php esc_html_e( 'Latest AI Signals', 'ai-crypto-trader' ); ?></h2>
			<?php if ( empty( $recent_signals ) ) : ?>
				<p class="act-empty"><?php esc_html_e( 'No signals yet.', 'ai-crypto-trader' ); ?></p>
			<?php else : ?>
				<table class="act-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'ai-crypto-trader' ); ?></th>
							<th><?php esc_html_e( 'Asset', 'ai-crypto-trader' ); ?></th>
							<th><?php esc_html_e( 'Signal', 'ai-crypto-trader' ); ?></th>
							<th><?php esc_html_e( 'Confidence', 'ai-crypto-trader' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $recent_signals as $s ) : ?>
						<tr>
							<td><?php echo esc_html( substr( $s['analysis_time'], 0, 16 ) ); ?></td>
							<td><?php echo esc_html( $s['asset'] ); ?></td>
							<td>
								<span class="act-signal-badge act-signal-<?php echo esc_attr( $s['signal'] ); ?>">
									<?php echo esc_html( strtoupper( $s['signal'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $s['confidence'] ); ?>%</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-crypto-trader-signals' ) ); ?>">
					<?php esc_html_e( 'View all signals →', 'ai-crypto-trader' ); ?>
				</a></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Recent Trades -->
	<div class="act-panel">
		<h2><?php esc_html_e( 'Recent Trades', 'ai-crypto-trader' ); ?></h2>
		<?php if ( empty( $recent_trades ) ) : ?>
			<p class="act-empty"><?php esc_html_e( 'No trades executed yet.', 'ai-crypto-trader' ); ?></p>
		<?php else : ?>
			<table class="act-table widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'ai-crypto-trader' ); ?></th>
						<th><?php esc_html_e( 'Pair', 'ai-crypto-trader' ); ?></th>
						<th><?php esc_html_e( 'Side', 'ai-crypto-trader' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'ai-crypto-trader' ); ?></th>
						<th><?php esc_html_e( 'Price', 'ai-crypto-trader' ); ?></th>
						<th><?php esc_html_e( 'Total', 'ai-crypto-trader' ); ?></th>
						<th><?php esc_html_e( 'PnL', 'ai-crypto-trader' ); ?></th>
						<th><?php esc_html_e( 'Strategy', 'ai-crypto-trader' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $recent_trades as $t ) : ?>
					<tr>
						<td><?php echo esc_html( substr( $t['trade_time'], 0, 16 ) ); ?></td>
						<td><?php echo esc_html( $t['pair'] ); ?></td>
						<td>
							<span class="act-signal-badge act-signal-<?php echo esc_attr( $t['side'] ); ?>">
								<?php echo esc_html( strtoupper( $t['side'] ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( number_format( $t['amount'], 6 ) ); ?></td>
						<td>$<?php echo esc_html( number_format( $t['price'], 2 ) ); ?></td>
						<td>$<?php echo esc_html( number_format( $t['total_value'], 2 ) ); ?></td>
						<td class="<?php echo floatval( $t['profit_loss'] ) >= 0 ? 'act-positive' : 'act-negative'; ?>">
							<?php
							$pnl = floatval( $t['profit_loss'] );
							if ( 'sell' === $t['side'] ) {
								echo ( $pnl >= 0 ? '+$' : '-$' ) . esc_html( number_format( abs( $pnl ), 2 ) );
							} else {
								echo '—';
							}
							?>
						</td>
						<td><?php echo esc_html( $t['strategy'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-crypto-trader-trades' ) ); ?>">
				<?php esc_html_e( 'View full trade history →', 'ai-crypto-trader' ); ?>
			</a></p>
		<?php endif; ?>
	</div>
</div>
