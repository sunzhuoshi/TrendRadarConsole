/**
 * TrendRadarConsole - Main JavaScript
 */

// Internationalization support
if (typeof window.i18n === 'undefined') {
    window.i18n = {};
}

function __(key) {
    return window.i18n[key] || key;
}

// Language switching
async function switchLanguage(lang) {
    try {
        await apiRequest('api/language.php', 'POST', { lang: lang });
        // Reload the page to apply the new language
        location.reload();
    } catch (error) {
        console.error('Failed to switch language:', error);
    }
}

// Mobile sidebar toggle
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
    if (overlay) {
        overlay.classList.toggle('open');
    }
}

// Get CSRF token from meta tag or hidden input
function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) return metaTag.content;
    
    const hiddenInput = document.querySelector('input[name="csrf_token"]');
    if (hiddenInput) return hiddenInput.value;
    
    return '';
}

// API request helper
async function apiRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken()
        }
    };
    
    if (data && method !== 'GET') {
        // Add CSRF token to data for POST/PUT/DELETE
        data.csrf_token = getCsrfToken();
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const responseText = await response.text();
        
        // Try to parse as JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (jsonError) {
            // If JSON parsing fails, throw error with the raw text response
            throw new Error(responseText || 'Request failed with invalid response');
        }
        
        if (!response.ok) {
            throw new Error(result.message || 'Request failed');
        }
        
        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// Toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    // Add styles
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 4px;
        color: #fff;
        font-size: 14px;
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    
    // Set background color based on type
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    toast.style.backgroundColor = colors[type] || colors.info;
    if (type === 'warning') {
        toast.style.color = '#212529';
    }
    
    document.body.appendChild(toast);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Add animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Modal handling
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

// Tab handling
function initTabs() {
    const tabItems = document.querySelectorAll('.tab-item');
    
    tabItems.forEach(item => {
        item.addEventListener('click', function() {
            const tabGroup = this.closest('.tabs').dataset.tabGroup || 'default';
            const target = this.dataset.tab;
            
            // Update tab items
            document.querySelectorAll(`.tab-item[data-tab-group="${tabGroup}"]`).forEach(tab => {
                tab.classList.remove('active');
            });
            this.classList.add('active');
            
            // Update tab content
            document.querySelectorAll(`.tab-content[data-tab-group="${tabGroup}"]`).forEach(content => {
                content.classList.remove('active');
            });
            document.querySelector(`.tab-content[data-tab="${target}"]`)?.classList.add('active');
        });
    });
}

// Form validation
function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('error');
        } else {
            field.classList.remove('error');
        }
    });
    
    return isValid;
}

// Confirm dialog
function confirmDialog(message) {
    return new Promise((resolve) => {
        const result = confirm(message);
        resolve(result);
    });
}

// Copy to clipboard
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showToast(__('copied_to_clipboard'), 'success');
    } catch (err) {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast(__('copied_to_clipboard'), 'success');
    }
}

// Toggle switch handler
function initToggleSwitches() {
    document.querySelectorAll('.toggle-switch input').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const settingKey = this.dataset.setting;
            const value = this.checked ? 'true' : 'false';
            
            if (settingKey) {
                // Save setting via API
                updateSetting(settingKey, value);
            }
        });
    });
}

// Update setting
async function updateSetting(key, value) {
    try {
        const configId = document.getElementById('config-id')?.value;
        if (!configId) return;
        
        await apiRequest('api/settings.php', 'POST', {
            config_id: configId,
            key: key,
            value: value
        });
        
        showToast('Setting updated', 'success');
    } catch (error) {
        showToast('Failed to update setting: ' + error.message, 'error');
    }
}

// Mobile sidebar toggle
function initMobileSidebar() {
    const toggleBtn = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }
}

// Platform management
const platformManager = {
    async toggle(id, enabled) {
        try {
            await apiRequest('api/platforms.php', 'PUT', {
                id: id,
                is_enabled: enabled ? 1 : 0
            });
            showToast('Platform updated', 'success');
        } catch (error) {
            showToast('Failed to update platform: ' + error.message, 'error');
        }
    },
    
    async add(configId, platformId, platformName) {
        try {
            await apiRequest('api/platforms.php', 'POST', {
                config_id: configId,
                platform_id: platformId,
                platform_name: platformName
            });
            showToast('Platform added', 'success');
            location.reload();
        } catch (error) {
            showToast('Failed to add platform: ' + error.message, 'error');
        }
    },
    
    async remove(id) {
        if (!await confirmDialog('Are you sure you want to remove this platform?')) {
            return;
        }
        
        try {
            await apiRequest('api/platforms.php?id=' + id, 'DELETE');
            showToast('Platform removed', 'success');
            location.reload();
        } catch (error) {
            showToast('Failed to remove platform: ' + error.message, 'error');
        }
    }
};

// Keyword management
const keywordManager = {
    async save(configId, keywordsText) {
        try {
            await apiRequest('api/keywords.php', 'POST', {
                config_id: configId,
                keywords_text: keywordsText
            });
            showToast('Keywords saved', 'success');
        } catch (error) {
            showToast('Failed to save keywords: ' + error.message, 'error');
        }
    }
};

// Webhook management
const webhookManager = {
    async save(configId, type, data) {
        try {
            await apiRequest('api/webhooks.php', 'POST', {
                config_id: configId,
                type: type,
                ...data
            });
            showToast('Webhook saved', 'success');
        } catch (error) {
            showToast('Failed to save webhook: ' + error.message, 'error');
        }
    },
    
    async toggle(id, enabled) {
        try {
            await apiRequest('api/webhooks.php', 'PUT', {
                id: id,
                is_enabled: enabled ? 1 : 0
            });
            showToast('Webhook updated', 'success');
        } catch (error) {
            showToast('Failed to update webhook: ' + error.message, 'error');
        }
    },
    
    async remove(id) {
        if (!await confirmDialog('Are you sure you want to remove this webhook?')) {
            return;
        }
        
        try {
            await apiRequest('api/webhooks.php?id=' + id, 'DELETE');
            showToast('Webhook removed', 'success');
            location.reload();
        } catch (error) {
            showToast('Failed to remove webhook: ' + error.message, 'error');
        }
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    initToggleSwitches();
    initMobileSidebar();
});

// Button loading state helpers
function setButtonLoading(button, isLoading) {
    if (!button) return;
    
    if (isLoading) {
        // Wrap content in span if not already wrapped
        if (!button.querySelector('.btn-text')) {
            const span = document.createElement('span');
            span.className = 'btn-text';
            // Move all child nodes into the span
            while (button.firstChild) {
                span.appendChild(button.firstChild);
            }
            button.appendChild(span);
        }
        button.classList.add('loading');
        button.disabled = true;
    } else {
        button.classList.remove('loading');
        button.disabled = false;
        // Restore original content by unwrapping span
        const span = button.querySelector('.btn-text');
        if (span) {
            while (span.firstChild) {
                button.insertBefore(span.firstChild, span);
            }
            span.remove();
        }
    }
}
