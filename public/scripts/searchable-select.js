/**
 * Searchable Select Component
 * Transforms a standard <select> into a searchable combobox
 */

class SearchableSelect {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            placeholder: options.placeholder || 'Wpisz lub wybierz...',
            noResultsText: options.noResultsText || 'Brak wyników',
            showAvatar: options.showAvatar !== false,
            patients: options.patients || [],
            ...options
        };

        this.select = container.querySelector('select');
        this.input = container.querySelector('.searchable-select__input');
        this.toggle = container.querySelector('.searchable-select__toggle');
        this.clear = container.querySelector('.searchable-select__clear');
        this.dropdown = container.querySelector('.searchable-select__dropdown');

        this.isOpen = false;
        this.focusedIndex = -1;
        this.items = [];

        this.init();
    }

    init() {
        this.buildItems();
        this.renderDropdown();
        this.bindEvents();
        
        // Set initial value if select has one
        if (this.select.value) {
            const item = this.items.find(i => i.value === this.select.value);
            if (item) {
                this.selectItem(item, false);
            }
        }
    }

    buildItems() {
        this.items = [];
        const options = this.select.querySelectorAll('option');
        
        options.forEach(option => {
            if (!option.value) return; // Skip placeholder option
            
            const value = option.value;
            const text = option.textContent.trim();
            
            // Try to get patient info from patients array
            const patient = this.options.patients.find(p => 
                String(p.patient_user_id) === String(value)
            );
            
            let name = text;
            let email = '';
            let initials = '';
            
            if (patient) {
                // Keys from PsychologistRepository: full_name, email
                name = patient.full_name || patient.name || text;
                email = patient.email || '';
                initials = this.getInitials(name);
            } else {
                initials = this.getInitials(text);
            }
            
            this.items.push({
                value,
                text,
                name,
                email,
                initials,
                searchText: `${name} ${email}`.toLowerCase()
            });
        });
    }

    getInitials(name) {
        return name
            .split(' ')
            .map(part => part.charAt(0))
            .join('')
            .toUpperCase()
            .slice(0, 2);
    }

    renderDropdown(filter = '') {
        const filterLower = filter.toLowerCase().trim();
        let html = '';
        let hasResults = false;

        this.items.forEach((item, index) => {
            const matches = !filterLower || item.searchText.includes(filterLower);
            if (!matches) return;

            hasResults = true;
            const isSelected = this.select.value === item.value;
            const isFocused = this.focusedIndex === index;
            
            let classes = 'searchable-select__option';
            if (isSelected) classes += ' is-selected';
            if (isFocused) classes += ' is-focused';

            html += `
                <div class="${classes}" data-value="${item.value}" data-index="${index}">
                    ${this.options.showAvatar ? `
                        <div class="searchable-select__option-avatar">${item.initials}</div>
                    ` : ''}
                    <div class="searchable-select__option-text">
                        <div class="searchable-select__option-name">${this.highlightMatch(item.name, filter)}</div>
                        ${item.email ? `<div class="searchable-select__option-email">${item.email}</div>` : ''}
                    </div>
                </div>
            `;
        });

        if (!hasResults) {
            html = `
                <div class="searchable-select__no-results">
                    <div class="searchable-select__no-results-icon">🔍</div>
                    <div>${this.options.noResultsText}</div>
                </div>
            `;
        }

        this.dropdown.innerHTML = html;
    }

    highlightMatch(text, filter) {
        if (!filter) return text;
        
        const regex = new RegExp(`(${this.escapeRegex(filter)})`, 'gi');
        return text.replace(regex, '<strong>$1</strong>');
    }

    escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    bindEvents() {
        // Input events
        this.input.addEventListener('input', () => {
            this.renderDropdown(this.input.value);
            this.open();
        });

        this.input.addEventListener('focus', () => {
            this.renderDropdown(this.input.value);
            this.open();
        });

        this.input.addEventListener('keydown', (e) => this.handleKeydown(e));

        // Toggle button
        this.toggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (this.isOpen) {
                this.close();
            } else {
                this.input.focus();
                this.renderDropdown('');
                this.open();
            }
        });

        // Clear button
        this.clear.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.clearSelection();
        });

        // Dropdown click
        this.dropdown.addEventListener('click', (e) => {
            const option = e.target.closest('.searchable-select__option');
            if (option) {
                const value = option.dataset.value;
                const item = this.items.find(i => i.value === value);
                if (item) {
                    this.selectItem(item);
                }
            }
        });

        // Click outside to close
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target)) {
                this.close();
            }
        });
    }

    handleKeydown(e) {
        const visibleOptions = this.dropdown.querySelectorAll('.searchable-select__option');
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.focusedIndex = Math.min(this.focusedIndex + 1, visibleOptions.length - 1);
                this.updateFocusedOption();
                break;

            case 'ArrowUp':
                e.preventDefault();
                this.focusedIndex = Math.max(this.focusedIndex - 1, 0);
                this.updateFocusedOption();
                break;

            case 'Enter':
                e.preventDefault();
                if (this.focusedIndex >= 0 && visibleOptions[this.focusedIndex]) {
                    const value = visibleOptions[this.focusedIndex].dataset.value;
                    const item = this.items.find(i => i.value === value);
                    if (item) {
                        this.selectItem(item);
                    }
                }
                break;

            case 'Escape':
                this.close();
                break;

            case 'Tab':
                this.close();
                break;
        }
    }

    updateFocusedOption() {
        const options = this.dropdown.querySelectorAll('.searchable-select__option');
        options.forEach((opt, i) => {
            opt.classList.toggle('is-focused', i === this.focusedIndex);
        });

        // Scroll into view
        if (options[this.focusedIndex]) {
            options[this.focusedIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    selectItem(item, triggerChange = true) {
        this.select.value = item.value;
        this.input.value = item.name;
        this.container.classList.add('has-value');
        this.close();

        if (triggerChange) {
            // Dispatch change event on original select
            this.select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    clearSelection() {
        this.select.value = '';
        this.input.value = '';
        this.container.classList.remove('has-value');
        this.renderDropdown('');
        this.input.focus();

        // Dispatch change event
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    open() {
        if (this.isOpen) return;
        this.isOpen = true;
        this.focusedIndex = -1;
        this.container.classList.add('is-open');
    }

    close() {
        if (!this.isOpen) return;
        this.isOpen = false;
        this.container.classList.remove('is-open');
        
        // If input doesn't match any selection, restore previous value
        const currentValue = this.select.value;
        if (currentValue) {
            const item = this.items.find(i => i.value === currentValue);
            if (item) {
                this.input.value = item.name;
            }
        } else {
            this.input.value = '';
        }
    }

    // Public method to set value programmatically
    setValue(value) {
        const item = this.items.find(i => i.value === String(value));
        if (item) {
            this.selectItem(item, false);
        }
    }

    // Public method to get current value
    getValue() {
        return this.select.value;
    }
}

// Function to initialize searchable selects
function initSearchableSelects() {
    // Try to get patients data from various sources
    let patientsData = window.__patients || [];
    
    // If not available yet, try to parse from data attribute
    if (!patientsData.length) {
        const analysisGrid = document.querySelector('.dashboard-grid--analysis');
        const psychologistGrid = document.querySelector('.dashboard-grid--psychologist');
        const dataSource = analysisGrid || psychologistGrid;
        
        if (dataSource && dataSource.dataset.patients) {
            try {
                patientsData = JSON.parse(dataSource.dataset.patients);
                window.__patients = patientsData;
            } catch (e) {
                console.warn('Failed to parse patients data');
            }
        }
    }
    
    document.querySelectorAll('.searchable-select').forEach(container => {
        // Skip if already initialized
        if (container.dataset.initialized) return;
        container.dataset.initialized = 'true';
        
        new SearchableSelect(container, {
            patients: patientsData,
            showAvatar: true
        });
    });
}

// Auto-initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', initSearchableSelects);

// Also allow manual initialization via global function
window.initSearchableSelects = initSearchableSelects;

// Export for manual initialization
window.SearchableSelect = SearchableSelect;
