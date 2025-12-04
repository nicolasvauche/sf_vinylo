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

        this.onPageShow = () => {
            const here = location.pathname + location.search + location.hash;
            const savedUrl = sessionStorage.getItem('vault:returnUrl');
            const y = parseInt(sessionStorage.getItem('vault:scrollTop') || '0', 10);

            if (savedUrl === here) {
                requestAnimationFrame(() => {
                    window.scrollTo(0, y);
                    sessionStorage.removeItem('vault:returnUrl');
                    sessionStorage.removeItem('vault:scrollTop');
                });
            }
        };
        window.addEventListener('pageshow', this.onPageShow);

        this.onClick = (e) => {
            const a = e.target.closest('a');
            if (!a) return;

            const href = a.getAttribute('href') || '';
            if (href.includes('/vault/disque/details')) {
                const urlNow = location.pathname + location.search + location.hash;
                sessionStorage.setItem('vault:returnUrl', urlNow);
                sessionStorage.setItem('vault:scrollTop', String(window.scrollY || 0));
            }
        };
        this.element.addEventListener('click', this.onClick);
    }

    disconnect() {
        if (this.swipe && typeof this.swipe.destroy === 'function') {
            this.swipe.destroy();
        }
        if (this.onPageShow) window.removeEventListener('pageshow', this.onPageShow);
        if (this.onClick) this.element.removeEventListener('click', this.onClick);
    }
}
