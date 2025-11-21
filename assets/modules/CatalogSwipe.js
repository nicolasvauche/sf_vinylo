export default class CatalogSwipe {
    constructor(containerElement, page, totalPages, url) {
        this.container = containerElement;
        this.page = page > 0 ? page : 1;
        this.totalPages = totalPages;
        this.url = url;

        this.threshold = 0.3;
        this.startX = 0;
        this.startY = 0;
        this.isDragging = false;
        this.action = null;

        this.container.style.touchAction = 'pan-y';

        this.container.addEventListener('mousedown', this.startDrag.bind(this));
        this.container.addEventListener('touchstart', this.startDrag.bind(this));
    }

    startDrag(e) {
        this.isDragging = true;
        this.container.classList.add('dragging');

        if (e.type === 'mousedown') {
            this.startX = e.clientX;
            this.startY = e.clientY;
        } else if (e.type === 'touchstart') {
            this.startX = e.touches[0].clientX;
            this.startY = e.touches[0].clientY;
        }

        document.addEventListener('mousemove', this.drag.bind(this));
        document.addEventListener('touchmove', this.drag.bind(this));
        document.addEventListener('mouseup', this.stopDrag.bind(this));
        document.addEventListener('touchend', this.stopDrag.bind(this));
    }

    drag(e) {
        if (!this.isDragging) return;

        let x = 0;
        let y = 0;

        if (e.type === 'mousemove') {
            x = e.clientX;
            y = e.clientY;
        } else if (e.type === 'touchmove') {
            x = e.touches[0].clientX;
            y = e.touches[0].clientY;
        }

        const dx = x - this.startX;
        const dy = y - this.startY;

        const isHorizontal = Math.abs(dx) > Math.abs(dy);

        if (
            isHorizontal &&
            Math.abs(dy) <= this.threshold * this.container.parentNode.offsetHeight
        ) {
            this.container.style.transition = 'none';
            this.container.style.transform = `translateX(${dx}px)`;

            if (dx < -this.threshold * this.container.parentNode.offsetWidth) {
                this.container.classList.remove('swipe-right');
                this.container.classList.add('swipe-left');
                this.action = 'next';
            } else if (dx > this.threshold * this.container.parentNode.offsetWidth) {
                this.container.classList.remove('swipe-left');
                this.container.classList.add('swipe-right');
                this.action = 'previous';
            } else {
                this.container.classList.remove('swipe-right', 'swipe-left');
                this.action = null;
            }
        } else if (!isHorizontal) {
            this.container.style.transition = 'transform 0.3s ease-out';
            this.container.style.transform = '';
            this.container.classList.remove('swipe-right', 'swipe-left');
            this.action = null;
        }
    }

    stopDrag(e) {
        if (!this.isDragging) return;

        this.isDragging = false;
        this.container.classList.remove('dragging');
        this.container.style.touchAction = '';

        if (this.action === 'previous') {
            this.previous();
        } else if (this.action === 'next') {
            this.next();
        }

        this.container.style.transition = 'transform 0.3s ease-out';
        this.container.style.transform = '';
        this.action = null;

        document.removeEventListener('mousemove', this.drag.bind(this));
        document.removeEventListener('touchmove', this.drag.bind(this));
    }

    previous() {
        this.page--;
        if (this.page <= 0) {
            this.page = this.totalPages;
        }
        //window.location.href = this.url + '?page=' + this.page;
        alert('Page précédente');
        this.container.style.transition = 'transform 0.3s ease-out';
        this.container.style.transform = '';
        this.container.classList.remove('swipe-right', 'swipe-left');
        this.action = null;
    }

    next() {
        this.page++;
        if (this.page > this.totalPages) {
            this.page = 1;
        }
        //window.location.href = this.url + '?page=' + this.page;
        alert('Page suivante');
        this.container.style.transition = 'transform 0.3s ease-out';
        this.container.style.transform = '';
        this.container.classList.remove('swipe-right', 'swipe-left');
        this.action = null;
    }
}
