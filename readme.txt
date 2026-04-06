=== HSQ-Weather ===
Contributors: Aneeqahmed-hsq
Tags: weather, multi-city, forecast, widget, shortcode
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.0
License: GPLv2 or later

Display weather for multiple cities on your WordPress site. No API key required! Supports small cities like Neelum, Muzaffarabad.

== Description ==

HSQ-Weather allows you to display current weather conditions for multiple cities on your WordPress site. Perfect for travel blogs, news sites, and local directories.

= Features =
* 🌍 Support for ANY city - even small cities like Neelum, Muzaffarabad, Skardu
* 🎨 Light and Dark themes
* ⏱️ Customizable refresh time (5 min to 1 hour)
* 🎨 Custom CSS support
* 💨 Wind speed display
* 💧 Humidity display
* 🌡️ Weather icons (sun, cloud, rain, etc.)
* 📊 Grid layout (2, 3, or 4 columns)
* 🌡️ Celsius/Fahrenheit toggle
* 🚀 No API key required!

== Installation ==

1. Upload the `hsq-weather` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to HSQ-Weather menu and add your cities
4. Use `[hsq_weather]` shortcode on any page/post

== Frequently Asked Questions ==

= Do I need an API key? =
No! The plugin uses free Open-Meteo API which requires no API key.

= Can I add small cities like Neelum? =
Yes! The plugin uses geocoding that works for any city name.

= How many cities can I add? =
Unlimited! Add as many as you want.

= Can I customize the styling? =
Yes! Use the Custom CSS option in settings.

== Shortcodes ==

Basic usage:
`[hsq_weather]`

With 3 columns:
`[hsq_weather columns="3"]`

With 4 columns:
`[hsq_weather columns="4"]`

== Changelog ==

= 1.0.0 =
* Initial release
* Multi-city support
* Dark/light theme
* Custom CSS option
* Weather icons, wind speed, humidity
* No API key required
