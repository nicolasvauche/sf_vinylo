import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["tab", "panel"];
    static values = {
        storageKey: String,
        defaultActive: String
    };

    connect() {
        this.tabById = new Map();
        this.panelById = new Map();

        this.tabTargets.forEach(btn => {
            const id = btn.dataset.tabId;
            if (!id) return;
            this.tabById.set(id, btn);
            btn.setAttribute("role", "tab");
            btn.setAttribute("aria-selected", "false");
            btn.setAttribute("tabindex", "-1");
        });

        this.panelTargets.forEach(p => {
            const id = p.dataset.tabId;
            if (!id) return;
            this.panelById.set(id, p);
            p.setAttribute("role", "tabpanel");
            p.setAttribute("tabindex", "0");
            p.hidden = true;

            const tab = this.tabById.get(id);
            if (tab) {
                const panelId = p.id || `tabpanel-${id}`;
                p.id = panelId;
                tab.setAttribute("aria-controls", panelId);
                p.setAttribute("aria-labelledby", tab.id || `tab-${id}`);
                tab.id = tab.id || `tab-${id}`;
            }
        });

        const fromHash = this.#readHash();
        const fromStorage = this.#readStorage();
        const initial =
            (fromHash && this.tabById.has(fromHash) && fromHash) ||
            (fromStorage && this.tabById.has(fromStorage) && fromStorage) ||
            (this.hasDefaultActiveValue && this.tabById.has(this.defaultActiveValue) && this.defaultActiveValue) ||
            (this.tabTargets[0] && this.tabTargets[0].dataset.tabId);

        if (initial) this.activate(initial, {focus: false});

        this._onHashChange = () => {
            const id = this.#readHash();
            if (id && this.tabById.has(id)) this.activate(id, {focus: false, pushState: false});
        };
        window.addEventListener("hashchange", this._onHashChange);
    }

    disconnect() {
        window.removeEventListener("hashchange", this._onHashChange);
    }

    click(event) {
        const btn = event.currentTarget;
        const id = btn.dataset.tabId;
        if (!id) return;
        this.activate(id, {focus: true});
    }

    keydown(event) {
        const keys = ["ArrowLeft", "ArrowRight", "Home", "End"];
        if (!keys.includes(event.key)) return;

        event.preventDefault();
        const tabs = this.tabTargets;
        const currentIndex = tabs.indexOf(document.activeElement);
        if (currentIndex === -1) return;

        let nextIndex = currentIndex;
        if (event.key === "ArrowLeft") nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
        if (event.key === "ArrowRight") nextIndex = (currentIndex + 1) % tabs.length;
        if (event.key === "Home") nextIndex = 0;
        if (event.key === "End") nextIndex = tabs.length - 1;

        const next = tabs[nextIndex];
        next.focus();
        const id = next.dataset.tabId;
        if (id) this.activate(id, {focus: false});
    }

    activate(id, {focus = true, pushState = true} = {}) {
        this.tabTargets.forEach(btn => {
            btn.setAttribute("aria-selected", "false");
            btn.setAttribute("tabindex", "-1");
            btn.classList.remove("is-active");
        });
        this.panelTargets.forEach(p => (p.hidden = true));

        const tab = this.tabById.get(id);
        const panel = this.panelById.get(id);
        if (!tab || !panel) return;

        tab.setAttribute("aria-selected", "true");
        tab.setAttribute("tabindex", "0");
        tab.classList.add("is-active");
        panel.hidden = false;

        if (focus) tab.focus();
        this.#writeStorage(id);

        if (pushState) {
            const url = new URL(window.location);
            url.hash = id;
            history.replaceState(null, "", url);
        }
    }

    #readHash() {
        const hash = window.location.hash.replace(/^#/, "");
        return hash || null;
    }

    #readStorage() {
        if (!this.hasStorageKeyValue) return null;
        try {
            return localStorage.getItem(this.storageKeyValue);
        } catch {
            return null;
        }
    }

    #writeStorage(id) {
        if (!this.hasStorageKeyValue) return;
        try {
            localStorage.setItem(this.storageKeyValue, id);
        } catch {

        }
    }
}
