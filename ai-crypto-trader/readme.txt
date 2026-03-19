=== AI Crypto Trader ===
Contributors: aicryptotrader
Tags: cryptocurrency, trading, ai, bitcoin, wallet, forex, commodities, openai
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI-powered wallet manager that automatically analyses and trades cryptocurrencies, forex, stocks, and commodities using GPT-4o and live market data.

== Description ==

**AI Crypto Trader** turns your WordPress site into a fully automated trading control centre. The plugin uses OpenAI's GPT-4o model combined with real-time market data, technical indicators, and financial news to make intelligent buy/sell/hold decisions across cryptocurrencies, forex pairs, commodities, and stocks.

= Key Features =

* **AI-Powered Signals** — GPT-4o analyses price data, RSI, MACD, SMA crossovers, volume trends, and news sentiment to generate confident trading signals.
* **Multi-Asset Support** — Cryptocurrencies (via CoinGecko), stocks & forex (via Alpha Vantage), commodities (oil, gold, wheat, etc.).
* **Financial News Analysis** — Aggregates news from NewsAPI and CryptoCompare to factor sentiment into every decision.
* **Risk Management** — Configurable stop-loss, take-profit, max position size, and maximum open positions.
* **Paper Trading Mode** — Test strategies safely with a simulated wallet before going live.
* **Live Exchange Integration** — Optional connection to Binance, Coinbase Pro, or Kraken via REST API.
* **Interactive Dashboard** — Real-time portfolio chart (Chart.js), KPI cards, manual trade form, and signals log.
* **Frontend Shortcodes** — Embed your portfolio, trade history, or AI signals on any page/post.
* **Email Notifications** — Get notified via email every time a trade is executed.
* **WP-Cron Scheduling** — Automatic analysis every 15 minutes, 30 minutes, hourly, every 4 hours, or daily.

= Shortcodes =

* `[act_portfolio]` — Display current holdings and portfolio value.
* `[act_trade_history limit="20"]` — Show recent trade history.
* `[act_signals limit="10"]` — Display latest AI trading signals.

= How It Works =

1. Configure your API keys (OpenAI, CoinGecko, Alpha Vantage, NewsAPI) in the Settings page.
2. Add the trading pairs you want to monitor (e.g. BTC/USDT, ETH/USDT).
3. Set your risk parameters (stop-loss %, take-profit %, max trade size).
4. Enable automated trading or use the "Run Analysis Now" button to test manually.
5. The plugin fetches live prices, OHLCV data, and news — then asks GPT-4o for a decision.
6. Trades are recorded in the wallet and shown in the Dashboard.

= Data Sources =

* **CoinGecko** — Cryptocurrency prices, OHLCV, market cap, trending coins (free tier available).
* **Alpha Vantage** — Stocks, forex rates, commodities, RSI, and MACD (free API key required).
* **NewsAPI** — Financial news headlines (free API key required; falls back to CryptoCompare).
* **CryptoCompare** — Crypto news (no key required, used as fallback).
* **OpenAI** — GPT-4o for trade signal generation (API key required).

= Supported Exchanges (Live Trading) =

* Binance
* Coinbase Pro
* Kraken

**⚠️ Risk Disclaimer:** Automated trading involves significant financial risk. Past performance does not guarantee future results. Always start with Paper Trading mode and never invest more than you can afford to lose.

== Installation ==

1. Upload the `ai-crypto-trader` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **AI Trader → Settings** and enter your API keys.
4. Set up your trading pairs and risk parameters.
5. Use **AI Trader → Dashboard** to monitor performance and run manual analyses.

== Frequently Asked Questions ==

= Do I need an OpenAI API key? =

No — without a key the plugin falls back to a built-in rule-based engine using RSI, SMA crossovers, and news sentiment. For best results, provide a GPT-4o key.

= Is real money at risk? =

Only if you configure a live exchange (Binance, Coinbase, or Kraken) and enable automated trading. The default mode is **Paper Trading** which uses no real funds.

= How often does it analyse markets? =

You can choose: every 15 minutes, 30 minutes, hourly, every 4 hours, or daily.

= What technical indicators are used? =

RSI (14), SMA-20, SMA-50, volume trend, and MACD signal (via Alpha Vantage).

== Screenshots ==

1. Dashboard – KPI cards, portfolio chart, and recent trades.
2. Settings – API keys, exchange configuration, and risk parameters.
3. Trade History – Full history with PnL per trade.
4. AI Signals Log – Every analysis with reasoning and confidence.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
