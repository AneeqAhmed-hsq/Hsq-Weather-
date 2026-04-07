=== HSQ-Weather ===
Contributors: hsq
Tags: weather, forecast, multi-city, open-meteo, no-api-key
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful weather plugin for WordPress with multi-city support, no API key required.

== Description ==

HSQ-Weather is a complete weather solution for your WordPress website. It allows you to display weather information for multiple cities with a beautiful grid layout.

= Features =

* **Multi-city support** - Add unlimited cities
* **No API key required** - Uses free Open-Meteo API
* **Direct city search** - Small cities like Neelum, Muzaffarabad supported
* **Grid layout** - 2, 3, or 4 columns responsive
* **Temperature unit toggle** - Celsius/Fahrenheit
* **Dark/Light theme** - User selectable
* **Auto-refresh** - Customizable refresh time (5min, 15min, 30min, 1hour)
* **Custom CSS** - Add your own styling
* **Drag & drop reordering** - Easy city management

= Shortcodes =

`[hsq_weather]` - Display weather with default 3 columns

`[hsq_weather columns="2"]` - Display with 2 columns

`[hsq_weather columns="4"]` - Display with 4 columns

== Installation ==

1. Upload the `hsq-weather` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to HSQ Weather settings page to add cities
4. Use shortcode `[hsq_weather]` on any page or post

== Frequently Asked Questions ==

= Do I need an API key? =

No! The plugin uses Open-Meteo API which is completely free and doesn't require any API key.

= How many cities can I add? =

You can add unlimited cities. There's no restriction.

= Does it work with caching plugins? =

Yes, the plugin uses WordPress transients which work well with most caching plugins.

= Is it mobile responsive? =

Yes, the grid layout is fully responsive. It shows 1 column on mobile, 2 on tablet, and 3-4 on desktop.

== Changelog ==

= 1.0.0 =
* Initial release
* Multi-city weather display
* Grid layout system
* Dark/Light theme toggle
* Auto-refresh feature
* Custom CSS support