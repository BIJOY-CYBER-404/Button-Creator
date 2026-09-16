<?php
/**
 * Generates Embeddable Responsive Mobile-Width Episode Buttons HTML
 * Faithful to the EpisodeButtonsGenerator specs:
 * - Mobile width (calc(100% - 40px), max-width 440px)
 * - 20px Left/Right margins
 * - Height: Auto, min-height 48px
 * - Alternating Blue: Filled (#2563eb) & Outlined with low opacity (rgba(37,99,235,0.08))
 * - Session End text
 */

if (!defined('ABSPATH')) {
    exit;
}

class SLEA_Button_Generator {

    /**
     * Generate complete embeddable HTML string formatted inside a WordPress Gutenberg Custom HTML block.
     *
     * @param array $items List of extracted links
     * @param array $custom_settings Optional override settings
     * @param int   $post_id Optional post ID to guarantee strict isolation & auditability
     * @return string
     */
    public static function generate_html($items, $custom_settings = array(), $post_id = 0) {
        if (empty($items) || !is_array($items)) {
            return '';
        }

        $default_settings = get_option('slea_settings', array());
        $settings = wp_parse_args($custom_settings, $default_settings);

        $prefix           = isset($settings['button_prefix']) ? $settings['button_prefix'] : 'Episode';
        $start_number     = isset($settings['start_number']) ? intval($settings['start_number']) : 1;
        $pad_zeroes       = !empty($settings['pad_zeroes']);
        $open_new_tab     = !empty($settings['open_new_tab']);
        $margin_side      = isset($settings['margin_side']) ? intval($settings['margin_side']) : 20;
        $session_end_text = isset($settings['session_end_text']) ? trim($settings['session_end_text']) : '- Session End -';
        $post_attr        = $post_id ? ' data-slea-post-id="' . intval($post_id) . '"' : '';

        $total_margin = $margin_side * 2;
        $target_attr = $open_new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

        $base_inline_style = "width: calc(100% - {$total_margin}px); max-width: 440px; margin-left: {$margin_side}px; margin-right: {$margin_side}px; height: auto; min-height: 48px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; padding: 13px 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 600; text-align: center; text-decoration: none; border-radius: 8px; transition: all 0.2s ease; cursor: pointer; user-select: none; line-height: 1.35;";

        $buttons_markup = array();

        foreach (array_values($items) as $index => $item) {
            $ep_num = $start_number + $index;
            $num_str = $pad_zeroes ? str_pad($ep_num, 2, '0', STR_PAD_LEFT) : (string)$ep_num;
            $label = trim(($prefix ? $prefix . ' ' : '') . $num_str);
            $url = esc_url($item['url']);
            $is_filled = ($index % 2 === 0);

            if ($is_filled) {
                // Filled Blue
                $style = "{$base_inline_style} background-color: #2563eb; color: #ffffff !important; border: 1.5px solid #2563eb; box-shadow: 0 2px 5px rgba(37, 99, 235, 0.25);";
                $buttons_markup[] = "  <a href=\"{$url}\"{$target_attr} class=\"ep-btn ep-btn-filled\" style=\"{$style}\">" . esc_html($label) . "</a>";
            } else {
                // Outlined Blue with subtle tint
                $style = "{$base_inline_style} background-color: rgba(37, 99, 235, 0.08); color: #2563eb !important; border: 1.5px solid #2563eb;";
                $buttons_markup[] = "  <a href=\"{$url}\"{$target_attr} class=\"ep-btn ep-btn-outlined\" style=\"{$style}\">" . esc_html($label) . "</a>";
            }
        }

        $buttons_html = implode("\n", $buttons_markup);

        $session_end_html = '';
        if ($session_end_text) {
            $session_end_html = "\n  <div class=\"ep-session-end\" style=\"margin-top: 14px; margin-left: {$margin_side}px; margin-right: {$margin_side}px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 700; color: #dc2626; text-align: center; letter-spacing: 0.5px;\">" . esc_html($session_end_text) . "</div>";
        }

        $style_block = "<style>
  .episodes-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    width: 100%;
    margin: 18px auto;
    box-sizing: border-box;
  }
  .ep-btn {
    width: calc(100% - {$total_margin}px);
    max-width: 440px;
    margin-left: {$margin_side}px;
    margin-right: {$margin_side}px;
    height: auto;
    min-height: 48px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 13px 24px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    font-size: 15px;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
    cursor: pointer;
    user-select: none;
    line-height: 1.35;
  }
  .ep-btn-filled {
    background-color: #2563eb;
    color: #ffffff !important;
    border: 1.5px solid #2563eb;
    box-shadow: 0 2px 5px rgba(37, 99, 235, 0.25);
  }
  .ep-btn-filled:hover {
    background-color: #1d4ed8 !important;
    border-color: #1d4ed8 !important;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.35);
  }
  .ep-btn-outlined {
    background-color: rgba(37, 99, 235, 0.08);
    color: #2563eb !important;
    border: 1.5px solid #2563eb;
  }
  .ep-btn-outlined:hover {
    background-color: #2563eb !important;
    color: #ffffff !important;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
  }
  @media (max-width: 480px) {
    .ep-btn {
      width: calc(100% - {$total_margin}px) !important;
      font-size: 14.5px !important;
      padding: 12px 16px !important;
    }
  }
</style>";

        $post_comment = $post_id ? " for Post #{$post_id}" : "";

        // Gutenberg Custom HTML block wrapper (<!-- wp:html --> ... <!-- /wp:html -->)
        return "<!-- wp:html -->\n" .
               "<!-- [START] Auto-Generated Episode Buttons{$post_comment} (Source Link Automator) -->\n" .
               $style_block . "\n" .
               "<div class=\"episodes-container\"{$post_attr}>\n" .
               $buttons_html .
               $session_end_html . "\n" .
               "</div>\n" .
               "<!-- [END] Auto-Generated Episode Buttons{$post_comment} -->\n" .
               "<!-- /wp:html -->";
    }
}
