import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'input', 'list',
        'placeId', 'displayName', 'locality', 'countryCode', 'lat', 'lng'
    ];

    static values = {
        endpoint: String,
        min: {type: Number, default: 3},
        debounce: {type: Number, default: 350},
        lang: {type: String, default: 'fr'},
        limit: {type: Number, default: 5}
    };

    connect() {
        this._aborter = null;
        this._debounced = this._debounce(this._onInput.bind(this), this.debounceValue);
        this._activeIndex = -1;
        this._suppressNextInput = false;

        this._onClickOutside = (e) => {
            if (!this.element.contains(e.target)) this._clearList();
        };
        window.addEventListener('click', this._onClickOutside);
    }

    disconnect() {
        window.removeEventListener('click', this._onClickOutside);
        if (this._aborter) this._aborter.abort();
    }

    onInput() {
        if (this._suppressNextInput) {
            this._suppressNextInput = false;
            return;
        }
        this._debounced();
    }

    onKeydown(e) {
        const items = this.listTarget.querySelectorAll('[role="option"]');
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this._activeIndex = (this._activeIndex + 1) % items.length;
            this._highlight(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this._activeIndex = (this._activeIndex - 1 + items.length) % items.length;
            this._highlight(items);
        } else if (e.key === 'Enter') {
            if (this._activeIndex >= 0 && items[this._activeIndex]) {
                e.preventDefault();
                items[this._activeIndex].click();
            }
        } else if (e.key === 'Escape') {
            this._clearList();
        }
    }

    select(e) {
        const el = e.currentTarget;

        this._fillHidden({
            placeId: el.dataset.placeId,
            displayName: el.dataset.displayName,
            locality: el.dataset.locality,
            countryCode: el.dataset.countryCode,
            lat: el.dataset.lat,
            lng: el.dataset.lng
        });

        this._suppressNextInput = true;
        this.inputTarget.value = `${el.dataset.locality} (${String(el.dataset.countryCode).toUpperCase()})`;

        if (this._aborter) this._aborter.abort();
        this._clearList();
        this.inputTarget.blur();
    }

    async _onInput() {
        const q = this.inputTarget.value.trim();
        if (q.length < this.minValue) {
            this._clearList();
            return;
        }

        if (this._aborter) this._aborter.abort();
        this._aborter = new AbortController();

        const url = new URL(this.endpointValue, window.location.origin);
        url.searchParams.set('q', q);
        url.searchParams.set('limit', String(this.limitValue));
        url.searchParams.set('lang', this.langValue);

        try {
            const resp = await fetch(url.toString(), {signal: this._aborter.signal});
            if (!resp.ok) {
                this._clearList();
                return;
            }
            const data = await resp.json();
            this._renderList(data);
        } catch (_) {
            this._clearList();
        }
    }

    _renderList(items) {
        this._activeIndex = -1;

        if (!Array.isArray(items) || items.length === 0) {
            this.listTarget.innerHTML = `<div class="suggest-empty">Aucune suggestion</div>`;
            this.listTarget.hidden = false;
            return;
        }

        const seen = new Set();
        const filtered = items.filter(s => {
            const key = `${(s.locality || '').toLowerCase()}|${(s.countryCode || '').toLowerCase()}`;
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });

        this.listTarget.innerHTML = filtered.map((s) => `
      <div role="option"
           class="suggest-item"
           data-action="click->location--autocomplete#select"
           data-place-id="${this._escape(s.placeId)}"
           data-display-name="${this._escape(s.displayName)}"
           data-locality="${this._escape(s.locality)}"
           data-country-code="${this._escape(s.countryCode)}"
           data-lat="${this._escape(s.lat)}"
           data-lng="${this._escape(s.lng)}">
        ${this._escape(s.label)}<br><small>${this._escape(s.displayName)}</small>
      </div>
    `).join('');

        this.listTarget.hidden = false;
    }

    _highlight(items) {
        items.forEach((el, idx) => el.classList.toggle('is-active', idx === this._activeIndex));
        const active = items[this._activeIndex];
        if (active) active.scrollIntoView({block: 'nearest'});
    }

    _fillHidden(s) {
        this.placeIdTarget.value = s.placeId || '';
        this.displayNameTarget.value = s.displayName || '';
        this.localityTarget.value = s.locality || '';
        this.countryCodeTarget.value = s.countryCode || '';
        this.latTarget.value = s.lat || '';
        this.lngTarget.value = s.lng || '';
    }

    _clearList() {
        this.listTarget.innerHTML = '';
        this.listTarget.hidden = true;
        this._activeIndex = -1;
    }

    _debounce(fn, delay) {
        let t = null;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), delay);
        };
    }

    _escape(str) {
        return String(str).replace(/[&<>"']/g, s => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[s]);
    }
}
