<?php
/**
 * Admin settings partial.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = (array) get_option( 'act_settings', array() );

function act_s( $key, $default = '' ) {
	global $settings;
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}
?>
<div class="wrap act-settings">
	<h1>
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'AI Crypto Trader — Settings', 'ai-crypto-trader' ); ?>
	</h1>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'act_settings_group' );
		?>

		<!-- API Keys -->
		<div class="act-settings-section">
			<h2><?php esc_html_e( 'API Keys', 'ai-crypto-trader' ); ?></h2>
			<p class="description"><?php esc_html_e( 'All keys are stored encrypted at rest by WordPress. Only share with trusted parties.', 'ai-crypto-trader' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="act_openai_key"><?php esc_html_e( 'OpenAI API Key', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<input type="password" id="act_openai_key" name="act_settings[openai_api_key]"
							value="<?php echo esc_attr( act_s( 'openai_api_key' ) ); ?>"
							class="regular-text" autocomplete="new-password" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: OpenAI platform URL */
								esc_html__( 'Required for AI-powered signals. Get a key at %s', 'ai-crypto-trader' ),
								'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">platform.openai.com</a>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="act_ai_model"><?php esc_html_e( 'AI Model', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<select id="act_ai_model" name="act_settings[ai_model]">
							<?php
							$models = array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo' );
							foreach ( $models as $m ) :
							?>
							<option value="<?php echo esc_attr( $m ); ?>" <?php selected( act_s( 'ai_model', 'gpt-4o' ), $m ); ?>>
								<?php echo esc_html( $m ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="act_coingecko_key"><?php esc_html_e( 'CoinGecko API Key', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<input type="password" id="act_coingecko_key" name="act_settings[coingecko_api_key]"
							value="<?php echo esc_attr( act_s( 'coingecko_api_key' ) ); ?>"
							class="regular-text" autocomplete="new-password" />
						<p class="description"><?php esc_html_e( 'Optional — the free tier works without a key (rate-limited).', 'ai-crypto-trader' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="act_alphavantage_key"><?php esc_html_e( 'Alpha Vantage API Key', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<input type="password" id="act_alphavantage_key" name="act_settings[alpha_vantage_api_key]"
							value="<?php echo esc_attr( act_s( 'alpha_vantage_api_key' ) ); ?>"
							class="regular-text" autocomplete="new-password" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: Alpha Vantage URL */
								esc_html__( 'For stocks, forex, and commodities data. Free key at %s', 'ai-crypto-trader' ),
								'<a href="https://www.alphavantage.co/support/#api-key" target="_blank" rel="noopener noreferrer">alphavantage.co</a>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="act_newsapi_key"><?php esc_html_e( 'NewsAPI Key', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<input type="password" id="act_newsapi_key" name="act_settings[newsapi_key]"
							value="<?php echo esc_attr( act_s( 'newsapi_key' ) ); ?>"
							class="regular-text" autocomplete="new-password" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: NewsAPI URL */
								esc_html__( 'For financial news. Free key at %s. Falls back to CryptoCompare if not set.', 'ai-crypto-trader' ),
								'<a href="https://newsapi.org/register" target="_blank" rel="noopener noreferrer">newsapi.org</a>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Exchange -->
		<div class="act-settings-section">
			<h2><?php esc_html_e( 'Exchange / Trading Mode', 'ai-crypto-trader' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="act_exchange_name"><?php esc_html_e( 'Exchange', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<select id="act_exchange_name" name="act_settings[exchange_name]">
							<option value="paper" <?php selected( act_s( 'exchange_name', 'paper' ), 'paper' ); ?>><?php esc_html_e( 'Paper Trading (No Real Money)', 'ai-crypto-trader' ); ?></option>
							<option value="binance" <?php selected( act_s( 'exchange_name' ), 'binance' ); ?>>Binance</option>
							<option value="coinbase" <?php selected( act_s( 'exchange_name' ), 'coinbase' ); ?>>Coinbase Pro</option>
							<option value="kraken" <?php selected( act_s( 'exchange_name' ), 'kraken' ); ?>>Kraken</option>
						</select>
						<p class="description act-warning"><?php esc_html_e( '⚠️ Live trading uses real funds. Start with Paper Trading to test the system first.', 'ai-crypto-trader' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="act_exchange_api_key"><?php esc_html_e( 'Exchange API Key', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<input type="password" id="act_exchange_api_key" name="act_settings[exchange_api_key]"
							value="<?php echo esc_attr( act_s( 'exchange_api_key' ) ); ?>"
							class="regular-text" autocomplete="new-password" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="act_exchange_api_secret"><?php esc_html_e( 'Exchange API Secret', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<input type="password" id="act_exchange_api_secret" name="act_settings[exchange_api_secret]"
							value="<?php echo esc_attr( act_s( 'exchange_api_secret' ) ); ?>"
							class="regular-text" autocomplete="new-password" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Initial Paper Balance', 'ai-crypto-trader' ); ?></th>
					<td>
						<input type="number" name="act_settings[initial_balance]"
							value="<?php echo esc_attr( act_s( 'initial_balance', 1000 ) ); ?>"
							min="1" step="1" class="small-text" />
						<select name="act_settings[currency]">
							<?php foreach ( array( 'USDT', 'BUSD', 'USD', 'USDC' ) as $c ) : ?>
							<option value="<?php echo esc_attr( $c ); ?>" <?php selected( act_s( 'currency', 'USDT' ), $c ); ?>><?php echo esc_html( $c ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- Trading Pairs -->
		<div class="act-settings-section">
			<h2><?php esc_html_e( 'Trading Pairs', 'ai-crypto-trader' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="act_trading_pairs"><?php esc_html_e( 'Active Pairs', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<textarea id="act_trading_pairs" name="act_settings[trading_pairs]"
							rows="6" class="large-text"><?php echo esc_textarea( implode( "\n", (array) act_s( 'trading_pairs', array( 'BTC/USDT', 'ETH/USDT' ) ) ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One pair per line. Format: BASE/QUOTE (e.g. BTC/USDT).', 'ai-crypto-trader' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Automated Trading', 'ai-crypto-trader' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="act_settings[trading_enabled]" value="1"
								<?php checked( act_s( 'trading_enabled' ) ); ?> />
							<?php esc_html_e( 'Allow the plugin to automatically place trades', 'ai-crypto-trader' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="act_analysis_interval"><?php esc_html_e( 'Analysis Interval', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<select id="act_analysis_interval" name="act_settings[analysis_interval]">
							<option value="15min" <?php selected( act_s( 'analysis_interval', 'hourly' ), '15min' ); ?>><?php esc_html_e( 'Every 15 minutes', 'ai-crypto-trader' ); ?></option>
							<option value="30min" <?php selected( act_s( 'analysis_interval' ), '30min' ); ?>><?php esc_html_e( 'Every 30 minutes', 'ai-crypto-trader' ); ?></option>
							<option value="hourly" <?php selected( act_s( 'analysis_interval', 'hourly' ), 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'ai-crypto-trader' ); ?></option>
							<option value="4hours" <?php selected( act_s( 'analysis_interval' ), '4hours' ); ?>><?php esc_html_e( 'Every 4 hours', 'ai-crypto-trader' ); ?></option>
							<option value="daily" <?php selected( act_s( 'analysis_interval' ), 'daily' ); ?>><?php esc_html_e( 'Daily', 'ai-crypto-trader' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- Risk Management -->
		<div class="act-settings-section">
			<h2><?php esc_html_e( 'Risk Management', 'ai-crypto-trader' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="act_risk_level"><?php esc_html_e( 'Risk Level', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<select id="act_risk_level" name="act_settings[risk_level]">
							<option value="low" <?php selected( act_s( 'risk_level', 'medium' ), 'low' ); ?>><?php esc_html_e( 'Low', 'ai-crypto-trader' ); ?></option>
							<option value="medium" <?php selected( act_s( 'risk_level', 'medium' ), 'medium' ); ?>><?php esc_html_e( 'Medium', 'ai-crypto-trader' ); ?></option>
							<option value="high" <?php selected( act_s( 'risk_level', 'medium' ), 'high' ); ?>><?php esc_html_e( 'High', 'ai-crypto-trader' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="act_max_trade_pct"><?php esc_html_e( 'Max Trade Size (%)', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<input type="number" id="act_max_trade_pct" name="act_settings[max_trade_pct]"
							value="<?php echo esc_attr( act_s( 'max_trade_pct', 10 ) ); ?>"
							min="0.1" max="100" step="0.1" class="small-text" /> %
						<p class="description"><?php esc_html_e( 'Maximum % of total portfolio to invest in a single trade.', 'ai-crypto-trader' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="act_stop_loss_pct"><?php esc_html_e( 'Stop-Loss (%)', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<input type="number" id="act_stop_loss_pct" name="act_settings[stop_loss_pct]"
							value="<?php echo esc_attr( act_s( 'stop_loss_pct', 5 ) ); ?>"
							min="0.1" max="50" step="0.1" class="small-text" /> %
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="act_take_profit_pct"><?php esc_html_e( 'Take-Profit (%)', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<input type="number" id="act_take_profit_pct" name="act_settings[take_profit_pct]"
							value="<?php echo esc_attr( act_s( 'take_profit_pct', 15 ) ); ?>"
							min="0.1" max="500" step="0.1" class="small-text" /> %
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="act_max_open_positions"><?php esc_html_e( 'Max Open Positions', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<input type="number" id="act_max_open_positions" name="act_settings[max_open_positions]"
							value="<?php echo esc_attr( act_s( 'max_open_positions', 5 ) ); ?>"
							min="1" max="50" step="1" class="small-text" />
					</td>
				</tr>
			</table>
		</div>

		<!-- Notifications -->
		<div class="act-settings-section">
			<h2><?php esc_html_e( 'Notifications', 'ai-crypto-trader' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Email Notifications', 'ai-crypto-trader' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="act_settings[enable_notifications]" value="1"
								<?php checked( act_s( 'enable_notifications', true ) ); ?> />
							<?php esc_html_e( 'Send email for each trade executed', 'ai-crypto-trader' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="act_notification_email"><?php esc_html_e( 'Notification Email', 'ai-crypto-trader' ); ?></label></th>
					<td>
						<input type="email" id="act_notification_email" name="act_settings[notification_email]"
							value="<?php echo esc_attr( act_s( 'notification_email', get_option( 'admin_email' ) ) ); ?>"
							class="regular-text" />
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button( __( 'Save Settings', 'ai-crypto-trader' ) ); ?>
	</form>
</div>
