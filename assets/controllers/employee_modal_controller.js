import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'overlay', 'backdrop', 'panel',
        'detailId', 'detailFullname', 'detailEmail',
        'detailAge', 'detailJob', 'detailDepartment', 'detailRhId',
        'detailRhName', 'detailCreated', 'detailUpdated',
        'avatarInitials', 'avatarLarge'
    ];

    open(event) {
        const row = event.currentTarget;
        const first = row.dataset.firstName || '';
        const last = row.dataset.lastName || '';
        const initials = (first.charAt(0) + last.charAt(0)).toUpperCase();

        this.detailIdTarget.textContent = row.dataset.employeeId || '-';
        this.detailFullnameTarget.textContent = (first + ' ' + last).trim() || '-';
        this.detailEmailTarget.textContent = row.dataset.email || '-';
        this.detailAgeTarget.textContent = row.dataset.age ? row.dataset.age + ' ans' : '-';
        this.detailJobTarget.textContent = row.dataset.jobTitle || '-';
        if (this.hasDetailDepartmentTarget) {
            this.detailDepartmentTarget.textContent = row.dataset.department || '-';
        }
        this.detailRhIdTarget.textContent = row.dataset.rhId || '-';
        this.detailRhNameTarget.textContent = row.dataset.rhName || '-';
        this.detailCreatedTarget.textContent = row.dataset.createdAt || '-';
        this.detailUpdatedTarget.textContent = row.dataset.updatedAt || '-';

        if (this.hasAvatarInitialsTarget) {
            this.avatarInitialsTarget.textContent = initials;
        }
        if (this.hasAvatarLargeTarget) {
            this.avatarLargeTarget.textContent = initials;
        }

        this.overlayTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        requestAnimationFrame(() => {
            this.backdropTarget.classList.remove('opacity-0');
            this.backdropTarget.classList.add('opacity-100');
            this.panelTarget.classList.remove('translate-x-full');
            this.panelTarget.classList.add('translate-x-0');
        });
    }

    close() {
        this.backdropTarget.classList.remove('opacity-100');
        this.backdropTarget.classList.add('opacity-0');
        this.panelTarget.classList.remove('translate-x-0');
        this.panelTarget.classList.add('translate-x-full');

        setTimeout(() => {
            this.overlayTarget.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }, 300);
    }

    closeOnEscape(event) {
        if (event.key === 'Escape' && !this.overlayTarget.classList.contains('hidden')) {
            this.close();
        }
    }
}
