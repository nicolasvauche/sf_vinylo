import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['grid'];
    static values = {name: String};

    connect() {
        if (!this.nameValue) {
            const anyRadio = this.gridTarget.querySelector('input[type="radio"]');
            if (anyRadio) this.nameValue = anyRadio.name;
        }
    }

    onUpload(event) {
        const input = event.currentTarget;
        if (!input.files || !input.files[0]) return;

        const url = URL.createObjectURL(input.files[0]);
        this.upsertUserCoverTile('upload', url);
    }

    onCamera(event) {
        const input = event.currentTarget;
        if (!input.files || !input.files[0]) return;

        const url = URL.createObjectURL(input.files[0]);
        this.upsertUserCoverTile('camera', url);
    }

    onDiscogsRadio(event) {
        const radio = event.currentTarget;
        const userTile = this.gridTarget.querySelector('.app-cover[data-user-cover="true"] input[type="radio"]');
        if (userTile) userTile.checked = false;
        radio.checked = true;
    }

    upsertUserCoverTile(source, previewUrl) {
        this.gridTarget.querySelectorAll('input[type="radio"]').forEach(r => (r.checked = false));

        let tile = this.gridTarget.querySelector('.app-cover[data-user-cover="true"]');
        if (!tile) {
            tile = document.createElement('label');
            tile.className = 'app-cover';
            tile.dataset.userCover = 'true';

            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = this.nameValue;
            radio.value = source; // 'upload' | 'camera'
            radio.checked = true;

            const img = document.createElement('img');
            img.alt = 'Couverture importée';
            img.decoding = 'async';
            img.loading = 'lazy';

            tile.appendChild(radio);
            tile.appendChild(img);
            this.gridTarget.prepend(tile);
        }

        const radio = tile.querySelector('input[type="radio"]');
        const img = tile.querySelector('img');

        if (tile.dataset.objectUrl && tile.dataset.objectUrl.startsWith('blob:')) {
            try {
                URL.revokeObjectURL(tile.dataset.objectUrl);
            } catch (_) {
            }
        }

        radio.value = source;
        radio.checked = true;

        img.src = '';
        img.src = previewUrl;
        tile.dataset.objectUrl = previewUrl;

        radio.focus();
    }
}
