# Source Link & Episode Button Automator (WordPress Plugin v2.2.0)

**Automate your RSS imported pending posts into fully published posts with responsive episode buttons.**

## Problem Solved
Your WordPress site imports posts via an RSS post importer and marks them as **"Pending"**.
At the end of the post paragraphs, the importer places a hyperlink named `"Source Link"`, for example:
`https://mydverse.com/2026/09/our-universe-korean-drama-in-hindi-dubbed/`

Before this plugin, you had to:
1. Manually open each pending post
2. Copy the source link
3. Paste it into an external resolver tool to bypass shorteners and hops
4. Extract the episode buttons from the resolved Blogspot destination (`https://mydverse02.blogspot.com/p/*.html`)
5. Copy the generated HTML
6. Manually replace `"Source Link"` with the HTML
7. Mark the post as **Ready** or **Published**

## What This Plugin Automates (v2.2.0)
- 🔍 **Auto-Detection**: Automatically finds the `"Source Link"` anchor text or URLs matching `mydverse.com/YYYY/MM/` in pending posts (with support for flexible whitespace, bold tags, and metadata fields).
- ⚡ **URL Resolution & Shortlink Bypass**: Recursively resolves shortened URLs (`shrt.sohojgyan.com`, `go.sohojgyan.com`, AdLinkFly forms, safelink interstitials, meta refreshes, and JS redirects) to the final Blogspot destination format: `https://mydverse02.blogspot.com/p/flp-120926.html` / `https://mydverse02.blogspot.com/p/mbmb-030826.html`.
- 🎯 **Action & Episode Button Extraction**: Scrapes the resolved destination Blogspot page and isolates multi-episode links (Google Drive, Mega, servers, 480p/720p/1080p, etc.).
- 🎨 **Responsive Mobile-Width Episode Buttons**: Generates responsive buttons (alternating filled blue & outlined blue, 20px margins, auto height, `- Session End -` footer) wrapped in isolated Gutenberg Custom HTML blocks.
- 🔄 **In-Place Replacement**: Strips out only the `"Source Link"` hyperlink and replaces it with the styled episode buttons.
- 🚀 **"Ready" Status Support & Auto-Publishing**: Marks processed posts as `ready` or changes status from `pending` to `publish`.
- ⏰ **WP-Cron Automation**: Runs automatically in the background at your selected frequency with configurable batch limits.
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
