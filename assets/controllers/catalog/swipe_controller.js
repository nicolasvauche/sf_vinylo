import {Controller} from '@hotwired/stimulus';
import CatalogSwipe from '../../modules/CatalogSwipe.js';

export default class extends Controller {
    connect() {
        const swipeContainer = document.getElementById('swipe-container')
        if (swipeContainer) {
            const page = swipeContainer.dataset.page > 0 ? swipeContainer.dataset.page : 1
            const pages = swipeContainer.dataset.pages
            const url = swipeContainer.dataset.url
            const catalogSwipe = new CatalogSwipe(swipeContainer, page, pages, url)
        }
    }
}
