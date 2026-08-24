const app = {
    partners: [],
    currentPage: 1,
    itemsPerPage: 9,
    searchTerm: '',
    sortBy: 'orgName-asc',
    viewMode: 'card',
    existingChoices: {
        pi: new Set(),
        neighborhood: new Set(),
        status: new Set(),
        orgTypes: new Set(),
        cities: new Set(),
        states: new Set()
    },
    
    apiConfig: {
        baseUrl: window.location.pathname.replace(/\/[^/]*$/, '')
    },

    async init() {
        this.bindEvents();
        this.fetchPartners();
    },

    bindEvents() {
        document.getElementById('itemsPerPage').addEventListener('change', (e) => {
            this.itemsPerPage = e.target.value === 'all' ? 'all' : parseInt(e.target.value);
            this.currentPage = 1;
            this.renderPartners();
        });

        document.getElementById('sortBy').addEventListener('change', (e) => {
            this.sortBy = e.target.value;
            this.currentPage = 1;
            this.renderPartners();
        });

        document.getElementById('searchInput').addEventListener('input', (e) => {
            this.searchTerm = e.target.value.toLowerCase();
            this.currentPage = 1;
            this.renderPartners();
        });

        // Custom dropdown handlers
        document.getElementById('pi').addEventListener('change', (e) => {
            this.handleCustomOption('pi', 'piCustom', e.target.value);
        });

        document.getElementById('neighborhood').addEventListener('change', (e) => {
            this.handleCustomOption('neighborhood', 'neighborhoodCustom', e.target.value);
        });

        document.getElementById('status').addEventListener('change', (e) => {
            this.handleCustomOption('status', 'statusCustom', e.target.value);
        });

        document.getElementById('orgType').addEventListener('change', (e) => {
            this.handleCustomOption('orgType', 'orgTypeCustom', e.target.value);
        });

        document.getElementById('city').addEventListener('change', (e) => {
            this.handleCustomOption('city', 'cityCustom', e.target.value);
        });

        document.getElementById('state').addEventListener('change', (e) => {
            this.handleCustomOption('state', 'stateCustom', e.target.value);
        });

        document.getElementById('country').addEventListener('change', (e) => {
            this.handleCustomOption('country', 'countryCustom', e.target.value);
        });

        document.getElementById('editModal').addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeModal();
            }
        });

        document.getElementById('notificationModal').addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeNotificationModal();
            }
        });
    },

    handleCustomOption(selectId, customInputId, value) {
        const customInput = document.getElementById(customInputId);
        if (!customInput) return;
        
        if (value === 'custom') {
            customInput.style.display = 'block';
            customInput.focus();
        } else {
            customInput.style.display = 'none';
            customInput.value = '';
        }
    },

    async fetchPartners() {
        const grid = document.getElementById('partnersGrid');
        grid.innerHTML = '<div class="loading">Loading partners...</div>';

        try {
            const url = `${this.apiConfig.baseUrl}/api/index.php?resource=partners`;
            console.log('Fetching partners from:', url);
            
            const response = await fetch(url, { method: 'GET' });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                console.error('Response error:', response.status, errorData);
                throw new Error(`HTTP error! status: ${response.status} - ${errorData.error || response.statusText}`);
            }

            const data = await response.json();
            console.log('Fetched records:', data.records?.length || 0);

            if (data.error) {
                grid.innerHTML = `<div class="error">Error: ${data.error}</div>`;
                return;
            }

            this.partners = data.records || [];
            this.extractExistingChoices();
            this.populateDropdowns();
            this.renderStats();
            this.renderPartners();
        } catch (error) {
            console.error('Error fetching partners:', error);
            grid.innerHTML = `<div class="error">${error.message}</div>`;
        }
    },

    renderStats() {
        const statsGrid = document.getElementById('statsGrid');
        if (!statsGrid) return;
        
        statsGrid.innerHTML = '';
    },

    extractExistingChoices() {
        this.existingChoices.pi.clear();
        this.existingChoices.neighborhood.clear();
        this.existingChoices.status.clear();
        this.existingChoices.orgTypes.clear();
        this.existingChoices.cities.clear();
        this.existingChoices.states.clear();

        this.partners.forEach(record => {
            const fields = record.fields;
            if (fields['PI']) this.existingChoices.pi.add(fields['PI']);
            if (fields['Neighborhood']) this.existingChoices.neighborhood.add(fields['Neighborhood']);
            if (fields['Status']) this.existingChoices.status.add(fields['Status']);
            if (fields['Org Type']) this.existingChoices.orgTypes.add(fields['Org Type']);
            if (fields['City']) this.existingChoices.cities.add(fields['City']);
            if (fields['State']) this.existingChoices.states.add(fields['State']);
        });
    },

    populateDropdowns() {
        this.populateSelectFromSet('neighborhood', this.existingChoices.neighborhood);
        this.populateSelectFromSet('status', this.existingChoices.status);
    },

    populateSelectFromSet(selectId, valuesSet) {
        const select = document.getElementById(selectId);
        if (!select) return;

        const existingValues = new Set(Array.from(select.options).map(o => o.value));
        const values = Array.from(valuesSet)
            .map(v => (typeof v === 'string' ? v.trim() : v))
            .filter(v => v);

        values.sort((a, b) => String(a).localeCompare(String(b)));

        const customOption = Array.from(select.options).find(o => o.value === 'custom');
        values.forEach(v => {
            if (existingValues.has(v)) return;
            const option = document.createElement('option');
            option.value = v;
            option.textContent = v;
            if (customOption) {
                select.insertBefore(option, customOption);
            } else {
                select.appendChild(option);
            }
            existingValues.add(v);
        });
    },

    toggleView() {
        this.viewMode = this.viewMode === 'card' ? 'table' : 'card';
        
        const gridView = document.getElementById('partnersGrid');
        const tableView = document.getElementById('partnersTable');
        const toggleBtn = document.getElementById('viewToggleBtn');
        const toggleText = document.getElementById('viewToggleText');
        
        if (this.viewMode === 'table') {
            gridView.style.display = 'none';
            tableView.style.display = 'block';
            toggleText.textContent = 'Card View';
        } else {
            gridView.style.display = 'grid';
            tableView.style.display = 'none';
            toggleText.textContent = 'Table View';
        }
        
        lucide.createIcons();
        this.renderPartners();
    },

    renderPartners() {
        if (this.viewMode === 'table') {
            this.renderTableView();
        } else {
            this.renderCardView();
        }
    },

    renderCardView() {
        const grid = document.getElementById('partnersGrid');
        grid.innerHTML = '';

        let filteredPartners = this.partners;
        if (this.searchTerm) {
            const term = this.searchTerm.toLowerCase();
            filteredPartners = this.partners.filter(record => {
                const f = record.fields;
                return (f['Org Name '] || '').toLowerCase().includes(term) ||
                       (f['Org Contact (and Title)'] || '').toLowerCase().includes(term) ||
                       (f['Org Type'] || '').toLowerCase().includes(term) ||
                       (f['PI'] || '').toLowerCase().includes(term) ||
                       (f['City'] || '').toLowerCase().includes(term);
            });
        }

        // Sort the filtered partners
        if (this.sortBy === 'orgName-asc') {
            filteredPartners.sort((a, b) => {
                const nameA = (a.fields['Org Name '] || '').toLowerCase();
                const nameB = (b.fields['Org Name '] || '').toLowerCase();
                return nameA.localeCompare(nameB);
            });
        } else if (this.sortBy === 'orgName-desc') {
            filteredPartners.sort((a, b) => {
                const nameA = (a.fields['Org Name '] || '').toLowerCase();
                const nameB = (b.fields['Org Name '] || '').toLowerCase();
                return nameB.localeCompare(nameA);
            });
        }

        if (filteredPartners.length === 0) {
            grid.innerHTML = '<div class="no-results">No partners found.</div>';
            this.renderPagination(0);
            return;
        }

        let displayPartners = filteredPartners;
        
        if (this.itemsPerPage !== 'all') {
            const startIndex = (this.currentPage - 1) * this.itemsPerPage;
            const endIndex = startIndex + this.itemsPerPage;
            displayPartners = filteredPartners.slice(startIndex, endIndex);
        }

        displayPartners.forEach(record => {
            const fields = record.fields;
            const card = document.createElement('div');
            card.className = 'event-card';
            card.onclick = () => this.openEditModal(record);

            const mouStatus = fields['with MOUs/Subaward? '] || 'Non-Formal';
            const statusClass = mouStatus.includes('Formal') ? 'status-ready' : 'status-tentative';
            
            const orgName = fields['Org Name '] || 'Untitled Org';
            const contact = fields['Org Contact (and Title)'] || 'No contact';
            const type = fields['Org Type'] || 'Unspecified Type';
            const pi = fields['PI'] || '';
            const location = [fields['City'], fields['State']].filter(Boolean).join(', ') || 'No location';
            
            card.innerHTML = `
                <div class="event-header">
                    <div class="event-date-badge">
                        <i data-lucide="building-2" style="width: 24px; height: 24px;"></i>
                    </div>
                </div>
                
                <h3 class="event-title">${orgName}</h3>
                
                <div class="event-meta">
                    <div class="meta-item">
                        <i data-lucide="user" style="width: 16px; height: 16px;"></i>
                        <span>${contact}</span>
                    </div>
                    <div class="meta-item">
                        <i data-lucide="tag" style="width: 16px; height: 16px;"></i>
                        <span>${type}</span>
                    </div>
                    <div class="meta-item">
                        <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                        <span>${location}</span>
                    </div>
                    ${pi ? `<div class="meta-item">
                        <i data-lucide="briefcase" style="width: 16px; height: 16px;"></i>
                        <span>PI: ${pi}</span>
                    </div>` : ''}
                </div>

                <span class="event-status ${statusClass}">
                    ${mouStatus}
                </span>
            `;
            
            grid.appendChild(card);
        });
        
        lucide.createIcons();
        this.renderPagination(filteredPartners.length);
    },

    renderTableView() {
        const tbody = document.getElementById('partnersTableBody');
        tbody.innerHTML = '';

        let filteredPartners = this.partners;
        if (this.searchTerm) {
            const term = this.searchTerm.toLowerCase();
            filteredPartners = this.partners.filter(record => {
                const f = record.fields;
                return (f['Org Name '] || '').toLowerCase().includes(term) ||
                       (f['Org Contact (and Title)'] || '').toLowerCase().includes(term) ||
                       (f['Org Type'] || '').toLowerCase().includes(term) ||
                       (f['PI'] || '').toLowerCase().includes(term) ||
                       (f['City'] || '').toLowerCase().includes(term);
            });
        }

        if (this.sortBy === 'orgName-asc') {
            filteredPartners.sort((a, b) => {
                const nameA = (a.fields['Org Name '] || '').toLowerCase();
                const nameB = (b.fields['Org Name '] || '').toLowerCase();
                return nameA.localeCompare(nameB);
            });
        } else if (this.sortBy === 'orgName-desc') {
            filteredPartners.sort((a, b) => {
                const nameA = (a.fields['Org Name '] || '').toLowerCase();
                const nameB = (b.fields['Org Name '] || '').toLowerCase();
                return nameB.localeCompare(nameA);
            });
        }

        if (filteredPartners.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="no-results">No partners found.</td></tr>';
            this.renderPagination(0);
            return;
        }

        let displayPartners = filteredPartners;
        
        if (this.itemsPerPage !== 'all') {
            const startIndex = (this.currentPage - 1) * this.itemsPerPage;
            const endIndex = startIndex + this.itemsPerPage;
            displayPartners = filteredPartners.slice(startIndex, endIndex);
        }

        displayPartners.forEach(record => {
            const fields = record.fields;
            const row = document.createElement('tr');
            
            const orgName = fields['Org Name '] || 'Untitled Org';
            const contact = fields['Org Contact (and Title)'] || 'No contact';
            const type = fields['Org Type'] || 'Unspecified';
            const pi = fields['PI'] || 'Unassigned';
            const city = fields['City'] || '-';
            const state = fields['State'] || '-';
            const mouStatus = fields['with MOUs/Subaward? '] || 'Non-Formal';
            const mouClass = mouStatus.includes('Formal') ? 'formal' : 'non-formal';
            
            row.innerHTML = `
                <td><span class="org-name">${orgName}</span></td>
                <td>${contact}</td>
                <td>${type}</td>
                <td>${pi}</td>
                <td>${city}</td>
                <td>${state}</td>
                <td><span class="mou-badge ${mouClass}">${mouStatus}</span></td>
            `;
            
            row.onclick = () => this.openEditModal(record);
            tbody.appendChild(row);
        });
        
        lucide.createIcons();
        this.renderPagination(filteredPartners.length);
    },

    renderPagination(totalItems) {
        const container = document.getElementById('paginationControls');
        container.innerHTML = '';

        if (this.itemsPerPage === 'all' || totalItems === 0) return;

        const totalPages = Math.ceil(totalItems / this.itemsPerPage);
        if (totalPages <= 1) return;

        const prevBtn = document.createElement('button');
        prevBtn.className = 'page-btn';
        prevBtn.innerHTML = '<i data-lucide="chevron-left" style="width: 16px; height: 16px;"></i>';
        prevBtn.disabled = this.currentPage === 1;
        prevBtn.onclick = () => {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.renderPartners();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        };
        container.appendChild(prevBtn);

        const pages = [];
        if (totalPages <= 7) {
            for (let i = 1; i <= totalPages; i++) pages.push(i);
        } else {
            if (this.currentPage <= 4) {
                pages.push(1, 2, 3, 4, 5, '...', totalPages);
            } else if (this.currentPage >= totalPages - 3) {
                pages.push(1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages);
            } else {
                pages.push(1, '...', this.currentPage - 1, this.currentPage, this.currentPage + 1, '...', totalPages);
            }
        }

        pages.forEach(p => {
            if (p === '...') {
                const dots = document.createElement('span');
                dots.className = 'page-dots';
                dots.innerText = '...';
                container.appendChild(dots);
            } else {
                const btn = document.createElement('button');
                btn.className = `page-btn ${p === this.currentPage ? 'active' : ''}`;
                btn.innerText = p;
                btn.onclick = () => {
                    this.currentPage = p;
                    this.renderPartners();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                };
                container.appendChild(btn);
            }
        });

        const nextBtn = document.createElement('button');
        nextBtn.className = 'page-btn';
        nextBtn.innerHTML = '<i data-lucide="chevron-right" style="width: 16px; height: 16px;"></i>';
        nextBtn.disabled = this.currentPage === totalPages;
        nextBtn.onclick = () => {
            if (this.currentPage < totalPages) {
                this.currentPage++;
                this.renderPartners();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        };
        container.appendChild(nextBtn);
        
        lucide.createIcons();
    },

    getStatusClass(status) {
        if (!status) return 'status-tentative';
        switch (status.toLowerCase()) {
            case 'active': return 'status-ready';
            case 'inactive': return 'status-completed';
            default: return 'status-tentative';
        }
    },

    openEditModal(record) {
        const fields = record.fields;
        const modal = document.getElementById('editModal');
        modal.querySelector('.modal-title').textContent = 'Edit Community Partner';
        
        document.getElementById('partnerId').value = record.id;
        
        // Populate fields matching Airtable schema
        document.getElementById('orgName').value = fields['Org Name '] || '';
        document.getElementById('orgContact').value = fields['Org Contact (and Title)'] || '';
        
        // Handle PI with custom option support
        this.setDropdownWithCustom('pi', 'piCustom', fields['PI'] || '');
        
        // Handle Neighborhood with custom option support
        this.setDropdownWithCustom('neighborhood', 'neighborhoodCustom', fields['Neighborhood'] || '');

        // Handle Status with custom option support
        this.setDropdownWithCustom('status', 'statusCustom', fields['Status'] || '');
        
        // Handle Org Type with custom option support
        this.setDropdownWithCustom('orgType', 'orgTypeCustom', fields['Org Type'] || '');
        
        document.getElementById('address').value = fields['Address'] || '';
        
        // Handle City with custom option support
        this.setDropdownWithCustom('city', 'cityCustom', fields['City'] || '');
        
        // Handle State with custom option support
        this.setDropdownWithCustom('state', 'stateCustom', fields['State'] || '');
        
        document.getElementById('postalCode').value = fields['Postal Code'] || '';
        
        // Handle Country with custom option support
        this.setDropdownWithCustom('country', 'countryCustom', fields['Country'] || 'United States');
        
        document.getElementById('mouStatus').value = fields['with MOUs/Subaward? '] || '';

        modal.classList.add('active');
    },

    openAddModal() {
        const modal = document.getElementById('editModal');
        modal.querySelector('.modal-title').textContent = 'New Partner';
        
        document.getElementById('partnerId').value = '';
        document.getElementById('editForm').reset();
        
        // Reset custom dropdowns
        this.setDropdownWithCustom('pi', 'piCustom', '');
        this.setDropdownWithCustom('neighborhood', 'neighborhoodCustom', '');
        this.setDropdownWithCustom('status', 'statusCustom', '');
        this.setDropdownWithCustom('orgType', 'orgTypeCustom', '');
        this.setDropdownWithCustom('city', 'cityCustom', '');
        this.setDropdownWithCustom('state', 'stateCustom', '');
        
        // Set default Country (using setDropdownWithCustom to handle the default correctly)
        this.setDropdownWithCustom('country', 'countryCustom', 'United States');

        modal.classList.add('active');
    },

    setDropdownWithCustom(selectId, customInputId, value) {
        const select = document.getElementById(selectId);
        const customInput = document.getElementById(customInputId);
        const trimmedValue = value ? value.trim() : '';
        
        let optionExists = false;
        for (let option of select.options) {
            if (option.value === trimmedValue) {
                optionExists = true;
                break;
            }
        }
        
        if (optionExists) {
            select.value = trimmedValue;
            customInput.style.display = 'none';
            customInput.value = '';
        } else if (trimmedValue) {
            select.value = 'custom';
            customInput.style.display = 'block';
            customInput.value = trimmedValue;
        } else {
            select.value = '';
            customInput.style.display = 'none';
            customInput.value = '';
        }
    },

    closeModal() {
        document.getElementById('editModal').classList.remove('active');
    },

    showNotification(title, message) {
        document.getElementById('notificationTitle').textContent = title;
        document.getElementById('notificationMessage').textContent = message;
        const modal = document.getElementById('notificationModal');
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
    },

    closeNotificationModal() {
        const modal = document.getElementById('notificationModal');
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    },

    getValueFromCustomDropdown(selectId, customInputId) {
        const select = document.getElementById(selectId);
        const customInput = document.getElementById(customInputId);
        
        if (select.value === 'custom') {
            return customInput.value.trim();
        }
        return select.value;
    },

    async savePartner() {
        const id = document.getElementById('partnerId').value;
        
        const piValue = this.getValueFromCustomDropdown('pi', 'piCustom');
        const neighborhoodValue = this.getValueFromCustomDropdown('neighborhood', 'neighborhoodCustom');
        const statusValue = this.getValueFromCustomDropdown('status', 'statusCustom');
        const orgTypeValue = this.getValueFromCustomDropdown('orgType', 'orgTypeCustom');
        const cityValue = this.getValueFromCustomDropdown('city', 'cityCustom');
        const stateValue = this.getValueFromCustomDropdown('state', 'stateCustom');
        const countryValue = this.getValueFromCustomDropdown('country', 'countryCustom');
        
        const fields = {
            'Org Name ': document.getElementById('orgName').value,
            'Org Contact (and Title)': document.getElementById('orgContact').value,
            'PI': piValue,
            'Neighborhood': neighborhoodValue,
            'Status': statusValue,
            'Org Type': orgTypeValue,
            'Address': document.getElementById('address').value,
            'City': cityValue,
            'State': stateValue,
            'Postal Code': parseInt(document.getElementById('postalCode').value) || null,
            'Country': countryValue,
            'with MOUs/Subaward? ': document.getElementById('mouStatus').value
        };

        const saveBtn = document.querySelector('.modal-footer .btn-primary');
        const originalText = saveBtn.innerText;
        saveBtn.innerText = 'Saving...';
        saveBtn.disabled = true;

        try {
            let url = `${this.apiConfig.baseUrl}/api/index.php?resource=partners`;
            let method = 'POST';

            if (id) {
                url += `&id=${id}`;
                method = 'PATCH';
            }
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ fields: fields })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            this.closeModal();
            this.fetchPartners();
            this.showNotification('Success', 'Partner saved successfully!');
        } catch (error) {
            console.error('Error saving partner:', error);
            this.showNotification('Error', 'Failed to save partner. Please try again.');
        } finally {
            saveBtn.innerText = originalText;
            saveBtn.disabled = false;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    app.init();
});
