import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static values = {
        modal: String,
        title: String,
        description: String,
        bodyUrl: String,
        size: String,
        variant: String,
        footerHtml: String,
        actionUrl: String,
    };

    open() {
        const target = document.querySelector(this.modalValue || "#app-modal");
        if (!target) return;

        target.dispatchEvent(new CustomEvent("modal:open", {
            bubbles: true,
            detail: {
                title: this.titleValue,
                description: this.descriptionValue,
                bodyUrl: this.bodyUrlValue,
                size: this.sizeValue,
                variant: this.variantValue,
                footerHtml: this.footerHtmlValue || null,
                actionUrl: this.actionUrlValue || null
            }
        }));
    }
}
