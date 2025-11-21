export default class CardSwipe {
    constructor(containerSelector, cardElement, feedbackElementId, threshold, from, playOnly) {
        this.container = document.querySelector(containerSelector);
        this.card = cardElement;
        this.threshold = threshold;

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

        this.initEventListeners();
    }

    initEventListeners() {
        this.card.addEventListener('mousedown', this.startDrag.bind(this));
        this.card.addEventListener('touchstart', this.startDrag.bind(this), {passive: false});
        this.card.addEventListener('transitionend', this.removeResetClass.bind(this));

        window.addEventListener('pageshow', (e) => {
            const comingBack = sessionStorage.getItem('noRewind') === '1' || e.persisted;
            if (comingBack) {
                this.card.style.transition = 'none';
                this.card.style.transform = 'none';
                this.card.classList.remove('swipe-left', 'swipe-right', 'swipe-up', 'dragging');

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
        });
    }

    instantResetForNav() {
        this.card.style.transition = 'none';
        this.card.style.transform = 'none';
        this.card.classList.remove('swipe-left', 'swipe-right', 'swipe-up', 'dragging');
        sessionStorage.setItem('noRewind', '1');
    }

    startDrag(e) {
        if (e.cancelable) e.preventDefault();

        const currentTag = e.target;

        if (currentTag.tagName.toLowerCase() === 'i') {
            if (currentTag.parentNode.tagName.toLowerCase() === 'span') {
                if (currentTag.parentNode.parentNode.tagName.toLowerCase() === 'a') {
                    currentTag.parentNode.parentNode.click();
                    return;
                }
            }
        } else if (currentTag.tagName.toLowerCase() === 'a') {
            currentTag.click();
            return;
        }

        this.isDragging = true;
        this.lockedAxis = null;
        this.action = null;

        if (e.type === 'mousedown') {
            this.startX = e.clientX;
            this.startY = e.clientY;
        } else if (e.type === 'touchstart') {
            this.startX = e.touches[0].clientX;
            this.startY = e.touches[0].clientY;
        }

        this.card.style.transition = 'none';
        this.card.style.willChange = 'transform';

        this.card.classList.add('dragging');
        this.card.style.touchAction = 'pan-y';

        document.addEventListener('mousemove', this.dragBound);
        document.addEventListener('touchmove', this.dragBound, {passive: false});
        document.addEventListener('mouseup', this.stopDragBound, {passive: false});
        document.addEventListener('touchend', this.stopDragBound, {passive: false});
    }

    drag(e) {
        if (!this.isDragging) return;

        if (e.cancelable) {
            e.preventDefault();
        }

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

        const absDx = Math.abs(dx);
        const absDy = Math.abs(dy);

        if (!this.lockedAxis) {
            if (absDx > this.lockThreshold || absDy > this.lockThreshold) {
                if (absDx >= absDy) {
                    // axe horizontal
                    this.lockedAxis = 'x';
                } else {
                    // axe vertical (haut uniquement)
                    if (dy < 0) {
                        this.lockedAxis = 'y';
                    } else {
                        return;
                    }
                }
            } else {
                return;
            }
        }

        this.container.style.overflow = 'hidden';

        this.card.classList.remove('swipe-right', 'swipe-left', 'swipe-up');
        this.action = null;

        // --------- AXE HORIZONTAL (gauche / droite) ----------
        if (this.lockedAxis === 'x') {
            const thresholdPx = this.threshold * this.container.offsetWidth;

            this.card.style.transform = `translate3d(${dx}px,0,0) rotate(${dx / 60}deg)`;

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

        // --------- AXE VERTICAL (HAUT UNIQUEMENT) ----------
        if (this.lockedAxis === 'y') {
            const thresholdPx = this.threshold * this.container.offsetHeight;

            const effectiveDy = Math.min(dy, 0);

            if (effectiveDy === 0) {
                this.card.style.transform = '';
                this.action = null;
                return;
            }

            this.card.style.transform = `translate3d(0,${effectiveDy}px,0)`;

            if (!this.playOnly && effectiveDy < -thresholdPx) {
                this.action = 'view';
                this.card.classList.add('swipe-up');
            }
        }
    }

    stopDrag(e) {
        if (!this.isDragging) return;

        this.isDragging = false;
        this.card.classList.remove('dragging');
        this.card.style.touchAction = '';

        const hasSwipe =
            this.card.classList.contains('swipe-right') ||
            this.card.classList.contains('swipe-left') ||
            this.card.classList.contains('swipe-up');

        if (hasSwipe) {
            switch (this.action) {
                case 'cancel':
                    if (this.from === 'flow') {
                        this.cancel(e);
                    } else if (this.from === 'vault') {
                        this.goback(e);
                    }
                    break;
                case 'view':
                    if (this.from === 'flow') {
                        this.view(e);
                    } else if (this.from === 'vault') {
                        this.delete(e);
                    }
                    break;
                case 'play':
                    if (this.from === 'flow') {
                        this.play(e);
                    } else if (this.from === 'vault') {
                        this.edit(e);
                    }
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
    }

    removeResetClass() {
        this.card.classList.remove('reset');
        this.container.style.overflow = 'visible';
    }

    cancel(e) {
        this.card.classList.add('swipe-left');
        alert('Refus de la suggestion');
        setTimeout(() => {
            this.card.style.transition = '';
            this.card.style.transform = '';
            this.card.classList.remove('swipe-left');
        }, 100);
    }

    view(e) {
        this.card.classList.add('swipe-up');

        setTimeout(() => {
            this.card.style.transition = 'transform 0.3s';
            this.card.style.transform = 'translateY(0)';

            setTimeout(() => {
                this.card.style.transition = '';
                this.card.style.transform = '';
                this.card.classList.remove('swipe-up');
            }, 500);
        }, 500);

        window.location.href = '/disque/details';
    }

    play(e) {
        this.card.classList.add('swipe-right');
        if (this.from === 'playlist') {
            //window.location.href = '/vinylehub/playlist/ajax/ecouter/' + this.card.dataset.id;
            alert('Lecture dans une playlist');
        } else {
            //window.location.href = '/vinylematch/suggest/ajax/decision/' + this.card.dataset.id + '/1';
            alert('Lecture de la suggestion');
        }
        setTimeout(() => {
            this.card.style.transition = '';
            this.card.style.transform = '';
            this.card.classList.remove('swipe-right');
        }, 100);
    }

    goback(e) {
        this.instantResetForNav();

        if (document.referrer && document.referrer !== window.location.href) {
            history.back();
        } else {
            window.location.href = '/flow';
        }
    }

    delete(e) {
        this.card.classList.add('swipe-up');
        alert('Supprimer le disque');
        setTimeout(() => {
            this.card.style.transition = '';
            this.card.style.transform = '';
            this.card.classList.remove('swipe-up');
        }, 100);
    }

    edit(e) {
        this.card.classList.add('swipe-right');
        alert('Modifier le disque');
        setTimeout(() => {
            this.card.style.transition = '';
            this.card.style.transform = '';
            this.card.classList.remove('swipe-right');
        }, 100);
    }
}
