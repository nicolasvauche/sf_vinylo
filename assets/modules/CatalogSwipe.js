export default class CatalogSwipe {
    constructor(containerElement, page, totalPages, url, feedbackElements) {
        this.container = containerElement;
        this.page = page > 0 ? page : 1;
        this.totalPages = totalPages;
        this.url = url;

        this.threshold = 0.3;
        this.lockThreshold = 10;
        this.clickThreshold = 6;
        this.EPS = 2;
        this.startX = 0;
        this.startY = 0;
        this.isDragging = false;
        this.isSwiping = false;
        this.action = null;
        this.lockedAxis = null;
        this.initialTarget = null;

        this.feedbackMap = new Map();
        if (Array.isArray(feedbackElements)) {
            feedbackElements.forEach((name) => {
                const el = document.querySelector(`.app-feedback.${name}`);
                if (el) {
                    if (el.parentNode !== document.body) document.body.appendChild(el);
                    this.feedbackMap.set(name, el);
                }
            });
        }

        this.dragBound = this.drag.bind(this);
        this.stopDragBound = this.stopDrag.bind(this);

        this.container.style.touchAction = 'pan-y';

        this.container.addEventListener('mousedown', this.startDrag.bind(this));
        this.container.addEventListener('touchstart', this.startDrag.bind(this), {passive: true});
    }

    setActiveFeedback(name) {
        if (!this.feedbackMap.size) return;
        for (const [key, el] of this.feedbackMap) {
            if (key === name) el.classList.add('active');
            else el.classList.remove('active');
        }
    }

    clearFeedback() {
        if (!this.feedbackMap.size) return;
        for (const el of this.feedbackMap.values()) el.classList.remove('active');
    }

    isInteractive(el) {
        const clickable = ['A', 'BUTTON', 'INPUT', 'TEXTAREA', 'SELECT', 'LABEL', 'SUMMARY'];
        return !!el && (clickable.includes(el.tagName) || el.closest('a, button, input, textarea, select, label, summary'));
    }

    closestLink(el) {
        return el ? el.closest('a') : null;
    }

    startDrag(e) {
        this.isDragging = true;
        this.isSwiping = false;
        this.lockedAxis = null;
        this.action = null;
        this.initialTarget = e.target;
        this.container.classList.add('dragging');

        if (e.type === 'mousedown') {
            this.startX = e.clientX;
            this.startY = e.clientY;
        } else {
            this.startX = e.touches[0].clientX;
            this.startY = e.touches[0].clientY;
        }

        this.container.style.transition = 'none';
        this.container.style.willChange = 'transform';

        document.addEventListener('mousemove', this.dragBound);
        document.addEventListener('touchmove', this.dragBound, {passive: false});
        document.addEventListener('mouseup', this.stopDragBound, {passive: false});
        document.addEventListener('touchend', this.stopDragBound, {passive: false});
    }

    drag(e) {
        if (!this.isDragging) return;

        let x = 0, y = 0;
        if (e.type === 'mousemove') {
            x = e.clientX;
            y = e.clientY;
        } else {
            x = e.touches[0].clientX;
            y = e.touches[0].clientY;
        }

        const dx = x - this.startX;
        const dy = y - this.startY;
        const absDx = Math.abs(dx);
        const absDy = Math.abs(dy);

        if (absDx <= this.EPS && absDy <= this.EPS) {
            this.clearFeedback();
        }

        if (!this.lockedAxis) {
            if (absDx <= this.EPS && absDy <= this.EPS) return;

            if (absDx >= absDy) {
                if (absDx > this.lockThreshold) {
                    this.lockedAxis = 'x';
                    this.isSwiping = true;
                    if (e.cancelable) e.preventDefault();

                    if (dx >= 0) this.setActiveFeedback('right');
                    else this.setActiveFeedback('left');
                } else {
                    if (dx >= 0) this.setActiveFeedback('right');
                    else this.setActiveFeedback('left');
                    return;
                }
            } else {
                return;
            }
        } else if (this.lockedAxis === 'x') {
            if (e.cancelable) e.preventDefault();
        }

        this.container.style.transform = `translate3d(${dx}px,0,0)`;

        if (dx >= 0) this.setActiveFeedback('right');
        else this.setActiveFeedback('left');

        const width = this.container.parentNode ? this.container.parentNode.offsetWidth : this.container.offsetWidth;
        const thresholdPx = this.threshold * width;

        if (dx < -thresholdPx) {
            this.container.classList.remove('swipe-right');
            this.container.classList.add('swipe-left');
            this.action = 'next';
        } else if (dx > thresholdPx) {
            this.container.classList.remove('swipe-left');
            this.container.classList.add('swipe-right');
            this.action = 'previous';
        } else {
            this.container.classList.remove('swipe-right', 'swipe-left');
            this.action = null;
        }
    }

    stopDrag(e) {
        if (!this.isDragging) return;

        let endX = 0, endY = 0;
        if (e.type === 'mouseup') {
            endX = e.clientX;
            endY = e.clientY;
        } else {
            const t = e.changedTouches && e.changedTouches[0] ? e.changedTouches[0] : (e.touches ? e.touches[0] : null);
            endX = t ? t.clientX : this.startX;
            endY = t ? t.clientY : this.startY;
        }
        const totalDx = Math.abs(endX - this.startX);
        const totalDy = Math.abs(endY - this.startY);

        this.isDragging = false;
        this.container.classList.remove('dragging');
        this.container.style.touchAction = '';

        if (!this.isSwiping && totalDx < this.clickThreshold && totalDy < this.clickThreshold) {
            const link = this.closestLink(this.initialTarget);
            if (link) {
                link.click();
                this.teardownListeners();
                this.container.style.willChange = '';
                return;
            }
        }

        if (this.action === 'previous') {
            this.previous();
        } else if (this.action === 'next') {
            this.next();
        } else {
            this.resetContainer();
        }

        this.teardownListeners();
        this.container.style.willChange = '';
    }

    teardownListeners() {
        document.removeEventListener('mousemove', this.dragBound);
        document.removeEventListener('touchmove', this.dragBound);
        document.removeEventListener('mouseup', this.stopDragBound);
        document.removeEventListener('touchend', this.stopDragBound);
    }

    resetContainer() {
        this.container.style.transition = 'transform 0.25s ease-out';
        this.container.style.transform = '';
        setTimeout(() => {
            this.container.style.transition = '';
            this.clearFeedback();
        }, 250);
        this.container.classList.remove('swipe-right', 'swipe-left');
        this.action = null;
        this.isSwiping = false;
    }

    previous() {
        this.page--;
        if (this.page <= 0) this.page = this.totalPages;
        // window.location.href = this.url + '?page=' + this.page;
        alert('Page précédente');
        this.resetContainer();
    }

    next() {
        this.page++;
        if (this.page > this.totalPages) this.page = 1;
        // window.location.href = this.url + '?page=' + this.page;
        alert('Page suivante');
        this.resetContainer();
    }
}
