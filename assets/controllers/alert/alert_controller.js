import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        duration: Number,
        max: {type: Number, default: 5}
    };

    connect() {
        this._bindExisting();

        this._onPushBound = (e) => this.push(e.detail || {});
        window.addEventListener('alert:push', this._onPushBound, {passive: true});
    }

    disconnect() {
        if (this._onPushBound) {
            window.removeEventListener('alert:push', this._onPushBound);
        }
    }

    push({html = '', type = 'info', duration, size = ''} = {}) {
        const el = document.createElement('div');
        el.className = `app-flash is-${type} ${size}`.trim();
        el.setAttribute('role', (type === 'error' || type === 'warning') ? 'alert' : 'status');

        if (Number.isFinite(duration)) {
            el.style.setProperty('--flash-duration', `${Math.max(0, Number(duration))}ms`);
        }

        el.innerHTML = `
            <div class="flash-body">
                <p class="flash-message">${html}</p>
            </div>
            <button class="flash-close" type="button" aria-label="Fermer">×</button>
            <div class="flash-timer" aria-hidden="true"></div>
        `;

        this.element.appendChild(el);
        this._bindFlash(el);
        return el;
    }

    _bindExisting() {
        this.element.querySelectorAll('.app-flash').forEach((flash) => this._bindFlash(flash));
    }

    _bindFlash(flash) {
        const cssVar = getComputedStyle(flash).getPropertyValue('--flash-duration').trim();
        const parsedFromCss = this._parseDuration(cssVar);
        const hasControllerDuration = Object.prototype.hasOwnProperty.call(this, 'hasDurationValue') && this.hasDurationValue;
        const controllerDuration = hasControllerDuration ? Number(this.durationValue) : NaN;

        const duration = Number.isFinite(parsedFromCss)
            ? parsedFromCss
            : (Number.isFinite(controllerDuration) ? controllerDuration : NaN);

        const closeBtn = flash.querySelector('.flash-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this._dismiss(flash));
        }

        if (Number.isFinite(duration) && duration > 0) {
            let timeout = setTimeout(() => this._dismiss(flash), duration);

            flash.addEventListener('mouseenter', () => {
                if (timeout) {
                    clearTimeout(timeout);
                    timeout = null;
                }
            });

            flash.addEventListener('mouseleave', () => {
                if (!timeout) {
                    timeout = setTimeout(() => this._dismiss(flash), 1200);
                }
            });
        } else {
            const timer = flash.querySelector('.flash-timer');
            if (timer) timer.remove();
        }

        this._cleanupOverflow();
    }

    _dismiss(flash) {
        if (!flash || flash._closing) return;
        flash._closing = true;

        flash.style.transition = 'transform .18s ease, opacity .18s ease';
        flash.style.transform = 'translateY(-6px)';
        flash.style.opacity = '0';

        window.setTimeout(() => {
            flash.remove();
            this._teardownIfEmpty();
        }, 180);
    }

    _cleanupOverflow() {
        const items = Array.from(this.element.querySelectorAll('.app-flash'));
        while (items.length > this.maxValue) {
            const old = items.shift();
            old?.remove();
        }
    }

    _teardownIfEmpty() {
        const stillThere = this.element && this.element.querySelector('.app-flash');
        if (!stillThere && this.element?.isConnected) {
            if (this._onPushBound) {
                window.removeEventListener('alert:push', this._onPushBound);
            }
            this.element.remove();
        }
    }

    _parseDuration(str) {
        if (!str) return NaN;
        const s = String(str).toLowerCase();
        if (s.endsWith('ms')) return parseFloat(s);
        if (s.endsWith('s')) return parseFloat(s) * 1000;
        const n = parseFloat(s);
        return Number.isFinite(n) ? n : NaN;
    }
}
