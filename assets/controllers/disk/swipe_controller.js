import {Controller} from '@hotwired/stimulus';
import DiskSwipe from '../../modules/DiskSwipe.js';

export default class extends Controller {

    connect() {
        const card = document.querySelector('.app-disk');
        if (card) {
            this.swipe = new DiskSwipe(
                '.app-disks',
                card,
                ['top', 'left', 'right'],
                0.3,
                card.dataset.from,
                card.dataset.playonly
            );

            const modal = document.getElementById('app-modal');
            modal.addEventListener("modal:closed", (e) => {
                const reason = e.detail?.reason;

                if (["cancel", "overlay", "esc"].includes(reason)) {
                    this.swipe.resetCard();
                }
            });
        }
    }
}
