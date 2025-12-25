<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_init', function() {
    register_setting( 'dpa_options_group', 'dpa_gemini_api_key' );
    register_setting( 'dpa_options_group', 'dpa_xai_api_key' );
    register_setting( 'dpa_options_group', 'dpa_active_engine' );
});

add_action( 'admin_menu', function() {
    add_options_page('DPA Board Config', 'DPA Board', 'manage_options', 'dpa-board', 'dpa_render_settings_page');
});

function dpa_render_settings_page() {
    $active = get_option('dpa_active_engine', 'gemini');
    ?>
    <div class="wrap">
        <h1>🏢 DPA Board Engine Room</h1>
        <form method="post" action="options.php" style="background:#fff; padding:20px; border:1px solid #ccd0d4; max-width: 800px;">
            <?php settings_fields( 'dpa_options_group' ); ?>
            
            <h3>1. Select Active Engine</h3>
            <p>Which AI should run the Board right now?</p>
            <label><input type="radio" name="dpa_active_engine" value="gemini" <?php checked($active, 'gemini'); ?>> <b>Google Gemini</b> (Free Tier available)</label><br>
            <label><input type="radio" name="dpa_active_engine" value="xai" <?php checked($active, 'xai'); ?>> <b>xAI Grok</b> (Requires $5 top-up)</label>
            
            <hr>

            <h3>2. API Credentials</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Gemini API Key</th>
                    <td><input type="password" name="dpa_gemini_api_key" value="<?php echo esc_attr(get_option('dpa_gemini_api_key')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">xAI (Grok) API Key</th>
                    <td><input type="password" name="dpa_xai_api_key" value="<?php echo esc_attr(get_option('dpa_xai_api_key')); ?>" class="regular-text"></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
