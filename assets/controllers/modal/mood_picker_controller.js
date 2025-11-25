import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    connect() {
        this.initial = this.element.getAttribute("data-initial") || "";
        this.current = this.initial || null;

        this.primarySelector = "#app-modal [data-primary]";
        this.applyActiveState();
        this.updatePrimary();
    }

    toggle(e) {
        const id = e.currentTarget.getAttribute("data-id");
        this.current = (this.current === id) ? null : id;
        this.applyActiveState();
        this.updatePrimary();
    }

    applyActiveState() {
        this.element.querySelectorAll(".picker-item").forEach(btn => {
            const active = btn.getAttribute("data-id") === this.current;
            btn.classList.toggle("active", active);
            btn.setAttribute("aria-pressed", active ? "true" : "false");
        });
    }

    updatePrimary() {
        const primary = document.querySelector(this.primarySelector);
        if (primary) {
            primary.disabled = (this.current == null);
        }
    }

    confirm() {
        const modal = document.getElementById("app-modal");
        modal.dispatchEvent(new CustomEvent("modal:confirm", {
            bubbles: true,
            detail: {
                id: "mood-picker",
                selectionId: this.current,
                changed: (this.current || "") !== (this.initial || "")
            }
        }));
    }
}
