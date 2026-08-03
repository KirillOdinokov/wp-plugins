(function() {
    'use strict';

    var STORAGE_KEY    = 'odinokov_ai_chat_data';
    var STORAGE_TTL    = 24 * 60 * 60 * 1000; // 24 hours

    function storageLoad() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            var data = JSON.parse(raw);
            if (!data || !data._ts) return null;
            if (Date.now() - data._ts > STORAGE_TTL) { localStorage.removeItem(STORAGE_KEY); return null; }
            return data;
        } catch(e) { return null; }
    }

    function storageSave(data) {
        try {
            data._ts = Date.now();
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch(e) {}
    }

    function storageClear() {
        try { localStorage.removeItem(STORAGE_KEY); } catch(e) {}
    }

    var restored = storageLoad();

    var triggerGroup  = document.getElementById('odinokov-ai-trigger-group');
    var trigger       = document.getElementById('odinokov-ai-trigger');
    var subButtons    = document.getElementById('odinokov-ai-sub-buttons');
    var subAi         = document.getElementById('odinokov-ai-sub-ai');
    var subHuman      = document.getElementById('odinokov-ai-sub-human');
    var overlay       = document.getElementById('odinokov-ai-overlay');
    var panel         = document.getElementById('odinokov-ai-panel');
    var messagesEl    = document.getElementById('odinokov-ai-messages');
    var inputEl       = document.getElementById('odinokov-ai-input');
    var sendBtn       = document.getElementById('odinokov-ai-send');
    var inputArea     = inputEl ? inputEl.closest('.odinokov-ai-input-area') : null;
    var welcomeEl     = document.getElementById('odinokov-ai-welcome');
    var tabs          = document.getElementById('odinokov-ai-tabs');
    var suggestionsEl = document.getElementById('odinokov-ai-suggestions');
    var captchaEl     = document.getElementById('odinokov-ai-captcha');
    var captchaQ      = document.getElementById('odinokov-ai-captcha-question');
    var captchaInput  = document.getElementById('odinokov-ai-captcha-input');
    var captchaBtn    = document.getElementById('odinokov-ai-captcha-btn');
    var captchaError  = document.getElementById('odinokov-ai-captcha-error');

    if (!trigger || !panel || !messagesEl) return;

    var isOpen        = false;
    var subVisible    = false;
    var captchaPassed = !!(restored && restored.captcha === true);
    var captchaHash   = '';
    var chatMode      = (restored && restored.chatMode) || 'ai';
    var aiMessages    = (restored && Array.isArray(restored.aiMessages)) ? restored.aiMessages : [];
    var humanMessages = (restored && Array.isArray(restored.humanMessages)) ? restored.humanMessages : [];
    var inactivityTimer = null;
    var INACTIVITY_MS   = 60 * 60 * 1000;

    if (odinokovAi.captcha) {
        captchaQ.textContent = odinokovAi.captcha.question;
        captchaHash = odinokovAi.captcha.hash;
    }

    function initHumanStub() {
        if (humanMessages.length > 0) return;
        var msg = odinokovAi.humanMessage || 'К сожалению, этот функционал пока дорабатывается.';
        humanMessages = [{ role: 'assistant', text: msg }];
        persistState();
    }
    initHumanStub();

    function persistState() {
        storageSave({
            captcha: captchaPassed,
            chatMode: chatMode,
            aiMessages: aiMessages,
            humanMessages: humanMessages
        });
    }

    function resetTimer() {
        if (inactivityTimer) clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(sendTranscript, INACTIVITY_MS);
    }

    function sendTranscript() {
        if (inactivityTimer) clearTimeout(inactivityTimer);
        inactivityTimer = null;
        var all = aiMessages.concat(humanMessages);
        if (!all.length) return;

        var copy = all.slice();
        var fd = new FormData();
        fd.append('action', 'odinokov_ai_send_transcript');
        fd.append('nonce', odinokovAi.nonce);
        fd.append('messages', JSON.stringify(copy));
        fetch(odinokovAi.ajaxUrl, { method: 'POST', body: fd });
    }

    // --- Visibility helpers ---

    function showSub() { subVisible = true;  subButtons.classList.add('odinokov-ai-sub-buttons--visible'); }
    function hideSub(){ subVisible = false; subButtons.classList.remove('odinokov-ai-sub-buttons--visible'); }

    function updateTabsUI() {
        if (!tabs) return;
        var btns = tabs.querySelectorAll('.odinokov-ai-tab');
        for (var i = 0; i < btns.length; i++) {
            btns[i].classList.toggle('odinokov-ai-tab--active', btns[i].dataset.mode === chatMode);
        }
    }

    function updateInputVisibility() {
        if (!inputArea) return;
        if (chatMode === 'human') {
            inputArea.style.display = 'none';
        } else {
            inputArea.style.display = '';
        }
    }

    function setMode(mode) {
        if (mode === chatMode) return;
        if (mode !== 'ai' && mode !== 'human') return;
        chatMode = mode;
        updateTabsUI();
        clearMessages();
        if (mode === 'ai') {
            restoreAiChat();
        } else {
            restoreHumanChat();
        }
        updateInputVisibility();
        persistState();
        scrollDown();
    }

    function restoreAiChat() {
        hideWelcome();
        if (!captchaPassed) {
            showCaptcha();
            return;
        }
        if (!aiMessages.length) {
            showWelcome();
            return;
        }
        for (var i = 0; i < aiMessages.length; i++) {
            renderMessage(aiMessages[i].role, aiMessages[i].text);
        }
        if (inputEl) { inputEl.disabled = false; inputEl.focus(); }
        if (sendBtn) sendBtn.disabled = false;
    }

    function restoreHumanChat() {
        hideWelcome();
        hideCaptcha();
        for (var i = 0; i < humanMessages.length; i++) {
            renderMessage(humanMessages[i].role, humanMessages[i].text);
        }
    }

    function clearMessages() {
        var children = messagesEl.querySelectorAll('.odinokov-ai-message');
        for (var i = 0; i < children.length; i++) children[i].remove();
    }

    function showCaptcha()   { if (captchaEl) captchaEl.style.display = ''; }
    function hideCaptcha()   { if (captchaEl) captchaEl.style.display = 'none'; }
    function showWelcome()   { if (welcomeEl) { welcomeEl.style.display = ''; renderSuggestions(); } }
    function hideWelcome()   { if (welcomeEl) welcomeEl.style.display = 'none'; }

    // --- Open / Close ---

    function openPanel(startMode) {
        isOpen = true;
        hideSub();
        if (startMode) chatMode = startMode;
        updateTabsUI();
        trigger.classList.add('odinokov-ai-trigger--open');
        overlay.classList.add('odinokov-ai-overlay--visible');
        panel.classList.add('odinokov-ai-panel--open');
        trigger.setAttribute('aria-label', 'Закрыть чат');
        overlay.setAttribute('aria-hidden', 'false');
        panel.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        clearMessages();
        if (chatMode === 'ai') restoreAiChat();
        else restoreHumanChat();
        updateInputVisibility();
        scrollDown();

        if (captchaPassed && chatMode === 'ai') {
            setTimeout(function() { if (inputEl) inputEl.focus(); }, 400);
            resetTimer();
        } else if (!captchaPassed && chatMode === 'ai') {
            setTimeout(function() { if (captchaInput) captchaInput.focus(); }, 400);
        }
    }

    function closePanel() {
        isOpen = false;
        trigger.classList.remove('odinokov-ai-trigger--open');
        overlay.classList.remove('odinokov-ai-overlay--visible');
        panel.classList.remove('odinokov-ai-panel--open');
        trigger.setAttribute('aria-label', 'Открыть чат');
        overlay.setAttribute('aria-hidden', 'true');
        panel.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        sendTranscript();
    }

    // --- Event listeners ---

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        if (isOpen) closePanel();
        else if (subVisible) hideSub();
        else showSub();
    });

    overlay.addEventListener('click', function() {
        if (isOpen) closePanel();
        if (subVisible) hideSub();
    });

    if (subAi) subAi.addEventListener('click', function(e) { e.stopPropagation(); openPanel('ai'); });
    if (subHuman) subHuman.addEventListener('click', function(e) { e.stopPropagation(); openPanel('human'); });

    document.addEventListener('click', function(e) {
        if (subVisible && triggerGroup && !triggerGroup.contains(e.target)) hideSub();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { if (isOpen) closePanel(); if (subVisible) hideSub(); }
    });

    // --- Tabs ---

    if (tabs) {
        tabs.addEventListener('click', function(e) {
            var tab = e.target.closest('.odinokov-ai-tab');
            if (!tab) return;
            setMode(tab.dataset.mode);
        });
    }

    // --- Suggestions ---

    function renderSuggestions() {
        if (!suggestionsEl) return;
        suggestionsEl.innerHTML = '';
        var items = odinokovAi.suggestions || [];
        for (var i = 0; i < items.length; i++) {
            (function(text) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'odinokov-ai-suggestion';
                btn.textContent = text;
                btn.addEventListener('click', function() {
                    if (!captchaPassed || chatMode !== 'ai') return;
                    if (inputEl) inputEl.value = text;
                    sendMessage();
                });
                suggestionsEl.appendChild(btn);
            })(items[i]);
        }
    }

    // --- Captcha ---

    function passCaptcha() {
        captchaPassed = true;
        hideCaptcha();
        showWelcome();
        updateInputVisibility();
        if (inputEl) { inputEl.disabled = false; inputEl.focus(); }
        if (sendBtn) sendBtn.disabled = false;
        persistState();
        resetTimer();
    }

    captchaBtn && captchaBtn.addEventListener('click', function() {
        var answer = captchaInput.value.trim();
        if (!answer) { captchaError.textContent = 'Введите ответ'; return; }
        captchaBtn.disabled = true; captchaInput.disabled = true; captchaError.textContent = '';

        var fd = new FormData();
        fd.append('action', 'odinokov_ai_verify_captcha');
        fd.append('nonce', odinokovAi.nonce);
        fd.append('answer', answer);
        fd.append('hash', captchaHash);

        fetch(odinokovAi.ajaxUrl, { method: 'POST', body: fd })
        .then(function(r) { return r.json().then(function(d) { if (!r.ok || !d.success) throw new Error(d.data.detail || 'Неверно'); return d; }); })
        .then(function() { passCaptcha(); })
        .catch(function(err) {
            captchaError.textContent = err.message;
            captchaBtn.disabled = false; captchaInput.disabled = false; captchaInput.value = ''; captchaInput.focus();
        });
    });

    captchaInput && captchaInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); captchaBtn.click(); }
    });

    // --- Messages ---

    function escapeHtml(text) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(text));
        return d.innerHTML;
    }

    function renderMessage(role, text) {
        var div = document.createElement('div');
        div.className = 'odinokov-ai-message odinokov-ai-message--' + role;
        div.innerHTML =
            '<div class="odinokov-ai-avatar">' + (role === 'user' ? '&#128100;' : '&#9881;') + '</div>' +
            '<div class="odinokov-ai-bubble">' + escapeHtml(text) + '</div>';
        messagesEl.appendChild(div);
    }

    function addMessage(role, text) {
        hideWelcome();
        if (chatMode === 'ai') aiMessages.push({ role: role, text: text });
        else humanMessages.push({ role: role, text: text });
        renderMessage(role, text);
        persistState();
        scrollDown();
        resetTimer();
    }

    function addTyping() {
        var d = document.createElement('div');
        d.className = 'odinokov-ai-message odinokov-ai-message--assistant';
        d.id = 'odinokov-ai-typing';
        d.innerHTML = '<div class="odinokov-ai-avatar">&#9881;</div><div class="odinokov-ai-bubble"><div class="odinokov-ai-typing"><span></span><span></span><span></span></div></div>';
        messagesEl.appendChild(d);
        scrollDown();
    }

    function removeTyping() {
        var e = document.getElementById('odinokov-ai-typing');
        if (e) e.remove();
    }

    function scrollDown() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function sendMessage() {
        if (!captchaPassed || chatMode !== 'ai') return;
        if (!inputEl) return;
        var text = inputEl.value.trim();
        if (!text) return;
        inputEl.value = '';
        if (sendBtn) sendBtn.disabled = true;
        inputEl.disabled = true;

        addMessage('user', text);
        addTyping();

        var fd = new FormData();
        fd.append('action', 'odinokov_ai_chat');
        fd.append('nonce', odinokovAi.nonce);
        fd.append('message', text);

        fetch(odinokovAi.ajaxUrl, { method: 'POST', body: fd })
        .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.data.detail || 'Ошибка'); return d; }); })
        .then(function(d) { removeTyping(); addMessage('assistant', d.data.reply); })
        .catch(function(e) { removeTyping(); addMessage('assistant', 'Ошибка: ' + e.message); })
        .finally(function() {
            if (sendBtn) sendBtn.disabled = false;
            if (inputEl) { inputEl.disabled = false; inputEl.focus(); }
        });
    }

    if (sendBtn) sendBtn.addEventListener('click', sendMessage);
    if (inputEl) inputEl.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); sendMessage(); }
    });
})();
