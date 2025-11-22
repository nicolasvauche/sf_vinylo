import {Controller} from '@hotwired/stimulus';
import DiskSwipe from '../../modules/DiskSwipe.js';

export default class extends Controller {
    connect() {
        const card = document.querySelector('.app-disk');
        if (card) {
            const cardSwipe = new DiskSwipe(
                '.app-disks',
                card,
                ['top', 'left', 'right'],
                0.3,
                card.dataset.from,
                card.dataset.playonly
            );
        }
    }
}
