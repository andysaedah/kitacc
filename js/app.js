/**
 * KiTAcc - Core JavaScript
 * AJAX utilities, UI interactions, and components
 */

const KiTAcc = {
    // ========================================
    // CONFIGURATION
    // ========================================
    config: {
        apiBase: 'api/',
        toastDuration: 4000
    },

    // ========================================
    // INITIALIZATION
    // ========================================
    init() {
        this.initSidebar();
        this.initDropdowns();
        this.initModals();
        this.initTabs();
        this.initFormValidation();
        this.initPasswordToggle();
        this.initAnimatedCounters();
        this.initBottomNav();
        this.initSessionTimeout();
    },

    // ========================================
    // AJAX UTILITY (with CSRF)
    // ========================================
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    },

    ajax(options) {
        const defaults = {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.getCsrfToken()
            },
            data: null,
            success: () => { },
            error: () => { },
            complete: () => { }
        };

        const settings = { ...defaults, ...options };

        const xhr = new XMLHttpRequest();
        xhr.open(settings.method, settings.url, true);

        // Set headers (unless FormData, which sets its own Content-Type)
        if (settings.data instanceof FormData) {
            // For FormData, only set non-content-type headers
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-TOKEN', this.getCsrfToken());
        } else if (typeof settings.data === 'object' && settings.data !== null) {
            // For plain objects, send as URL-encoded so PHP $_POST works
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-TOKEN', this.getCsrfToken());
        } else {
            Object.keys(settings.headers).forEach(key => {
                xhr.setRequestHeader(key, settings.headers[key]);
            });
        }

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                settings.complete(xhr);

                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        settings.success(response, xhr);
                    } catch (e) {
                        settings.success(xhr.responseText, xhr);
                    }
                } else if (xhr.status === 401) {
                    // Session expired
                    window.location.href = 'login.php';
                } else if (xhr.status === 403) {
                    KiTAcc.toast('Access denied.', 'error');
                    settings.error(xhr);
                } else {
                    settings.error(xhr);
                }
            }
        };

        xhr.onerror = function () {
            settings.error(xhr);
            settings.complete(xhr);
        };

        if (settings.data) {
            if (settings.data instanceof FormData) {
                // Append CSRF token to FormData
                if (!settings.data.has('csrf_token')) {
                    settings.data.append('csrf_token', this.getCsrfToken());
                }
                xhr.send(settings.data);
            } else if (typeof settings.data === 'object') {
                // Send as URL-encoded form data (so PHP $_POST receives it)
                settings.data.csrf_token = this.getCsrfToken();
                const params = new URLSearchParams(settings.data).toString();
                xhr.send(params);
            } else {
                xhr.send(settings.data);
            }
        } else {
            xhr.send();
        }

        return xhr;
    },

    get(url, success, error) {
        return this.ajax({ url, method: 'GET', success, error });
    },

    post(url, data, success, error) {
        return this.ajax({ url, method: 'POST', data, success, error });
    },

    /**
     * Send FormData via AJAX (for file uploads)
     */
    postForm(url, formData, success, error) {
        return this.ajax({
            url,
            method: 'POST',
            data: formData,
            success,
            error
        });
    },

    // ========================================
    // SIDEBAR
    // ========================================
    initSidebar() {
        const toggle = document.getElementById('sidebarToggle');
        const wrapper = document.getElementById('appWrapper');
        const overlay = document.getElementById('mobileOverlay');
        const sidebar = document.getElementById('sidebar');

        if (!toggle || !wrapper) return;

        // Load saved state
        const collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (collapsed && window.innerWidth > 1024) {
            wrapper.classList.add('sidebar-collapsed');
        }

        toggle.addEventListener('click', () => {
            if (window.innerWidth <= 1024) {
                sidebar.classList.toggle('mobile-open');
            } else {
                wrapper.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', wrapper.classList.contains('sidebar-collapsed'));
            }
        });

        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('mobile-open');
            });
        }

        window.addEventListener('resize', () => {
            if (window.innerWidth > 1024) {
                sidebar.classList.remove('mobile-open');
            }
        });
    },

    // ========================================
    // BOTTOM NAV
    // ========================================
    initBottomNav() {
        const currentPage = window.location.pathname.split('/').pop().replace('.php', '');
        document.querySelectorAll('.bottom-nav-item').forEach(item => {
            const href = item.getAttribute('href');
            if (href && href.includes(currentPage)) {
                item.classList.add('active');
            }
        });
    },

    // ========================================
    // DROPDOWNS
    // ========================================
    initDropdowns() {
        const dropdowns = document.querySelectorAll('.user-dropdown');

        dropdowns.forEach(dropdown => {
            dropdown.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdown.classList.toggle('active');
            });
        });

        document.addEventListener('click', () => {
            dropdowns.forEach(d => d.classList.remove('active'));
        });
    },

    // ========================================
    // MODALS
    // ========================================
    initModals() {
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.closeModal(overlay);
                }
            });
        });

        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', () => {
                const overlay = btn.closest('.modal-overlay');
                if (overlay) this.closeModal(overlay);
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal-overlay.active');
                if (activeModal) this.closeModal(activeModal);
            }
        });
    },

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    },

    closeModal(modal) {
        if (typeof modal === 'string') {
            modal = document.getElementById(modal);
        }
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    },

    // ========================================
    // TABS
    // ========================================
    initTabs() {
        document.querySelectorAll('.tabs').forEach(tabContainer => {
            const tabs = tabContainer.querySelectorAll('.tab');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const targetId = tab.dataset.tab;

                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');

                    const parent = tabContainer.closest('.card') || tabContainer.parentElement;
                    parent.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });

                    const targetContent = parent.querySelector(`#${targetId}`);
                    if (targetContent) {
                        targetContent.classList.add('active');
                    }
                });
            });
        });
    },

    // ========================================
    // FORM VALIDATION
    // ========================================
    initFormValidation() {
        document.querySelectorAll('form[data-validate]').forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    },

    validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('[required]');

        inputs.forEach(input => {
            this.clearFieldError(input);

            if (!input.value.trim()) {
                this.showFieldError(input, 'This field is required');
                isValid = false;
            } else if (input.type === 'email' && !this.isValidEmail(input.value)) {
                this.showFieldError(input, 'Please enter a valid email');
                isValid = false;
            }
        });

        const password = form.querySelector('[name="new_password"]');
        const confirm = form.querySelector('[name="confirm_password"]');

        if (password && confirm && password.value !== confirm.value) {
            this.showFieldError(confirm, 'Passwords do not match');
            isValid = false;
        }

        return isValid;
    },

    showFieldError(input, message) {
        input.classList.add('error');
        const errorEl = document.createElement('div');
        errorEl.className = 'form-error';
        errorEl.textContent = message;
        input.parentElement.appendChild(errorEl);
    },

    clearFieldError(input) {
        input.classList.remove('error');
        const error = input.parentElement.querySelector('.form-error');
        if (error) error.remove();
    },

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },

    // ========================================
    // PASSWORD TOGGLE
    // ========================================
    initPasswordToggle() {
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = btn.parentElement.querySelector('input');
                const icon = btn.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
        });
    },

    // ========================================
    // TOAST
    // ========================================
    toast(message, type = 'info', duration = null) {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        duration = duration || this.config.toastDuration;

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        const icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        toast.innerHTML = `
            <i class="fas ${icons[type] || icons.info}" style="color: var(--${type === 'error' ? 'danger' : type});"></i>
            <span class="toast-message"></span>
            <button class="toast-close"><i class="fas fa-times"></i></button>
        `;
        toast.querySelector('.toast-message').textContent = message;

        container.appendChild(toast);

        toast.querySelector('.toast-close').addEventListener('click', () => {
            this.removeToast(toast);
        });

        setTimeout(() => {
            this.removeToast(toast);
        }, duration);
    },

    removeToast(toast) {
        toast.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    },

    // ========================================
    // ANIMATED COUNTERS
    // ========================================
    initAnimatedCounters() {
        document.querySelectorAll('[data-counter]').forEach(el => {
            const target = parseFloat(el.dataset.counter);
            const duration = 1000;
            const start = 0;
            const startTime = performance.now();
            const prefix = el.dataset.prefix || '';
            const suffix = el.dataset.suffix || '';
            const decimals = el.dataset.decimals || 0;

            const animate = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeOut = 1 - Math.pow(1 - progress, 3);
                const current = start + (target - start) * easeOut;

                el.textContent = prefix + current.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + suffix;

                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            };

            requestAnimationFrame(animate);
        });
    },

    // ========================================
    // LOADING STATES
    // ========================================
    showLoading(element) {
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.innerHTML = '<div class="spinner spinner-lg" style="color: var(--primary);"></div>';
        element.style.position = 'relative';
        element.appendChild(overlay);
    },

    hideLoading(element) {
        const overlay = element.querySelector('.loading-overlay');
        if (overlay) overlay.remove();
    },

    // ========================================
    // IMAGE COMPRESSION (for receipt uploads)
    // ========================================
    compressImage(file, maxWidth = 1200, quality = 0.8) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = function (e) {
                const img = new Image();
                img.onload = function () {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;

                    if (width > maxWidth) {
                        height = (height * maxWidth) / width;
                        width = maxWidth;
                    }

                    canvas.width = width;
                    canvas.height = height;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob(function (blob) {
                        const compressedFile = new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });
                        resolve(compressedFile);
                    }, 'image/jpeg', quality);
                };
                img.onerror = reject;
                img.src = e.target.result;
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    },

    // ========================================
    // TOGGLE SWITCH
    // ========================================
    initToggle(element, callback) {
        element.addEventListener('click', function () {
            this.classList.toggle('active');
            if (callback) callback(this.classList.contains('active'));
        });
    },

    // ========================================
    // UTILITIES
    // ========================================
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    formatCurrency(amount) {
        return 'RM ' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' });
    },

    /**
     * Serialize form to object
     */
    serializeForm(form) {
        const data = {};
        const formData = new FormData(form);
        for (const [key, value] of formData.entries()) {
            data[key] = value;
        }
        return data;
    },

    /**
     * Confirm dialog
     */
    confirm(message) {
        return window.confirm(message);
    },

    // ========================================
    // SESSION TIMEOUT (Client-side idle timer)
    // ========================================
    initSessionTimeout() {
        // Read config from data attribute set by PHP
        const body = document.body;
        const timeoutMinutes = parseInt(body.dataset.sessionTimeout || '30', 10);
        if (timeoutMinutes <= 0) return;

        const timeoutMs = timeoutMinutes * 60 * 1000;
        const warningMs = 2 * 60 * 1000; // Show warning 2 minutes before expiry
        let idleTimer = null;
        let warningTimer = null;
        let countdownInterval = null;
        let modalEl = null;

        const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];

        function createModal() {
            if (document.getElementById('sessionTimeoutModal')) return;
            const modal = document.createElement('div');
            modal.id = 'sessionTimeoutModal';
            modal.className = 'modal-overlay';
            modal.innerHTML = `
                <div class="modal" style="max-width:420px;">
                    <div class="modal-header">
                        <h3 class="modal-title"><i class="fas fa-clock" style="color:var(--warning);margin-right:0.5rem;"></i>Session Expiring</h3>
                    </div>
                    <div class="modal-body" style="text-align:center;">
                        <p style="margin-bottom:0.75rem;">Your session will expire due to inactivity in</p>
                        <p id="sessionCountdown" style="font-size:2rem;font-weight:700;color:var(--danger);margin:0.5rem 0;">2:00</p>
                        <p class="text-muted" style="font-size:0.8125rem;">Click below to continue working.</p>
                    </div>
                    <div class="modal-footer" style="justify-content:center;">
                        <button class="btn btn-primary" id="sessionStayBtn"><i class="fas fa-check"></i> Stay Logged In</button>
                    </div>
                </div>`;
            document.body.appendChild(modal);
            modalEl = modal;

            document.getElementById('sessionStayBtn').addEventListener('click', () => {
                hideWarning();
                resetTimer();
                // Ping server to refresh session
                KiTAcc.post(window.location.href, { action: 'ping' }, function() {}, { method: 'GET' });
            });
        }

        function showWarning() {
            createModal();
            modalEl.classList.add('active');
            let remaining = warningMs / 1000;

            const countdownEl = document.getElementById('sessionCountdown');
            countdownEl.textContent = formatCountdown(remaining);

            countdownInterval = setInterval(() => {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = 'login.php?timeout=1';
                    return;
                }
                countdownEl.textContent = formatCountdown(remaining);
            }, 1000);
        }

        function hideWarning() {
            if (modalEl) modalEl.classList.remove('active');
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
        }

        function formatCountdown(secs) {
            const m = Math.floor(secs / 60);
            const s = secs % 60;
            return m + ':' + (s < 10 ? '0' : '') + s;
        }

        function resetTimer() {
            if (idleTimer) clearTimeout(idleTimer);
            if (warningTimer) clearTimeout(warningTimer);
            hideWarning();

            // Show warning 2 min before timeout
            warningTimer = setTimeout(() => {
                showWarning();
            }, timeoutMs - warningMs);

            // Hard redirect at timeout
            idleTimer = setTimeout(() => {
                window.location.href = 'login.php?timeout=1';
            }, timeoutMs);
        }

        // Listen for user activity
        activityEvents.forEach(evt => {
            document.addEventListener(evt, () => {
                // Only reset if warning is not showing
                if (!modalEl || !modalEl.classList.contains('active')) {
                    resetTimer();
                }
            }, { passive: true });
        });

        resetTimer();
    }
};

// Add slideOut animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    KiTAcc.init();
});

window.KiTAcc = KiTAcc;
