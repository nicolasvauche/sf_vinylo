import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static values = {
        dismissible: Boolean,
        variant: String,
        title: String,
        description: String,
    };

    static targets = ["footer"];

    connect() {
        if (this.element.parentNode !== document.body) {
            document.body.appendChild(this.element);
        }

        this.sentinel = document.getElementById('modal-focus-sentinel');
        if (!this.sentinel) {
            this.sentinel = document.createElement('div');
            this.sentinel.id = 'modal-focus-sentinel';
            this.sentinel.tabIndex = -1;
            // invisible mais focusable, hors flux
            Object.assign(this.sentinel.style, {
                position: 'fixed',
                width: '1px',
                height: '1px',
                top: '0',
                left: '0',
                opacity: '0',
                pointerEvents: 'none'
            });
            document.body.prepend(this.sentinel);
        }

        this.body = document.body;
        this.dialog = this.element.querySelector(".dialog");
        this.frame = this.element.querySelector("turbo-frame");
        this.titleEl = this.element.querySelector(".title");

        this.boundKeydown = (e) => this.onKeydown(e);
        this.boundOpen = (e) => this.open(e.detail || {});
        this.boundClose = () => this.close();

        this.element.addEventListener("modal:open", this.boundOpen);
        this.element.addEventListener("modal:close", this.boundClose);
    }

    disconnect() {
        this.element.removeEventListener("modal:open", this.boundOpen);
        this.element.removeEventListener("modal:close", this.boundClose);
        document.removeEventListener("keydown", this.boundKeydown);
    }

    open({title, description, bodyUrl, footerHtml, size, variant} = {}) {
        this.prevActive = document.activeElement instanceof HTMLElement ? document.activeElement : null;

        if (size) this.element.classList.toggle("s", size === "s");
        if (variant) this.element.classList.toggle("picker", variant === "picker");

        this.titleEl.textContent = title ?? this.titleValue ?? "";
        this.dialog.setAttribute("aria-label", this.titleEl.textContent || "Modal");

        if (bodyUrl) {
            this.frame.setAttribute("src", bodyUrl);
        } else {
            this.frame.removeAttribute("src");
            this.frame.innerHTML = description ? `<div class="modal-desc">${description}</div>` : "";
        }

        if (this.hasFooterTarget) {
            this.footerTarget.innerHTML = footerHtml || "";
        }

        this.element.setAttribute("aria-hidden", "false");
        this.element.classList.add("open");
        this.body.style.overflow = "hidden";

        this.dialog.focus();
        document.addEventListener("keydown", this.boundKeydown);

        this.dispatch("opened", {
            detail: {id: this.element.id, variant: variant || this.variantValue, ts: Date.now()}
        });
    }

    close(reason = "cancel") {
        if (this.element.contains(document.activeElement)) {
            this.sentinel.focus();
        }

        this.element.setAttribute("aria-hidden", "true");
        this.element.classList.remove("open");
        this.body.style.overflow = "";
        document.removeEventListener("keydown", this.boundKeydown);

        this.dialog.blur?.();

        requestAnimationFrame(() => {
            if (this.prevActive && this.prevActive.isConnected && typeof this.prevActive.focus === "function") {
                try {
                    this.prevActive.focus();
                } catch {
                }
            }
        });

        this.dispatch("closed", {
            detail: {id: this.element.id, reason, ts: Date.now()}
        });
    }

    onBackdrop() {
        if (this.dismissibleValue) this.close("overlay");
    }

    onKeydown(e) {
        if (e.key === "Escape" && this.dismissibleValue) {
            e.preventDefault();
            this.close("esc");
        }
        if (e.key === "Enter") {
            const primary = this.dialog.querySelector("[data-primary]");
            if (primary) primary.click();
        }
    }

    confirm(e) {
        const payload = e?.detail || {};
        this.dispatch("confirm", {detail: payload});
        this.close("confirm");
    }
}
