import {Controller} from '@hotwired/stimulus';
import CollectionSwipe from '../../modules/CollectionSwipe.js';

export default class extends Controller {
    connect() {
        const el = this.element;
        const page = parseInt(el.dataset.page, 10) || 1;
        const pages = parseInt(el.dataset.pages, 10) || 1;
        const url = el.dataset.url;

        this.swipe = new CollectionSwipe(
            el,
            page,
            pages,
            url,
            ['left', 'right']
        );
    }

    disconnect() {
        if (this.swipe && typeof this.swipe.destroy === 'function') {
            this.swipe.destroy();
        }
    }
}

