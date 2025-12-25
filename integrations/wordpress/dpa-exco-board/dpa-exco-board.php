<?php
/**
 * Plugin Name: DPA Exco Board (Daraima’s Parallel Agents - Professional Lifecycle)
 * Version: 11.0.0
 * Description: The definitive DPA-I experience. High-authority, professional expertise, and execution-focused.
 * Author: Daraima Bassey
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Ensure settings are loaded
require_once plugin_dir_path( __FILE__ ) . 'dpa-settings.php';

/**
 * 1. ENQUEUE ASSETS & LOCALIZATION
 * Jealously maintaining all 8.0+ CSS/JS hooks
 */
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'dpa-style', plugin_dir_url( __FILE__ ) . 'assets/style.css', array(), '11.0' );
    wp_enqueue_script( 'dpa-app', plugin_dir_url( __FILE__ ) . 'assets/app.js', array('jquery'), '11.0', true );
    wp_localize_script( 'dpa-app', 'dpa_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('dpa_nonce')
    ));
});

/**
 * 2. CHAT INTERFACE SHORTCODE
 * Maintains the DPA-I War Room aesthetic
 */
add_shortcode( 'dpa_exco_chat', function() {
    ob_start(); ?>
    <div id="dpa-board-container">
        <div class="dpa-header">
            <div class="dpa-status-dot"></div>
            <div class="dpa-header-text">
                <h3>🏢 DPA Executive Board</h3>
                <span>Daraima’s Parallel Agents (Active)</span>
            </div>
        </div>
        
        <div id="dpa-chat-stream">
            <div class="dpa-message agent-daraima">
                <div class="dpa-avatar">👑</div>
                <div class="dpa-bubble">
                    <strong>Daraima:</strong> Welcome to the War Room. We’ve managed 60+ global projects; we don't have time for fluff. Pitch us your concept, or ask a specific agent for an execution plan.
                </div>
            </div>
        </div>

        <div class="dpa-input-area">
            <textarea id="dpa-user-pitch" placeholder="Present your pitch or address an agent (e.g. 'Moses, I need a schema')..."></textarea>
            <button id="dpa-submit-btn">Consult the Board</button>
        </div>
    </div>
    <?php return ob_get_clean();
});

/**
 * 3. THE ANALYTICAL ENGINE (DPA-I CORE)
 * This is the 117+ line logic restored and expanded.
 */
add_action( 'wp_ajax_dpa_process_pitch', 'dpa_run_master_simulation' );
add_action( 'wp_ajax_nopriv_dpa_process_pitch', 'dpa_run_master_simulation' );

function dpa_run_master_simulation() {
    // Security First
    check_ajax_referer( 'dpa_nonce', 'nonce' );
    
    $pitch = isset($_POST['pitch']) ? sanitize_textarea_field($_POST['pitch']) : '';
    if (empty($pitch)) {
        wp_send_json_error(['message' => 'The board requires a pitch to begin.']);
    }

    $active_engine = get_option('dpa_active_engine', 'gemini');
    
    /**
     * THE STRICT DPA-I SYSTEM PROMPT (STRICT PROFESSIONALS)
     * All vectors, friction targets, and expertise levels included.
     */
    $system_prompt = "
    # SYSTEM SETTING: THE EXECUTIVE BOARD (DPA-I HUMANIZED)
    
    ## 1. THE VIBE
    You are a Board of Executives with 10+ years of elite experience. Use conversational English, professional idioms, and sharp expertise. 
    NO ROBOTIC HEADERS. NO 'Phase 1'. Talk like you are in a high-stakes WhatsApp/Slack thread.
    
    ## 2. THE CAST (DARaima’S PARALLEL AGENTS)
    - 👑 Daraima (Lead): CEO. Strategic facilitator. Opens and pivots the meeting to execution.
    - ⚖️ Justice (CFO): ROI-obsessed. Thinks in EBITDA, CAC, LTV. Interrupts expensive ideas.
    - 💻 Moses (CTO): Pragmatist. Hates hype. Speaks in Tech Debt, SQL, Python, and Latency.
    - 🇩🇪 Clovet (Architect): Scalability master. 10-year horizons. Precision-engineered systems.
    - 🎯 Emma (Product/Growth): User psychology expert. UX is everything. Calls devs 'robots'.

    ## 3. THE HIDDEN FLOW (DPA-I PROTOCOL)
    - THE ROAST: Dissect the idea with elite industry knowledge.
    - THE FRICTION: Agents MUST argue. Justice vs Emma (Cost vs Magic). Moses vs Clovet (Stable vs Scale).
    - INTERACTIVE: If the user addresses one person, they lead, but others provide friction.
    - THE VERDICT: Daraima provides the 'Scorecard on a Napkin' Table.
    - THE WAY FORWARD (MANDATORY): Daraima MUST conclude by asking what the user wants next, suggesting 3 specific action plans (e.g., Technical Roadmap, Financial Forecast, or UX Wireframe).

    OUTPUT: Return ONLY a raw JSON array: [{\"agent\":\"Name\",\"emoji\":\"Emoji\",\"message\":\"Text\"}]";

    // API Routing
    if ($active_engine === 'xai') {
        $api_key = trim(get_option('dpa_xai_api_key'));
        $url = "https://api.x.ai/v1/chat/completions";
        $payload = [
            "model" => "grok-3-mini",
            "messages" => [
                ["role" => "system", "content" => $system_prompt],
                ["role" => "user", "content" => "THE PITCH: " . $pitch]
            ],
            "temperature" => 0.85,
            "max_tokens" => 4000,
            "reasoning_effort" => "high"
        ];
        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json'
        ];
    } else {
        $api_key = trim(get_option('dpa_gemini_api_key'));
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $api_key;
        $payload = [
            "contents" => [["parts" => [["text" => $system_prompt . "\nUSER INPUT: " . $pitch]]]]
        ];
        $headers = ['Content-Type' => 'application/json'];
    }

    // Remote Request with extended timeout for deep reasoning
    $response = wp_remote_post($url, [
        'headers' => $headers,
        'body'    => json_encode($payload),
        'timeout' => 120,
        'sslverify' => false
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Connection to the Boardroom failed: ' . $response->get_error_message()]);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Parsing Logic for Grok vs Gemini
    $raw_text = ($active_engine === 'xai') 
        ? ($data['choices'][0]['message']['content'] ?? '') 
        : ($data['candidates'][0]['content']['parts'][0]['text'] ?? '');

    if (empty($raw_text)) {
        wp_send_json_error(['message' => 'The Board remained silent. Check API logs.']);
    }

    // JSON Sanitization
    $clean_json = preg_replace('/^```json\s*|\s*```$/m', '', trim($raw_text));
    $conversation = json_decode($clean_json, true);

    if (!$conversation || !is_array($conversation)) {
        wp_send_json_error(['message' => 'The Board produced an unreadable transcript. Requesting a re-take.']);
    }

    // Success Return
    wp_send_json_success(['conversation' => $conversation]);
    wp_die();
}
