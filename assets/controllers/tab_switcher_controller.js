import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'panel', 'indicator'];
    static values = { active: { type: Number, default: 0 } };

    connect() {
        this._activate(this.activeValue, true);
    }

    switch(event) {
        const index = parseInt(event.currentTarget.dataset.index, 10);
        if (index === this.activeValue) return;
        this._activate(index, false);
    }

    _activate(index, immediate) {
        this.activeValue = index;

        // Update tab states
        this.tabTargets.forEach((tab, i) => {
            const isActive = i === index;
            tab.classList.toggle('tab-active', isActive);
            tab.classList.toggle('tab-inactive', !isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        // Slide the indicator
        if (this.hasIndicatorTarget) {
            const activeTab = this.tabTargets[index];
            if (activeTab) {
                const container = activeTab.parentElement;
                const offsetLeft = activeTab.offsetLeft - container.offsetLeft;
                const width = activeTab.offsetWidth;
                this.indicatorTarget.style.transition = immediate ? 'none' : 'all 0.35s cubic-bezier(0.16, 1, 0.3, 1)';
                this.indicatorTarget.style.left = offsetLeft + 'px';
                this.indicatorTarget.style.width = width + 'px';
            }
        }

        // Switch panels with animation
        this.panelTargets.forEach((panel, i) => {
            if (i === index) {
                panel.classList.remove('hidden');
                if (!immediate) {
                    panel.style.opacity = '0';
                    panel.style.transform = 'translateY(8px)';
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            panel.style.transition = 'opacity 0.35s ease, transform 0.35s cubic-bezier(0.16, 1, 0.3, 1)';
                            panel.style.opacity = '1';
                            panel.style.transform = 'translateY(0)';
                        });
                    });
                } else {
                    panel.style.opacity = '1';
                    panel.style.transform = 'translateY(0)';
                }
            } else {
                panel.classList.add('hidden');
                panel.style.opacity = '0';
                panel.style.transform = 'translateY(8px)';
            }
        });
    }
}
