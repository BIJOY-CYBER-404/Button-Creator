# Source Link & Episode Button Automator (WordPress Plugin)

**Automate your RSS imported pending posts into fully published posts with responsive episode buttons.**

## Problem Solved
Your WordPress site imports posts via an RSS post importer and marks them as **"Pending"**.
At the end of the post paragraphs, the importer places a hyperlink named `"Source Link"`, for example:
`https://mydverse.com/2026/09/our-universe-korean-drama-in-hindi-dubbed/`

Before this plugin, you had to:
1. Manually open each pending post
2. Copy the source link
3. Paste it into an external resolver tool to bypass shorteners and hops
4. Extract the episode buttons
5. Copy the generated HTML
6. Manually replace `"Source Link"` with the HTML
7. Mark the post as **Published**

## What This Plugin Automates
- 🔍 **Auto-Detection**: Automatically finds the `"Source Link"` anchor text or URLs matching `mydverse.com/YYYY/MM/` in pending posts.
- ⚡ **URL Resolution & Shortlink Bypass**: Recursively resolves redirects, HTTP 301/302 headers, `<meta http-equiv="refresh">`, JavaScript location redirects, and embedded query params/base64 destinations.
- 🎯 **Action & Button Extraction**: Scrapes the destination page and isolates action/download/episode links.
- 🎨 **Responsive Mobile-Width Episode Buttons**: Generates responsive buttons (alternating filled blue & outlined blue, 20px margins, auto height, `- Session End -` footer).
- 🔄 **In-Place Replacement**: Strips out the `"Source Link"` hyperlink and replaces it with the styled episode buttons.
- 🚀 **Auto-Publishing**: Automatically changes the post status from `pending` to `publish`.
- ⏰ **WP-Cron Automation**: Runs automatically in the background at your selected frequency (Every 5 minutes, 15 minutes, 30 minutes, Hourly, etc.) with configurable batch limits (e.g. 5 or 10 posts per run).
- 🖱️ **Manual One-Click Batch**: Run automation anytime with one click directly from `WordPress Admin -> Episode Automator`.
- 🛡️ **Safety & Rollback**: Preserves original post content and original source URLs in post meta fields (`_slea_original_content`, `_slea_source_url`).

## Installation Guide
1. Download or copy the `source-link-episode-automator` folder into your WordPress site's `wp-content/plugins/` directory:
   ```
   wp-content/plugins/source-link-episode-automator/
   ```
2. Go to **WordPress Admin -> Plugins** and click **Activate** on **Source Link & Episode Button Automator**.
3. Navigate to the new menu item: **Episode Automator** in your WordPress sidebar.
4. Customize your settings (Cron interval, batch size, button prefix) and test with the Live URL Sandbox!
