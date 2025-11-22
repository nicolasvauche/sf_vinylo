import {Controller} from '@hotwired/stimulus';
import CardSwipe from '../../modules/CardSwipe.js';

export default class extends Controller {
    connect() {
        const card = document.querySelector('.app-card');
        if (card) {
            const cardSwipe = new CardSwipe(
                '.app-cards',
                card,
                ['top', 'left', 'right'],
                0.3,
                card.dataset.from,
                card.dataset.playonly
            );
        }
    }
}
