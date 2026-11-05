/**
 * Solvex Report — Main JavaScript
 * 
 * Core functionality: toasts, form validation, counter animation,
 * password strength meter, and utility functions.
 */

'use strict';

const Solvex = {

    // ── Toast System ────────────────────────────────────────
    toast: {
        container: null,

        init() {
            this.container = document.getElementById('toast-container');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toast-container';
                this.container.className = 'toast-container';
                document.body.appendChild(this.container);
            }
        },

        show(message, type = 'info', duration = 5000) {
            if (!this.container) this.init();

            const icons = {
                success: 'bi-check-circle-fill',
                warning: 'bi-exclamation-triangle-fill',
                danger:  'bi-x-circle-fill',
                error:   'bi-x-circle-fill',
                info:    'bi-info-circle-fill'
            };

            const toast = document.createElement('div');
            toast.className = `toast-message ${type}`;
            toast.innerHTML = `
                <i class="bi ${icons[type] || icons.info} toast-icon"></i>
                <div class="toast-body">${this.escapeHtml(message)}</div>
                <button class="toast-close" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            `;

            toast.querySelector('.toast-close').addEventListener('click', () => {
                this.dismiss(toast);
            });

            this.container.appendChild(toast);

            if (duration > 0) {
                setTimeout(() => this.dismiss(toast), duration);
            }

            return toast;
        },

        dismiss(toast) {
            toast.classList.add('hiding');
            toast.addEventListener('animationend', () => toast.remove());
        },

        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    },

    // ── Counter Animation ───────────────────────────────────
    animateCounters() {
        const counters = document.querySelectorAll('[data-counter]');
        if (!counters.length) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const target = parseInt(el.dataset.counter, 10);
                    this.countUp(el, 0, target, 1500);
                    observer.unobserve(el);
                }
            });
        }, { threshold: 0.3 });

        counters.forEach(counter => observer.observe(counter));
    },

    countUp(el, start, end, duration) {
        const range = end - start;
        if (range === 0) { el.textContent = end; return; }

        const startTime = performance.now();
        const step = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Ease out cubic
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = Math.round(start + range * eased);
            el.textContent = current.toLocaleString('en-IN');
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };
        requestAnimationFrame(step);
    },

    // ── Password Strength Meter ─────────────────────────────
    passwordStrength: {
        init(inputSelector, meterSelector, textSelector) {
            const input = document.querySelector(inputSelector);
            const meter = document.querySelector(meterSelector);
            const text  = document.querySelector(textSelector);

            if (!input || !meter) return;

            input.addEventListener('input', () => {
                const result = this.check(input.value);
                meter.className = 'strength-meter-fill ' + result.level;
                if (text) {
                    text.textContent = result.label;
                    text.style.color = result.color;
                }
            });
        },

        check(password) {
            let score = 0;
            if (password.length >= 8)  score++;
            if (password.length >= 12) score++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
            if (/\d/.test(password)) score++;
            if (/[^a-zA-Z0-9]/.test(password)) score++;

            const levels = [
                { level: '',       label: '',          color: '' },
                { level: 'weak',   label: 'Weak',      color: '#EF4444' },
                { level: 'fair',   label: 'Fair',      color: '#F59E0B' },
                { level: 'good',   label: 'Good',      color: '#3B82F6' },
                { level: 'strong', label: 'Strong',    color: '#10B981' },
                { level: 'strong', label: 'Very Strong', color: '#059669' },
            ];

            return levels[Math.min(score, 5)];
        }
    },

    // ── Form Validation ─────────────────────────────────────
    validateForm(formEl) {
        let valid = true;
        const inputs = formEl.querySelectorAll('[required], [data-validate]');

        inputs.forEach(input => {
            const error = this.validateField(input);
            const feedback = input.parentElement.querySelector('.invalid-feedback');

            if (error) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                if (feedback) feedback.textContent = error;
                valid = false;
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                if (feedback) feedback.textContent = '';
            }
        });

        return valid;
    },

    validateField(input) {
        const value = input.value.trim();
        const type  = input.type;
        const rules = input.dataset.validate || '';

        // Required check
        if (input.required && !value) {
            return 'This field is required.';
        }

        // Skip further checks if empty and not required
        if (!value) return null;

        // Email
        if (type === 'email' || rules.includes('email')) {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                return 'Please enter a valid email address.';
            }
        }

        // Min length
        const minLen = input.minLength || parseInt(input.dataset.minlength, 10);
        if (minLen > 0 && value.length < minLen) {
            return `Must be at least ${minLen} characters.`;
        }

        // Password match
        if (rules.includes('match')) {
            const matchEl = document.querySelector(input.dataset.matchTarget);
            if (matchEl && value !== matchEl.value) {
                return 'Passwords do not match.';
            }
        }

        // Register number format
        if (rules.includes('register_no')) {
            if (!/^\d{4}[A-Z]{2,3}\d{3}$/.test(value)) {
                return 'Format: YYYYXXNNN (e.g., 2024CS001)';
            }
        }

        return null;
    },

    // ── Fade-in Animation on Scroll ─────────────────────────
    initScrollAnimations() {
        const elements = document.querySelectorAll('.fade-in-up');
        if (!elements.length) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        elements.forEach(el => {
            el.style.animationPlayState = 'paused';
            observer.observe(el);
        });
    },

    // ── Smooth Scroll for Anchor Links ──────────────────────
    initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;

                const target = document.querySelector(targetId);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    },

    // ── Multi-step Form ─────────────────────────────────────
    multiStep: {
        currentStep: 1,
        totalSteps: 3,

        init(totalSteps) {
            this.totalSteps = totalSteps;
            this.showStep(1);
        },

        showStep(step) {
            this.currentStep = step;

            // Hide all steps
            document.querySelectorAll('.form-step').forEach(el => {
                el.style.display = 'none';
            });

            // Show current step
            const currentEl = document.querySelector(`.form-step[data-step="${step}"]`);
            if (currentEl) {
                currentEl.style.display = 'block';
            }

            // Update progress bar
            document.querySelectorAll('.progress-bar-solvex .step').forEach((el, i) => {
                el.classList.toggle('active', i + 1 === step);
                el.classList.toggle('completed', i + 1 < step);
            });
        },

        next() {
            if (this.currentStep < this.totalSteps) {
                this.showStep(this.currentStep + 1);
            }
        },

        prev() {
            if (this.currentStep > 1) {
                this.showStep(this.currentStep - 1);
            }
        }
    },

    // ── Notifications Poller & Dropdown ─────────────────────
    notifications: {
        timer: null,
        baseUrl() {
            return (window.SOLVEX_BASE_URL || '').replace(/\/$/, '');
        },

        init() {
            const badge = document.getElementById('navNotificationBadge');
            const bell = document.getElementById('notifBellButton');
            if (!badge && !bell) return;

            this.check();
            this.timer = setInterval(() => this.check(), 60000); // 60 seconds

            if (bell) {
                bell.addEventListener('click', () => this.loadDropdown());
            }
        },

        async check() {
            try {
                const res = await fetch(`${this.baseUrl()}/api/notifications.php?action=count`);
                if (!res.ok) return;
                const data = await res.json();
                const badge = document.getElementById('navNotificationBadge');
                if (badge && data.success) {
                    if (data.unread_count > 0) {
                        badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }
                }
            } catch (e) {
                // Ignore silent network failure during poll
            }
        },

        async loadDropdown() {
            const list = document.getElementById('notifDropdownList');
            if (!list) return;

            try {
                const res = await fetch(`${this.baseUrl()}/api/notifications.php?action=latest`);
                if (!res.ok) return;
                const data = await res.json();
                if (data.success) {
                    if (!data.notifications || data.notifications.length === 0) {
                        list.innerHTML = '<div class="text-center py-3 text-muted small">No notifications yet.</div>';
                        return;
                    }

                    list.innerHTML = '';
                    data.notifications.forEach(n => {
                        const item = document.createElement('a');
                        item.className = `dropdown-item p-2 rounded-2 text-wrap small ${n.is_read ? 'text-muted' : 'fw-bold text-navy bg-light'}`;
                        item.href = n.url || `${this.baseUrl()}/student/notifications.php`;
                        item.innerHTML = `
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi ${n.is_read ? 'bi-dot text-muted' : 'bi-circle-fill text-primary'} mt-1" style="font-size:0.6rem;"></i>
                                <div class="flex-grow-1">
                                    <div>${Solvex.toast.escapeHtml(n.message)}</div>
                                    <div class="text-muted" style="font-size:0.7rem;">${n.created_at}</div>
                                </div>
                            </div>
                        `;
                        if (!n.is_read) {
                            item.addEventListener('click', () => {
                                const formData = new FormData();
                                formData.append('action', 'mark_read');
                                formData.append('id', String(n.id));
                                formData.append('csrf_token', window.SOLVEX_CSRF || '');
                                fetch(`${this.baseUrl()}/api/notifications.php?action=mark_read`, {
                                    method: 'POST',
                                    body: formData
                                }).catch(() => {});
                            });
                        }
                        list.appendChild(item);
                    });
                }
            } catch (e) {
                list.innerHTML = '<div class="text-center py-2 text-danger small">Error loading notifications.</div>';
            }
        }
    },

    // ── Initialize ──────────────────────────────────────────
    init() {
        this.toast.init();
        this.animateCounters();
        this.initScrollAnimations();
        this.initSmoothScroll();
        this.notifications.init();

        // Setup form validation on submit
        document.querySelectorAll('form[data-validate]').forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    }
};

// Auto-init on DOM ready
document.addEventListener('DOMContentLoaded', () => Solvex.init());
