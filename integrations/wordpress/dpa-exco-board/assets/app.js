jQuery(document).ready(function($) {
    const $stream = $('#dpa-chat-stream');
    const $btn = $('#dpa-submit-btn');
    const $input = $('#dpa-user-pitch');

    $btn.on('click', async function() {
        const pitch = $input.val().trim();
        if (!pitch) return;

        $btn.prop('disabled', true).text('Consulting Board...');
        
        // Append User Message
        appendMessage('User', 'ðŸ‘¤', pitch, 'user-message');
        $input.val('');
        scrollToBottom();

        try {
            const response = await $.post(dpa_vars.ajax_url, {
                action: 'dpa_process_pitch',
                nonce: dpa_vars.nonce,
                pitch: pitch
            });

            if (response.success && response.data.conversation) {
                await playBoardMeeting(response.data.conversation);
            } else {
                throw new Error(response.data.message || 'Board Deadlock');
            }
        } catch (e) {
            alert(e.message);
        } finally {
            $btn.prop('disabled', false).text('Submit Pitch');
        }
    });

    async function playBoardMeeting(messages) {
        for (const msg of messages) {
            // 1. Show individual typing indicator
            const $typing = $(`<div class="typing-bubble">
                <span class="dot-anim"></span> ${msg.agent} is reviewing...
            </div>`);
            $stream.append($typing);
            scrollToBottom();

            // 2. Realistic interval (3 seconds)
            await new Promise(r => setTimeout(r, 3000));
            $typing.remove();

            // 3. Append the real message
            appendMessage(msg.agent, msg.emoji, msg.message, `agent-${msg.agent.toLowerCase().split(' ')[0]}`);
            scrollToBottom();

            // 4. Pause between agents (2 seconds)
            await new Promise(r => setTimeout(r, 2000));
        }
    }

    function appendMessage(name, emoji, text, typeClass) {
        const formattedText = formatContent(text);
        const html = `
            <div class="dpa-message ${typeClass}">
                <div class="dpa-avatar">${emoji}</div>
                <div class="dpa-bubble">
                    ${typeClass !== 'user-message' ? `<strong>${name}</strong><br>` : ''}
                    ${formattedText}
                </div>
            </div>
        `;
        $stream.append(html);
    }

    function formatContent(text) {
        if (text.includes('|')) {
            // Basic Markdown Table Parser for the Napkin Table
            let lines = text.split('\n');
            let html = '';
            let inTable = false;
            
            lines.forEach(line => {
                if (line.includes('|')) {
                    if (!inTable) { html += '<table>'; inTable = true; }
                    let cells = line.split('|').filter(c => c.trim() !== '');
                    html += '<tr>' + cells.map(c => `<td>${c.trim()}</td>`).join('') + '</tr>';
                } else {
                    if (inTable) { html += '</table>'; inTable = false; }
                    html += line + '<br>';
                }
            });
            return html;
        }
        return text.replace(/\n/g, '<br>');
    }

    function scrollToBottom() {
        $stream.animate({ scrollTop: $stream[0].scrollHeight }, 500);
    }
});
