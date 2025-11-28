import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        paramname: {type: String, default: 'id'}
    };

    select(event) {
        if (event.target.closest('.delete')) return;

        const card = event.currentTarget;
        const id = card.dataset.id;
        if (!id) return;

        const urlTemplate = this.urlValue;
        const finalUrl = urlTemplate.replace(/\/\d+$/, '/' + id);

        window.location.href = finalUrl;
    }
}
