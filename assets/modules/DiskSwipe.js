export default class DiskSwipe {
    constructor(containerSelector, cardElement, feedbackElements, threshold, from, playOnly) {
        this.container = document.querySelector(containerSelector);
        this.card = cardElement;
        this.threshold = threshold;

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

        this.from = from;
        this.playOnly = playOnly === true || playOnly === 'true';

        this.dragBound = this.drag.bind(this);
        this.stopDragBound = this.stopDrag.bind(this);

        this.startX = 0;
        this.startY = 0;
        this.isDragging = false;
        this.action = null;

        this.lockedAxis = null;
        this.lockThreshold = 10;
        this.EPS = 2;
        this.lastDx = 0;
        this.lastDy = 0;
        this.downTarget = null;

        this.initEventListeners();
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

    initEventListeners() {
        this.card.addEventListener('mousedown', this.startDrag.bind(this));
        this.card.addEventListener('touchstart', this.startDrag.bind(this), {passive: true});
        this.card.addEventListener('transitionend', this.removeResetClass.bind(this));

        window.addEventListener('pageshow', (e) => {
            const comingBack = sessionStorage.getItem('noRewind') === '1' || e.persisted;
            if (comingBack) {
                this.card.style.transition = 'none';
                this.card.style.transform = 'none';
                this.card.classList.remove('swipe-left', 'swipe-right', 'swipe-up', 'dragging');
                this.clearFeedback();
                requestAnimationFrame(() => {
                    this.card.style.transition = '';
                    sessionStorage.removeItem('noRewind');
                });
            }
        });

        window.addEventListener('pagehide', () => {
            this.card.style.transition = 'none';
            this.card.style.transform = 'none';
            this.card.classList.remove('swipe-left', 'swipe-right', 'swipe-up', 'dragging');
            this.clearFeedback();
        });
    }

    instantResetForNav() {
        this.card.style.transition = 'none';
        this.card.style.transform = 'none';
        this.card.classList.remove('swipe-left', 'swipe-right', 'swipe-up', 'dragging');
        this.clearFeedback();
        sessionStorage.setItem('noRewind', '1');
    }

    startDrag(e) {
        this.downTarget = e.target;
        this.isDragging = true;
        this.lockedAxis = null;
        this.action = null;
        this.clearFeedback();

        if (e.type === 'mousedown') {
            this.startX = e.clientX;
            this.startY = e.clientY;
        } else {
            this.startX = e.touches[0].clientX;
            this.startY = e.touches[0].clientY;
        }

        this.card.style.transition = 'none';
        this.card.style.willChange = 'transform';

        this.card.classList.add('dragging');
        this.card.style.touchAction = 'pan-y';

        document.addEventListener('mousemove', this.dragBound);
        document.addEventListener('touchmove', this.dragBound, {passive: false});
        document.addEventListener('mouseup', this.stopDragBound);
        document.addEventListener('touchend', this.stopDragBound);
    }

    drag(e) {
        if (!this.isDragging) return;

        if (this.lockedAxis && e.cancelable) e.preventDefault();

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
        this.lastDx = dx;
        this.lastDy = dy;

        const absDx = Math.abs(dx);
        const absDy = Math.abs(dy);

        if (absDx <= this.EPS && absDy <= this.EPS) {
            this.clearFeedback();
        }

        if (!this.lockedAxis) {
            if (absDx > this.lockThreshold || absDy > this.lockThreshold) {
                if (absDx >= absDy) {
                    this.lockedAxis = 'x';
                } else {
                    if (dy < 0) this.lockedAxis = 'y';
                    else return;
                }
            } else {
                if (absDx > this.EPS || absDy > this.EPS) {
                    if (absDx >= absDy) {
                        if (dx >= 0) this.setActiveFeedback('right');
                        else if (!this.playOnly && this.from !== 'playlist') this.setActiveFeedback('left');
                        else this.clearFeedback();
                    } else if (dy < 0 && !this.playOnly) {
                        this.setActiveFeedback('top');
                    } else {
                        this.clearFeedback();
                    }
                }
                return;
            }
        }

        this.container.style.overflow = 'hidden';
        this.card.classList.remove('swipe-right', 'swipe-left', 'swipe-up');
        this.action = null;

        if (this.lockedAxis === 'x') {
            const thresholdPx = this.threshold * this.container.offsetWidth;
            this.card.style.transform = `translate3d(${dx}px,0,0) rotate(${dx / 60}deg)`;

            if (dx >= 0) {
                this.setActiveFeedback('right');
            } else {
                if (this.from !== 'playlist' && !this.playOnly) this.setActiveFeedback('left');
                else this.clearFeedback();
            }

            if (dx > thresholdPx) {
                this.action = 'play';
                this.card.classList.add('swipe-right');
            } else if (dx < -thresholdPx) {
                if (this.from !== 'playlist' && !this.playOnly) {
                    this.action = 'cancel';
                    this.card.classList.add('swipe-left');
                }
            }
            return;
        }

        if (this.lockedAxis === 'y') {
            const thresholdPx = this.threshold * this.container.offsetHeight;
            const effectiveDy = Math.min(dy, 0);

            if (Math.abs(effectiveDy) <= this.EPS) {
                this.card.style.transform = '';
                this.action = null;
                return;
            }

            if (!this.playOnly) this.setActiveFeedback('top');
            this.card.style.transform = `translate3d(0,${effectiveDy}px,0)`;

            if (!this.playOnly && Math.abs(effectiveDy) > thresholdPx) {
                this.action = 'view';
                this.card.classList.add('swipe-up');
            }
        }
    }

    stopDrag() {
        if (!this.isDragging) return;

        const absDx = Math.abs(this.lastDx);
        const absDy = Math.abs(this.lastDy);
        const wasLocked = !!this.lockedAxis;

        this.isDragging = false;
        this.card.classList.remove('dragging');
        this.card.style.touchAction = '';

        const hasSwipe =
            this.card.classList.contains('swipe-right') ||
            this.card.classList.contains('swipe-left') ||
            this.card.classList.contains('swipe-up');

        if (!wasLocked && absDx <= this.EPS && absDy <= this.EPS) {
            document.removeEventListener('mousemove', this.dragBound);
            document.removeEventListener('touchmove', this.dragBound);
            document.removeEventListener('mouseup', this.stopDragBound);
            document.removeEventListener('touchend', this.stopDragBound);
            this.card.style.willChange = '';
            return;
        }

        if (hasSwipe) {
            switch (this.action) {
                case 'cancel':
                    if (this.from === 'flow') this.cancel();
                    else if (this.from === 'vault') this.goback();
                    break;
                case 'view':
                    if (this.from === 'flow') this.view();
                    else if (this.from === 'vault') this.delete();
                    break;
                case 'play':
                    if (this.from === 'flow') this.play();
                    else if (this.from === 'vault') this.edit();
                    break;
                default:
                    this.resetCard();
                    break;
            }
        } else {
            this.resetCard();
        }

        document.removeEventListener('mousemove', this.dragBound);
        document.removeEventListener('touchmove', this.dragBound);
        document.removeEventListener('mouseup', this.stopDragBound);
        document.removeEventListener('touchend', this.stopDragBound);

        this.card.style.willChange = '';
    }

    resetCard() {
        this.card.style.transition = 'transform 0.25s ease-out';
        this.card.style.transform = '';
        setTimeout(() => {
            this.card.style.transition = '';
        }, 250);
        this.container.style.overflow = 'visible';
        this.clearFeedback();
    }

    removeResetClass() {
        this.card.classList.remove('reset');
        this.container.style.overflow = 'visible';
    }

    hardReset() {
        const card = this.card;

        card.style.transition = 'none';
        card.style.transform = '';
        card.classList.remove(
            'swipe-left',
            'swipe-right',
            'swipe-up',
            'dragging',
        );

        this.clearFeedback();
        this.container.style.overflow = 'visible';
        card.style.willChange = '';
        void card.offsetHeight;
        setTimeout(() => card.style.transition = '', 0);
    }

    // --- Actions
    cancel() {
        this.card.classList.add('swipe-left');
        alert('Refus de la suggestion');
        setTimeout(() => {
            this.card.style.transition = '';
            this.card.style.transform = '';
            this.card.classList.remove('swipe-left');
            this.clearFeedback();
        }, 100);
    }

    view() {
        this.card.classList.add('swipe-up');
        setTimeout(() => {
            this.card.style.transition = 'transform 0.3s';
            this.card.style.transform = 'translateY(0)';
            setTimeout(() => {
                this.card.style.transition = '';
                this.card.style.transform = '';
                this.card.classList.remove('swipe-up');
                this.clearFeedback();
            }, 500);
        }, 500);
        window.location.href = '/vault/disque/details';
    }

    play() {
        this.card.classList.add('swipe-right');
        if (this.from === 'playlist') {
            alert('Lecture dans une playlist');
        } else {
            alert('Lecture de la suggestion');
        }
        setTimeout(() => {
            this.card.style.transition = '';
            this.card.style.transform = '';
            this.card.classList.remove('swipe-right');
            this.clearFeedback();
        }, 100);
    }

    goback() {
        this.instantResetForNav();
        const ref = document.referrer || '';
        if (ref.includes('/vault')) {
            window.location.href = '/vault';
        } else if (ref.includes('/flow')) {
            window.location.href = '/flow';
        } else {
            window.location.href = '/flow';
        }
    }

    delete() {
        const modalEl = document.getElementById('app-modal');
        const url = this.card.dataset.confirmUrl;
        const actionUrl = this.card.dataset.actionUrl;

        this.hardReset();

        requestAnimationFrame(() => {
            modalEl.dispatchEvent(new CustomEvent('modal:open', {
                bubbles: true,
                detail: {
                    title: 'Supprimer ce disque ?',
                    variant: 'confirm',
                    size: 's',
                    bodyUrl: url,
                    footerHtml: `
                    <button type="button" data-action="modal--modal#close">Annuler</button>
                    <button type="button" data-primary class="bg-danger" data-action="modal--modal#confirm">Supprimer</button>
                `,
                    actionUrl: actionUrl
                }
            }));
        });
    }

    edit() {
        this.card.classList.add('swipe-right');
        alert('Modifier le disque');
        setTimeout(() => {
            this.card.style.transition = '';
            this.card.style.transform = '';
            this.card.classList.remove('swipe-right');
            this.clearFeedback();
        }, 100);
    }
}
