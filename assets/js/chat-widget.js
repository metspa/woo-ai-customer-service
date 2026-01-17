(function() {
    'use strict';

    function WooAIChatWidget(config) {
        this.config = config;
        this.sessionId = null;
        this.nonce = null;
        this.isOpen = false;
        this.isLoading = false;
        this.hasStarted = false;
        this.messages = [];
        this.pendingImage = null;
    }

    WooAIChatWidget.prototype.init = function() {
        this.createWidget();
        this.attachEventListeners();
        this.restoreSession();
    };

    WooAIChatWidget.prototype.createWidget = function() {
        var self = this;
        var position = this.config.position === 'bottom-left' ? 'left: 20px;' : 'right: 20px;';

        var container = document.createElement('div');
        container.id = 'woo-ai-chat-container';
        container.setAttribute('style', position);
        container.style.setProperty('--woo-ai-primary-color', this.config.primaryColor);
        container.style.setProperty('--woo-ai-secondary-color', this.config.secondaryColor);

        var chatButton = document.createElement('button');
        chatButton.id = 'woo-ai-chat-button';
        chatButton.setAttribute('aria-label', 'Open chat');
        var btnSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        btnSvg.setAttribute('width', '24');
        btnSvg.setAttribute('height', '24');
        btnSvg.setAttribute('viewBox', '0 0 24 24');
        btnSvg.setAttribute('fill', 'currentColor');
        var path1 = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path1.setAttribute('d', 'M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z');
        var path2 = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path2.setAttribute('d', 'M7 9h10v2H7zm0-3h10v2H7z');
        btnSvg.appendChild(path1);
        btnSvg.appendChild(path2);
        chatButton.appendChild(btnSvg);

        var chatWindow = document.createElement('div');
        chatWindow.id = 'woo-ai-chat-window';
        chatWindow.className = 'woo-ai-hidden';

        var header = document.createElement('div');
        header.id = 'woo-ai-chat-header';
        var headerTitle = document.createElement('span');
        headerTitle.textContent = this.config.widgetTitle;
        var closeButton = document.createElement('button');
        closeButton.id = 'woo-ai-chat-close';
        closeButton.setAttribute('aria-label', 'Close chat');
        closeButton.textContent = '\u00D7';
        header.appendChild(headerTitle);
        header.appendChild(closeButton);

        var formSection = document.createElement('div');
        formSection.id = 'woo-ai-chat-form';

        var formTitle = document.createElement('h3');
        formTitle.textContent = this.config.formTitle;

        var formSubtitle = document.createElement('p');
        formSubtitle.textContent = this.config.formSubtitle;

        var form = document.createElement('form');
        form.id = 'woo-ai-lead-form';

        var fields = [
            { name: 'first_name', type: 'text', placeholder: 'First Name *', required: true, autocomplete: 'given-name' },
            { name: 'last_name', type: 'text', placeholder: 'Last Name *', required: true, autocomplete: 'family-name' },
            { name: 'email', type: 'email', placeholder: 'Email *', required: true, autocomplete: 'email' },
            { name: 'phone', type: 'tel', placeholder: 'Phone ' + (this.config.phoneRequired ? '*' : '(optional)'), required: this.config.phoneRequired, autocomplete: 'tel' }
        ];

        fields.forEach(function(field) {
            var input = document.createElement('input');
            input.type = field.type;
            input.name = field.name;
            input.placeholder = field.placeholder;
            input.required = field.required;
            input.autocomplete = field.autocomplete;
            form.appendChild(input);
        });

        var submitBtn = document.createElement('button');
        submitBtn.type = 'submit';
        submitBtn.textContent = 'Start Chat';
        form.appendChild(submitBtn);

        var contactAlt = document.createElement('p');
        contactAlt.className = 'woo-ai-contact-alt';
        contactAlt.appendChild(document.createTextNode('Or contact us directly:'));
        contactAlt.appendChild(document.createElement('br'));
        var emailLink = document.createElement('a');
        emailLink.href = 'mailto:' + this.config.supportEmail;
        emailLink.textContent = this.config.supportEmail;
        contactAlt.appendChild(emailLink);
        contactAlt.appendChild(document.createElement('br'));
        var phoneLink = document.createElement('a');
        phoneLink.href = 'tel:' + this.config.supportPhone;
        phoneLink.textContent = this.config.supportPhone;
        contactAlt.appendChild(phoneLink);

        formSection.appendChild(formTitle);
        formSection.appendChild(formSubtitle);
        formSection.appendChild(form);
        formSection.appendChild(contactAlt);

        var messagesArea = document.createElement('div');
        messagesArea.id = 'woo-ai-chat-messages';
        messagesArea.className = 'woo-ai-hidden';

        // Image preview area (hidden by default)
        var imagePreview = document.createElement('div');
        imagePreview.id = 'woo-ai-image-preview';
        imagePreview.className = 'woo-ai-hidden';
        var previewImg = document.createElement('img');
        previewImg.id = 'woo-ai-preview-img';
        var removeBtn = document.createElement('button');
        removeBtn.id = 'woo-ai-remove-image';
        removeBtn.type = 'button';
        removeBtn.setAttribute('aria-label', 'Remove image');
        removeBtn.textContent = '\u00D7';
        imagePreview.appendChild(previewImg);
        imagePreview.appendChild(removeBtn);

        var inputArea = document.createElement('div');
        inputArea.id = 'woo-ai-chat-input';
        inputArea.className = 'woo-ai-hidden';

        // Hidden file input
        var fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.id = 'woo-ai-file-input';
        fileInput.accept = 'image/jpeg,image/png,image/gif,image/webp';
        fileInput.style.display = 'none';

        // Upload button
        var uploadButton = document.createElement('button');
        uploadButton.id = 'woo-ai-upload-button';
        uploadButton.type = 'button';
        uploadButton.setAttribute('aria-label', 'Attach image');
        uploadButton.title = 'Attach image';
        var uploadSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        uploadSvg.setAttribute('width', '20');
        uploadSvg.setAttribute('height', '20');
        uploadSvg.setAttribute('viewBox', '0 0 24 24');
        uploadSvg.setAttribute('fill', 'currentColor');
        var uploadPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        uploadPath.setAttribute('d', 'M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z');
        uploadSvg.appendChild(uploadPath);
        uploadButton.appendChild(uploadSvg);

        var messageInput = document.createElement('input');
        messageInput.type = 'text';
        messageInput.id = 'woo-ai-message-input';
        messageInput.placeholder = 'Type your message...';
        messageInput.maxLength = 2000;

        var sendButton = document.createElement('button');
        sendButton.id = 'woo-ai-send-button';
        sendButton.setAttribute('aria-label', 'Send message');
        var sendSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        sendSvg.setAttribute('width', '20');
        sendSvg.setAttribute('height', '20');
        sendSvg.setAttribute('viewBox', '0 0 24 24');
        sendSvg.setAttribute('fill', 'currentColor');
        var sendPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        sendPath.setAttribute('d', 'M2.01 21L23 12 2.01 3 2 10l15 2-15 2z');
        sendSvg.appendChild(sendPath);
        sendButton.appendChild(sendSvg);

        inputArea.appendChild(fileInput);
        inputArea.appendChild(uploadButton);
        inputArea.appendChild(messageInput);
        inputArea.appendChild(sendButton);

        chatWindow.appendChild(header);
        chatWindow.appendChild(formSection);
        chatWindow.appendChild(messagesArea);
        chatWindow.appendChild(imagePreview);
        chatWindow.appendChild(inputArea);

        container.appendChild(chatButton);
        container.appendChild(chatWindow);

        document.body.appendChild(container);
    };

    WooAIChatWidget.prototype.attachEventListeners = function() {
        var self = this;

        document.getElementById('woo-ai-chat-button').addEventListener('click', function() { self.toggleWidget(); });
        document.getElementById('woo-ai-chat-close').addEventListener('click', function() { self.toggleWidget(); });

        document.getElementById('woo-ai-lead-form').addEventListener('submit', function(e) {
            e.preventDefault();
            self.submitLeadForm(e.target);
        });

        document.getElementById('woo-ai-send-button').addEventListener('click', function() { self.sendMessage(); });
        document.getElementById('woo-ai-message-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                self.sendMessage();
            }
        });

        // Image upload handlers
        document.getElementById('woo-ai-upload-button').addEventListener('click', function() {
            document.getElementById('woo-ai-file-input').click();
        });

        document.getElementById('woo-ai-file-input').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                self.handleFileSelect(e.target.files[0]);
            }
        });

        document.getElementById('woo-ai-remove-image').addEventListener('click', function() {
            self.clearPendingImage();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && self.isOpen) {
                self.toggleWidget();
            }
        });
    };

    WooAIChatWidget.prototype.handleFileSelect = function(file) {
        var self = this;

        // Validate file type
        var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (allowedTypes.indexOf(file.type) === -1) {
            this.showChatError('Please select a JPG, PNG, GIF, or WebP image.');
            return;
        }

        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            this.showChatError('Image must be less than 5MB.');
            return;
        }

        // Show preview
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('woo-ai-preview-img').src = e.target.result;
            document.getElementById('woo-ai-image-preview').classList.remove('woo-ai-hidden');
        };
        reader.readAsDataURL(file);

        this.pendingImage = file;
    };

    WooAIChatWidget.prototype.clearPendingImage = function() {
        this.pendingImage = null;
        document.getElementById('woo-ai-image-preview').classList.add('woo-ai-hidden');
        document.getElementById('woo-ai-preview-img').src = '';
        document.getElementById('woo-ai-file-input').value = '';
    };

    WooAIChatWidget.prototype.uploadImage = function(file) {
        var self = this;

        return new Promise(function(resolve, reject) {
            var formData = new FormData();
            formData.append('image', file);
            formData.append('session_id', self.sessionId);

            fetch(self.config.restUrl + '/upload', {
                method: 'POST',
                headers: {
                    'X-Chat-Token': self.nonce
                },
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(result) {
                if (result.success) {
                    resolve(result.url);
                } else {
                    reject(result.error || 'Upload failed');
                }
            })
            .catch(function(error) {
                reject(error.message || 'Upload failed');
            });
        });
    };

    WooAIChatWidget.prototype.toggleWidget = function() {
        this.isOpen = !this.isOpen;
        var chatWindow = document.getElementById('woo-ai-chat-window');
        var chatButton = document.getElementById('woo-ai-chat-button');

        if (this.isOpen) {
            chatWindow.classList.remove('woo-ai-hidden');
            chatButton.classList.add('woo-ai-hidden');
        } else {
            chatWindow.classList.add('woo-ai-hidden');
            chatButton.classList.remove('woo-ai-hidden');
        }

        if (this.isOpen && this.hasStarted) {
            document.getElementById('woo-ai-message-input').focus();
        }
    };

    WooAIChatWidget.prototype.submitLeadForm = function(form) {
        var self = this;
        var formData = new FormData(form);
        var data = {};
        formData.forEach(function(value, key) { data[key] = value; });

        var submitBtn = form.querySelector('button');
        var originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Starting...';

        fetch(this.config.restUrl + '/session', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(function(response) { return response.json(); })
        .then(function(result) {
            if (result.success) {
                self.sessionId = result.session_id;
                self.nonce = result.nonce;
                self.hasStarted = true;
                self.saveSession(data);

                document.getElementById('woo-ai-chat-form').classList.add('woo-ai-hidden');
                document.getElementById('woo-ai-chat-messages').classList.remove('woo-ai-hidden');
                document.getElementById('woo-ai-chat-input').classList.remove('woo-ai-hidden');

                self.addMessage('assistant', result.welcome_message);
                document.getElementById('woo-ai-message-input').focus();
            } else {
                self.showError(result.error || 'Something went wrong. Please try again.');
            }
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        })
        .catch(function(error) {
            console.error('Chat session error:', error);
            self.showError('Connection error. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    };

    WooAIChatWidget.prototype.sendMessage = function() {
        var self = this;
        var input = document.getElementById('woo-ai-message-input');
        var message = input.value.trim();

        // Allow sending with just an image
        if (!message && !this.pendingImage) return;
        if (this.isLoading) return;

        input.value = '';
        this.isLoading = true;
        input.disabled = true;
        document.getElementById('woo-ai-send-button').disabled = true;
        document.getElementById('woo-ai-upload-button').disabled = true;

        var imageUrl = null;
        var pendingFile = this.pendingImage;

        // If there's a pending image, upload it first
        if (pendingFile) {
            this.clearPendingImage();

            this.uploadImage(pendingFile)
            .then(function(url) {
                imageUrl = url;
                self.doSendMessage(message || 'Sent an image', imageUrl);
            })
            .catch(function(error) {
                self.showChatError('Failed to upload image: ' + error);
                self.isLoading = false;
                input.disabled = false;
                document.getElementById('woo-ai-send-button').disabled = false;
                document.getElementById('woo-ai-upload-button').disabled = false;
            });
        } else {
            this.doSendMessage(message, null);
        }
    };

    WooAIChatWidget.prototype.doSendMessage = function(message, imageUrl) {
        var self = this;
        var input = document.getElementById('woo-ai-message-input');

        this.addMessage('user', message, imageUrl);
        this.showTypingIndicator();

        var payload = {
            session_id: this.sessionId,
            message: message
        };
        if (imageUrl) {
            payload.image_url = imageUrl;
        }

        fetch(this.config.restUrl + '/message', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Chat-Token': this.nonce
            },
            body: JSON.stringify(payload)
        })
        .then(function(response) { return response.json(); })
        .then(function(result) {
            self.hideTypingIndicator();

            if (result.expired) {
                self.addMessage('assistant', result.error);
                self.resetChat();
            } else if (result.rate_limited) {
                self.addMessage('assistant', result.error);
            } else {
                self.addMessage('assistant', result.message);
            }

            self.isLoading = false;
            input.disabled = false;
            document.getElementById('woo-ai-send-button').disabled = false;
            document.getElementById('woo-ai-upload-button').disabled = false;
            input.focus();
        })
        .catch(function(error) {
            console.error('Chat message error:', error);
            self.hideTypingIndicator();
            self.addMessage('assistant', "I'm having trouble connecting. Please contact us directly at " + self.config.supportEmail + " or call " + self.config.supportPhone + ".");
            self.isLoading = false;
            input.disabled = false;
            document.getElementById('woo-ai-send-button').disabled = false;
            document.getElementById('woo-ai-upload-button').disabled = false;
            input.focus();
        });
    };

    WooAIChatWidget.prototype.addMessage = function(role, content, imageUrl) {
        var messagesDiv = document.getElementById('woo-ai-chat-messages');
        var messageEl = document.createElement('div');
        messageEl.className = 'woo-ai-message woo-ai-message-' + role;

        // Add image if present
        if (imageUrl) {
            var imgWrapper = document.createElement('div');
            imgWrapper.className = 'woo-ai-message-image';
            var img = document.createElement('img');
            img.src = imageUrl;
            img.alt = 'Attached image';
            img.addEventListener('click', function() {
                window.open(imageUrl, '_blank');
            });
            imgWrapper.appendChild(img);
            messageEl.appendChild(imgWrapper);
        }

        // Add text content
        if (content) {
            var textWrapper = document.createElement('div');
            textWrapper.className = 'woo-ai-message-text';
            this.processMessageContent(textWrapper, content);
            messageEl.appendChild(textWrapper);
        }

        messagesDiv.appendChild(messageEl);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
        this.messages.push({ role: role, content: content, image_url: imageUrl });
    };

    WooAIChatWidget.prototype.processMessageContent = function(container, content) {
        var self = this;
        var lines = content.split('\n');

        lines.forEach(function(line, lineIndex) {
            if (lineIndex > 0) {
                container.appendChild(document.createElement('br'));
            }
            self.processLine(container, line);
        });
    };

    WooAIChatWidget.prototype.processLine = function(container, text) {
        var urlRegex = /(https?:\/\/[^\s]+)/g;
        var emailRegex = /([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/g;
        var phoneRegex = /(\d{3}[-.]?\d{3}[-.]?\d{4})/g;

        var matches = [];
        var match;

        while ((match = urlRegex.exec(text)) !== null) {
            matches.push({ start: match.index, end: match.index + match[0].length, text: match[0], type: 'url' });
        }
        while ((match = emailRegex.exec(text)) !== null) {
            matches.push({ start: match.index, end: match.index + match[0].length, text: match[0], type: 'email' });
        }
        while ((match = phoneRegex.exec(text)) !== null) {
            matches.push({ start: match.index, end: match.index + match[0].length, text: match[0], type: 'phone' });
        }

        matches.sort(function(a, b) { return a.start - b.start; });

        var filteredMatches = [];
        matches.forEach(function(m) {
            if (filteredMatches.length === 0 || m.start >= filteredMatches[filteredMatches.length - 1].end) {
                filteredMatches.push(m);
            }
        });

        var lastIndex = 0;
        filteredMatches.forEach(function(m) {
            if (m.start > lastIndex) {
                container.appendChild(document.createTextNode(text.substring(lastIndex, m.start)));
            }

            var link = document.createElement('a');
            link.textContent = m.text;

            if (m.type === 'url') {
                link.href = m.text;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            } else if (m.type === 'email') {
                link.href = 'mailto:' + m.text;
            } else if (m.type === 'phone') {
                link.href = 'tel:' + m.text.replace(/[-.]/g, '');
            }

            container.appendChild(link);
            lastIndex = m.end;
        });

        if (lastIndex < text.length) {
            container.appendChild(document.createTextNode(text.substring(lastIndex)));
        }
    };

    WooAIChatWidget.prototype.showTypingIndicator = function() {
        var messagesDiv = document.getElementById('woo-ai-chat-messages');
        var indicator = document.createElement('div');
        indicator.id = 'woo-ai-typing';
        indicator.className = 'woo-ai-message woo-ai-message-assistant woo-ai-typing';

        for (var i = 0; i < 3; i++) {
            indicator.appendChild(document.createElement('span'));
        }

        messagesDiv.appendChild(indicator);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    };

    WooAIChatWidget.prototype.hideTypingIndicator = function() {
        var indicator = document.getElementById('woo-ai-typing');
        if (indicator) indicator.remove();
    };

    WooAIChatWidget.prototype.showError = function(message) {
        var formDiv = document.getElementById('woo-ai-chat-form');
        var errorEl = formDiv.querySelector('.woo-ai-error');

        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'woo-ai-error';
            formDiv.insertBefore(errorEl, formDiv.querySelector('form'));
        }

        errorEl.textContent = message;
        errorEl.style.display = 'block';

        setTimeout(function() { errorEl.style.display = 'none'; }, 5000);
    };

    WooAIChatWidget.prototype.showChatError = function(message) {
        var messagesDiv = document.getElementById('woo-ai-chat-messages');
        var errorEl = document.createElement('div');
        errorEl.className = 'woo-ai-chat-error';
        errorEl.textContent = message;
        messagesDiv.appendChild(errorEl);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;

        setTimeout(function() { errorEl.remove(); }, 5000);
    };

    WooAIChatWidget.prototype.saveSession = function(leadData) {
        try {
            var sessionData = {
                sessionId: this.sessionId,
                nonce: this.nonce,
                leadData: { first_name: leadData.first_name, email: leadData.email },
                timestamp: Date.now()
            };
            localStorage.setItem('woo_ai_chat_session', JSON.stringify(sessionData));
        } catch (e) {}
    };

    WooAIChatWidget.prototype.restoreSession = function() {
        try {
            var saved = localStorage.getItem('woo_ai_chat_session');
            if (!saved) return;

            var sessionData = JSON.parse(saved);
            var twoHours = 2 * 60 * 60 * 1000;

            if (Date.now() - sessionData.timestamp > twoHours) {
                localStorage.removeItem('woo_ai_chat_session');
                return;
            }

            var form = document.getElementById('woo-ai-lead-form');
            if (sessionData.leadData) {
                var firstNameInput = form.querySelector('[name="first_name"]');
                var emailInput = form.querySelector('[name="email"]');

                if (firstNameInput && sessionData.leadData.first_name) {
                    firstNameInput.value = sessionData.leadData.first_name;
                }
                if (emailInput && sessionData.leadData.email) {
                    emailInput.value = sessionData.leadData.email;
                }
            }
        } catch (e) {}
    };

    WooAIChatWidget.prototype.resetChat = function() {
        this.sessionId = null;
        this.nonce = null;
        this.hasStarted = false;
        this.messages = [];
        this.pendingImage = null;

        localStorage.removeItem('woo_ai_chat_session');

        var messagesDiv = document.getElementById('woo-ai-chat-messages');
        while (messagesDiv.firstChild) {
            messagesDiv.removeChild(messagesDiv.firstChild);
        }

        this.clearPendingImage();

        document.getElementById('woo-ai-chat-form').classList.remove('woo-ai-hidden');
        document.getElementById('woo-ai-chat-messages').classList.add('woo-ai-hidden');
        document.getElementById('woo-ai-chat-input').classList.add('woo-ai-hidden');
    };

    function initChat() {
        if (typeof wooAiChatConfig !== 'undefined') {
            window.wooAiChat = new WooAIChatWidget(wooAiChatConfig);
            window.wooAiChat.init();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChat);
    } else {
        initChat();
    }
})();
