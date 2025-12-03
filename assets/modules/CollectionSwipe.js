export default class CollectionSwipe {
    constructor(containerElement, page, totalPages, url, feedbackElements) {
        this.container = containerElement;
        this.page = Number.isFinite(page) ? page : 1;
        this.totalPages = Number.isFinite(totalPages) ? totalPages : 1;
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
        this.startDragBound = this.startDrag.bind(this);

        this.container.style.touchAction = 'pan-y';

        this.container.addEventListener('mousedown', this.startDragBound);
        this.container.addEventListener('touchstart', this.startDragBound, {passive: true});
    }

    setActiveFeedback(name) {
        if (!this.feedbackMap.size) return;
        for (const [key, el] of this.feedbackMap) {
            if (key === name) {
                el.classList.add('active');
                el.setAttribute('disabled', 'disabled');
            } else {
                el.classList.remove('active');
                el.removeAttribute('disabled');
            }
        }
    }

    enableActiveFeedback() {
        for (const el of this.feedbackMap.values()) {
            if (el.classList.contains('active')) el.removeAttribute('disabled');
        }
    }

    disableActiveFeedback() {
        for (const el of this.feedbackMap.values()) {
            if (el.classList.contains('active')) el.setAttribute('disabled', 'disabled');
        }
    }

    clearFeedback() {
        if (!this.feedbackMap.size) return;
        for (const el of this.feedbackMap.values()) {
            el.classList.remove('active');
            el.removeAttribute('disabled');
        }
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
        document.addEventListener('mouseup', this.stopDragBound);
        document.addEventListener('touchend', this.stopDragBound);
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

        const canPrev = this.page > 1;
        const canNext = this.page < this.totalPages;

        if (absDx <= this.EPS && absDy <= this.EPS) this.clearFeedback();

        if (!this.lockedAxis) {
            if (absDx <= this.EPS && absDy <= this.EPS) return;

            if (absDx >= absDy) {
                if ((dx > 0 && !canPrev) || (dx < 0 && !canNext)) {
                    this.clearFeedback();
                    return;
                }
                if (absDx > this.lockThreshold) {
                    this.lockedAxis = 'x';
                    this.isSwiping = true;
                    if (e.cancelable) e.preventDefault();
                    this.setActiveFeedback(dx >= 0 ? 'right' : 'left');
                    this.disableActiveFeedback();
                } else {
                    this.setActiveFeedback(dx >= 0 ? 'right' : 'left');
                    this.disableActiveFeedback();
                    return;
                }
            } else {
                return;
            }
        } else if (this.lockedAxis === 'x') {
            if (e.cancelable) e.preventDefault();
            if ((dx > 0 && !canPrev) || (dx < 0 && !canNext)) {
                this.clearFeedback();
                this.container.style.transform = '';
                this.action = null;
                return;
            }
        }

        this.container.style.transform = `translate3d(${dx}px,0,0)`;
        this.setActiveFeedback(dx >= 0 ? 'right' : 'left');
        this.disableActiveFeedback();

        const width = (this.container.parentNode ? this.container.parentNode.offsetWidth : this.container.offsetWidth) || 1;
        const thresholdPx = this.threshold * width;

        if (dx < -thresholdPx) {
            if (canNext) {
                this.container.classList.remove('swipe-right');
                this.container.classList.add('swipe-left');
                this.action = 'next';
                this.enableActiveFeedback();
            } else {
                this.container.classList.remove('swipe-right', 'swipe-left');
                this.action = null;
                this.disableActiveFeedback();
            }
        } else if (dx > thresholdPx) {
            if (canPrev) {
                this.container.classList.remove('swipe-left');
                this.container.classList.add('swipe-right');
                this.action = 'previous';
                this.enableActiveFeedback();
            } else {
                this.container.classList.remove('swipe-right', 'swipe-left');
                this.action = null;
                this.disableActiveFeedback();
            }
        } else {
            this.container.classList.remove('swipe-right', 'swipe-left');
            this.action = null;
            this.disableActiveFeedback();
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

    blockedBounce(direction = 'none') {
        const px = direction === 'previous' ? 12 : direction === 'next' ? -12 : 8;
        this.container.style.transition = 'transform 0.12s ease-out';
        this.container.style.transform = `translate3d(${px}px,0,0)`;
        setTimeout(() => this.resetContainer(), 120);
    }

    navigateTo(page) {
        const p = Math.max(1, Math.min(page, this.totalPages));
        const u = new URL(this.url, window.location.origin);
        u.searchParams.set('page', String(p));
        window.location.href = u.toString();
    }

    previous() {
        if (this.page <= 1) {
            this.blockedBounce('previous');
            return;
        }
        this.navigateTo(this.page - 1);
    }

    next() {
        if (this.page >= this.totalPages) {
            this.blockedBounce('next');
            return;
        }
        this.navigateTo(this.page + 1);
    }

    destroy() {
        this.teardownListeners();
        this.clearFeedback();
        this.container.style.willChange = '';
        this.container.classList.remove('dragging', 'swipe-right', 'swipe-left');
    }
}
