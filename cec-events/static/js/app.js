const app = {
    currentYear: '2026',
    events: [],
    currentPage: 1,
    itemsPerPage: 9,
    searchTerm: '',
    sortBy: 'date-desc',
    viewMode: 'card',
    statusFilter: '',
    dateFrom: '',
    dateTo: '',
    filtersVisible: false,
    existingChoices: {
        types: new Set(),
        statuses: new Set(),
        demographics: new Set(),
        attendees: new Set(),
        neighborhoods: new Set()
    },
    
    // MySQL PHP API Configuration
    apiConfig: {
        baseUrl: window.location.pathname.replace(/\/[^/]*$/, '') // dynamic base path
    },

    async init() {
        this.bindEvents();
        await this.populateYearDropdown();
        await this.fetchEvents();
        this.handleUrlOnLoad();
    },

    handleUrlOnLoad() {
        const hash = window.location.hash;
        if (hash.startsWith('#event=')) {
            const eventId = hash.substring(7);
            const event = this.events.find(e => e.id === eventId);
            if (event) {
                this.openEditModal(event);
            }
        } else if (hash.startsWith('#year=')) {
            const year = hash.substring(6);
            const yearSelect = document.getElementById('yearSelect');
            const tableName = `${year} Events`;
            for (let option of yearSelect.options) {
                if (option.value === tableName) {
                    yearSelect.value = tableName;
                    this.currentYear = year;
                    this.fetchEvents();
                    break;
                }
            }
        }
    },

    updateUrl(eventId = null, year = null) {
        if (eventId) {
            window.history.pushState({eventId}, '', `#event=${eventId}`);
        } else if (year) {
            window.history.pushState({year}, '', `#year=${year}`);
        } else {
            window.history.pushState({}, '', window.location.pathname);
        }
    },

    bindEvents() {
        document.getElementById('yearSelect').addEventListener('change', (e) => {
            console.log('Year select changed:', e.target.value);
            if (e.target.value === 'new') {
                console.log('New year option selected');
                this.openYearModal();
            } else {
                // Extract year from value (e.g., "2026 Events" -> "2026")
                this.currentYear = e.target.value.split(' ')[0];
                this.updateUrl(null, this.currentYear);
                this.fetchEvents();
            }
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', (e) => {
            if (e.state && e.state.eventId) {
                const event = this.events.find(ev => ev.id === e.state.eventId);
                if (event) {
                    this.openEditModal(event, false);
                }
            } else if (e.state && e.state.year) {
                const yearSelect = document.getElementById('yearSelect');
                const tableName = `${e.state.year} Events`;
                yearSelect.value = tableName;
                this.currentYear = e.state.year;
                this.fetchEvents();
            } else {
                this.closeModal();
            }
        });

        document.getElementById('itemsPerPage').addEventListener('change', (e) => {
            this.itemsPerPage = e.target.value === 'all' ? 'all' : parseInt(e.target.value);
            this.currentPage = 1; // Reset to first page
            this.renderEvents();
        });

        document.getElementById('searchInput').addEventListener('input', (e) => {
            this.searchTerm = e.target.value.toLowerCase();
            this.currentPage = 1;
            this.renderEvents();
        });

        document.getElementById('sortBy').addEventListener('change', (e) => {
            this.sortBy = e.target.value;
            this.currentPage = 1;
            this.renderEvents();
        });

        document.getElementById('statusFilter').addEventListener('change', (e) => {
            this.statusFilter = e.target.value;
            this.currentPage = 1;
            this.renderEvents();
        });

        document.getElementById('dateFrom').addEventListener('change', (e) => {
            this.dateFrom = e.target.value;
            this.currentPage = 1;
            this.renderEvents();
        });

        document.getElementById('dateTo').addEventListener('change', (e) => {
            this.dateTo = e.target.value;
            this.currentPage = 1;
            this.renderEvents();
        });

        // Custom dropdown handlers
        document.getElementById('eventType').addEventListener('change', (e) => {
            this.handleCustomOption('eventType', 'eventTypeCustom', e.target.value);
        });

        document.getElementById('eventStatus').addEventListener('change', (e) => {
            this.handleCustomOption('eventStatus', 'eventStatusCustom', e.target.value);
        });

        document.getElementById('eventDemographic').addEventListener('change', (e) => {
            this.handleCustomOption('eventDemographic', 'eventDemographicCustom', e.target.value);
        });

        document.getElementById('eventAttendees').addEventListener('change', (e) => {
            this.handleCustomOption('eventAttendees', 'eventAttendeesCustom', e.target.value);
        });

        document.getElementById('eventNeighborhood').addEventListener('change', (e) => {
            this.handleCustomOption('eventNeighborhood', 'eventNeighborhoodCustom', e.target.value);
        });

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeModal();
            }
        });

        // Year modal - close when clicking outside
        document.getElementById('yearModal').addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeYearModal();
            }
        });

        // Year modal - submit on Enter key
        document.getElementById('yearInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.submitNewYear();
            }
        });

        // Notification modal - close when clicking outside
        document.getElementById('notificationModal').addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeNotificationModal();
            }
        });
    },

    async populateYearDropdown() {
        const url = `${this.apiConfig.baseUrl}/api/index.php?resource=years`;
        console.log('Fetching years from:', url);
        
        try {
            const response = await fetch(url, { method: 'GET' });

            if (!response.ok) {
                console.error('Failed to fetch years:', response.statusText);
                return;
            }
            
            const data = await response.json();
            console.log('Years fetched:', data);
            
            // API returns { tables: [{ name: "2026 Events" }, ...] }
            const eventTables = (data.tables || [])
                .map(table => table.name)
                .sort((a, b) => {
                    const yearA = parseInt(a.match(/\d{4}/)?.[0] || '0');
                    const yearB = parseInt(b.match(/\d{4}/)?.[0] || '0');
                    return yearB - yearA;
                });
            
            console.log('Event tables found:', eventTables);
            
            // Populate the dropdown
            const yearSelect = document.getElementById('yearSelect');
            yearSelect.innerHTML = '';
            
            // Add event table options
            eventTables.forEach(tableName => {
                const option = document.createElement('option');
                option.value = tableName;
                option.textContent = tableName;
                yearSelect.appendChild(option);
            });
            
            // Add "Add New Year" option at the end
            const addNewOption = document.createElement('option');
            addNewOption.value = 'new';
            addNewOption.textContent = '+ Add New Year';
            yearSelect.appendChild(addNewOption);
            
            // Set current year to the first available table
            if (eventTables.length > 0) {
                yearSelect.value = eventTables[0];
                this.currentYear = eventTables[0].split(' ')[0];
            }
            
        } catch (error) {
            console.error('Error fetching years:', error);
        }
    },

    async createNewYearTable(year) {
        console.log('=== createNewYearTable called ===');
        console.log('Year:', year);
        const tableName = `${year} Events`;
        const url = `${this.apiConfig.baseUrl}/api/index.php?resource=years/new`;
        console.log('API URL:', url);

        const grid = document.getElementById('eventsGrid');
        grid.innerHTML = `<div class="loading">Adding year ${year}...</div>`;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ year: parseInt(year) })
            });
            console.log('Response status:', response.status);

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || response.statusText);
            }
            
            console.log('Year added successfully');
            this.showNotification('Success', `Year ${year} is now available. Events can be added for this year.`);
            return true;
        } catch (error) {
            console.error('Error adding year:', error);
            this.showNotification('Error', `Failed to add year: ${error.message}`);
            return false;
        }
    },

    handleCustomOption(selectId, customInputId, value) {
        const customInput = document.getElementById(customInputId);
        if (value === 'custom') {
            customInput.style.display = 'block';
            customInput.focus();
        } else {
            customInput.style.display = 'none';
            customInput.value = '';
        }
    },

    async fetchEvents() {
        const grid = document.getElementById('eventsGrid');
        grid.innerHTML = '<div class="loading">Loading events...</div>';

        try {
            const url = `${this.apiConfig.baseUrl}/api/index.php?resource=events&year=${this.currentYear}`;
            
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.error) {
                grid.innerHTML = `<div class="error">Error: ${data.error}</div>`;
                return;
            }

            this.events = data.records || [];
            this.extractExistingChoices();
            this.populateDropdowns();
            this.renderEvents();
        } catch (error) {
            console.error('Error fetching events:', error);
            grid.innerHTML = '<div class="error">Failed to load events. Please try again.</div>';
        }
    },

    
    extractExistingChoices() {
        // Clear existing sets
        this.existingChoices.types.clear();
        this.existingChoices.statuses.clear();
        this.existingChoices.demographics.clear();
        this.existingChoices.attendees.clear();
        this.existingChoices.neighborhoods.clear();

        // Extract from current events
        this.events.forEach(record => {
            const fields = record.fields;
            
            if (fields['Type of Event']) {
                this.existingChoices.types.add(fields['Type of Event'].trim());
            }

            if (fields['Status (Tentative, Ready, Completed)']) {
                this.existingChoices.statuses.add(fields['Status (Tentative, Ready, Completed)'].trim());
            }
            
            if (fields['Demographic Served']) {
                this.existingChoices.demographics.add(fields['Demographic Served'].trim());
            }
            
            if (fields['Attendees']) {
                this.existingChoices.attendees.add(fields['Attendees'].trim());
            }
            
            if (fields['Neighborhood']) {
                this.existingChoices.neighborhoods.add(fields['Neighborhood'].trim());
            }
        });
    },

    populateDropdowns() {
        // Populate Type dropdown
        const typeSelect = document.getElementById('eventType');
        this.populateDropdown(typeSelect, this.existingChoices.types, [
            'Community Event',
            'Neighborhood Association Meeting', 
            'Health Fair',
            'Workshop'
        ]);

        // Populate Status dropdown
        const statusSelect = document.getElementById('eventStatus');
        this.populateDropdown(statusSelect, this.existingChoices.statuses, [
            'Tentative',
            'Ready',
            'Completed'
        ]);

        // Populate Demographic dropdown
        const demographicSelect = document.getElementById('eventDemographic');
        this.populateDropdown(demographicSelect, this.existingChoices.demographics, [
            'Seniors',
            'Families',
            'Youth',
            'Adults',
            'Children',
            'Veterans'
        ]);

        // Populate Attendees dropdown
        const attendeeSelect = document.getElementById('eventAttendees');
        this.populateDropdown(attendeeSelect, this.existingChoices.attendees, [
            'Nallely Cheung',
            'Maria Rodriguez',
            'John Smith',
            'Sarah Johnson'
        ]);

        // Populate Neighborhood dropdown
        const neighborhoodSelect = document.getElementById('eventNeighborhood');
        this.populateDropdown(neighborhoodSelect, this.existingChoices.neighborhoods, []);
    },

    populateDropdown(selectElement, existingChoices, defaultChoices) {
        const currentValue = selectElement.value;
        selectElement.innerHTML = '<option value="">Select...</option>';
        
        // Add existing choices first
        const sortedExisting = Array.from(existingChoices).sort();
        sortedExisting.forEach(choice => {
            if (choice && choice.trim()) {
                const option = document.createElement('option');
                option.value = choice;
                option.textContent = choice;
                selectElement.appendChild(option);
            }
        });

        // Add default choices that aren't already in existing
        defaultChoices.forEach(choice => {
            if (!existingChoices.has(choice)) {
                const option = document.createElement('option');
                option.value = choice;
                option.textContent = choice;
                selectElement.appendChild(option);
            }
        });

        // Add custom option
        const customOption = document.createElement('option');
        customOption.value = 'custom';
        customOption.textContent = '+ Add new...';
        selectElement.appendChild(customOption);

        // Restore previous selection if it still exists
        if (currentValue) {
            selectElement.value = currentValue;
        }
    },

    toggleFilters() {
        this.filtersVisible = !this.filtersVisible;
        
        const filterGroup = document.getElementById('filterGroup');
        const toggleText = document.getElementById('filterToggleText');
        
        if (this.filtersVisible) {
            filterGroup.style.display = 'flex';
            toggleText.textContent = 'Hide Filters';
        } else {
            filterGroup.style.display = 'none';
            toggleText.textContent = 'Show Filters';
        }
    },

    toggleView() {
        this.viewMode = this.viewMode === 'card' ? 'table' : 'card';
        
        const gridView = document.getElementById('eventsGrid');
        const tableView = document.getElementById('eventsTable');
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
        this.renderEvents();
    },

    renderEvents() {
        if (this.viewMode === 'table') {
            this.renderTableView();
        } else {
            this.renderCardView();
        }
    },

    updateEventsCount(count) {
        const el = document.getElementById('eventsCount');
        if (!el) return;
        el.textContent = count;
    },

    getFilteredAndSortedEvents() {
        let filteredEvents = this.events;
        
        // Search filter
        if (this.searchTerm) {
            const term = this.searchTerm.toLowerCase();
            filteredEvents = filteredEvents.filter(record => {
                const f = record.fields;
                return (f['Event Name'] || '').toLowerCase().includes(term) ||
                       (f['Neighborhood'] || '').toLowerCase().includes(term) ||
                       (f['Address'] || '').toLowerCase().includes(term) ||
                       (f['Event Location'] || '').toLowerCase().includes(term) ||
                       (f['Type of Event'] || '').toLowerCase().includes(term) ||
                       (f['Status (Tentative, Ready, Completed)'] || '').toLowerCase().includes(term);
            });
        }
        
        // Status filter
        if (this.statusFilter) {
            filteredEvents = filteredEvents.filter(record => {
                const status = record.fields['Status (Tentative, Ready, Completed)'] || '';
                return status === this.statusFilter;
            });
        }
        
        // Date period filter
        if (this.dateFrom) {
            filteredEvents = filteredEvents.filter(record => {
                const eventDate = record.fields['Event Date'] || '';
                return eventDate >= this.dateFrom;
            });
        }
        
        if (this.dateTo) {
            filteredEvents = filteredEvents.filter(record => {
                const eventDate = record.fields['Event Date'] || '';
                return eventDate <= this.dateTo;
            });
        }

        // Sort
        if (this.sortBy === 'date-desc') {
            filteredEvents.sort((a, b) => {
                const dateA = a.fields['Event Date'] || '';
                const dateB = b.fields['Event Date'] || '';
                return dateB.localeCompare(dateA);
            });
        } else if (this.sortBy === 'date-asc') {
            filteredEvents.sort((a, b) => {
                const dateA = a.fields['Event Date'] || '';
                const dateB = b.fields['Event Date'] || '';
                return dateA.localeCompare(dateB);
            });
        } else if (this.sortBy === 'name-asc') {
            filteredEvents.sort((a, b) => {
                const nameA = (a.fields['Event Name'] || '').toLowerCase();
                const nameB = (b.fields['Event Name'] || '').toLowerCase();
                return nameA.localeCompare(nameB);
            });
        } else if (this.sortBy === 'name-desc') {
            filteredEvents.sort((a, b) => {
                const nameA = (a.fields['Event Name'] || '').toLowerCase();
                const nameB = (b.fields['Event Name'] || '').toLowerCase();
                return nameB.localeCompare(nameA);
            });
        }

        return filteredEvents;
    },

    renderCardView() {
        const grid = document.getElementById('eventsGrid');
        grid.innerHTML = '';

        const filteredEvents = this.getFilteredAndSortedEvents();

        if (filteredEvents.length === 0) {
            this.updateEventsCount(0);
            grid.innerHTML = '<div class="no-results">No events found.</div>';
            this.renderPagination(0);
            return;
        }

        this.updateEventsCount(filteredEvents.length);

        let displayEvents = filteredEvents;
        
        if (this.itemsPerPage !== 'all') {
            const startIndex = (this.currentPage - 1) * this.itemsPerPage;
            const endIndex = startIndex + this.itemsPerPage;
            displayEvents = filteredEvents.slice(startIndex, endIndex);
        }

        displayEvents.forEach(record => {
            const fields = record.fields;
            const card = document.createElement('div');
            card.className = 'event-card';
            card.onclick = () => {
                this.updateUrl(record.id);
                this.openEditModal(record);
            };

            const statusClass = this.getStatusClass(fields['Status (Tentative, Ready, Completed)']);
            const time = fields[' Time'] || '';
            const neighborhood = fields['Neighborhood'] || '';
            const address = fields['Address'] || '';
            const eventLocation = fields['Event Location'] || '';
            const location = neighborhood ? (address ? `${neighborhood} - ${address}` : neighborhood) : (address || eventLocation || 'No location');
            const type = fields['Type of Event'] || 'Unspecified Type';
            
            let day = 'DD';
            let month = 'MMM';
            if (fields['Event Date']) {
                const dateObj = new Date(fields['Event Date'] + 'T00:00:00');
                day = dateObj.getDate();
                month = dateObj.toLocaleString('default', { month: 'short' });
            }
            
            card.innerHTML = `
                <div class="event-header">
                    <div class="event-date-badge">
                        <span class="date-day">${day}</span>
                        <span class="date-month">${month}</span>
                    </div>
                    ${time ? `<div class="event-time-badge"><i data-lucide="clock" style="width: 12px; height: 12px; margin-right: 4px; vertical-align: text-bottom;"></i>${time}</div>` : ''}
                </div>
                
                <h3 class="event-title">${fields['Event Name'] || 'Untitled Event'}</h3>
                
                <div class="event-meta">
                    <div class="meta-item">
                        <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i>
                        <span>${location}</span>
                    </div>
                    <div class="meta-item">
                        <i data-lucide="tag" style="width: 16px; height: 16px;"></i>
                        <span>${type}</span>
                    </div>
                </div>

                <span class="event-status ${statusClass}">
                    ${fields['Status (Tentative, Ready, Completed)'] || 'Tentative'}
                </span>
            `;
            
            grid.appendChild(card);
        });
        
        lucide.createIcons();
        this.renderPagination(filteredEvents.length);
    },

    renderTableView() {
        const tbody = document.getElementById('eventsTableBody');
        tbody.innerHTML = '';

        const filteredEvents = this.getFilteredAndSortedEvents();

        if (filteredEvents.length === 0) {
            this.updateEventsCount(0);
            tbody.innerHTML = '<tr><td colspan="6" class="no-results">No events found.</td></tr>';
            this.renderPagination(0);
            return;
        }

        this.updateEventsCount(filteredEvents.length);

        let displayEvents = filteredEvents;
        
        if (this.itemsPerPage !== 'all') {
            const startIndex = (this.currentPage - 1) * this.itemsPerPage;
            const endIndex = startIndex + this.itemsPerPage;
            displayEvents = filteredEvents.slice(startIndex, endIndex);
        }

        displayEvents.forEach(record => {
            const fields = record.fields;
            const row = document.createElement('tr');
            
            const eventName = fields['Event Name'] || 'Untitled Event';
            const eventDate = fields['Event Date'] || '-';
            const eventTime = fields[' Time'] || '-';
            const type = fields['Type of Event'] || 'Unspecified';
            const neighborhood = fields['Neighborhood'] || '';
            const address = fields['Address'] || '';
            const eventLocation = fields['Event Location'] || '';
            const location = neighborhood ? (address ? `${neighborhood} - ${address}` : neighborhood) : (address || eventLocation || '-');
            const status = fields['Status (Tentative, Ready, Completed)'] || 'Tentative';
            const statusClass = status.toLowerCase().replace(/\s+/g, '-');
            
            // Format date for display
            let displayDate = eventDate;
            if (eventDate !== '-') {
                try {
                    const dateObj = new Date(eventDate + 'T00:00:00');
                    displayDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                } catch(e) { /* keep raw */ }
            }
            
            // Extract from time (first part of time range)
            let displayTime = eventTime;
            if (eventTime && eventTime !== '-') {
                const timeMatch = eventTime.match(/^(\d{1,2}:\d{2}(?:\s*(?:AM|PM))?)/i);
                if (timeMatch) {
                    displayTime = timeMatch[1];
                }
            }
            
            row.innerHTML = `
                <td>${displayDate}</td>
                <td>${displayTime}</td>
                <td><span class="event-name">${eventName}</span></td>
                <td>${type}</td>
                <td>${location}</td>
                <td><span class="status-badge ${statusClass}">${status}</span></td>
            `;
            
            row.onclick = () => {
                this.updateUrl(record.id);
                this.openEditModal(record);
            };
            tbody.appendChild(row);
        });
        
        lucide.createIcons();
        this.renderPagination(filteredEvents.length);
    },

    renderPagination(totalItems) {
        const container = document.getElementById('paginationControls');
        container.innerHTML = '';

        if (this.itemsPerPage === 'all' || totalItems === 0) return;

        const totalPages = Math.ceil(totalItems / this.itemsPerPage);
        if (totalPages <= 1) return;

        // Prev Button
        const prevBtn = document.createElement('button');
        prevBtn.className = 'page-btn';
        prevBtn.innerHTML = '<i data-lucide="chevron-left" style="width: 16px; height: 16px;"></i>';
        prevBtn.disabled = this.currentPage === 1;
        prevBtn.onclick = () => {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.renderEvents();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        };
        container.appendChild(prevBtn);

        // Page Numbers
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
                    this.renderEvents();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                };
                container.appendChild(btn);
            }
        });

        // Next Button
        const nextBtn = document.createElement('button');
        nextBtn.className = 'page-btn';
        nextBtn.innerHTML = '<i data-lucide="chevron-right" style="width: 16px; height: 16px;"></i>';
        nextBtn.disabled = this.currentPage === totalPages;
        nextBtn.onclick = () => {
            if (this.currentPage < totalPages) {
                this.currentPage++;
                this.renderEvents();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        };
        container.appendChild(nextBtn);
        
        lucide.createIcons();
    },

    getStatusClass(status) {
        if (!status) return 'status-tentative';
        switch (status.toLowerCase()) {
            case 'ready': return 'status-ready';
            case 'completed': return 'status-completed';
            default: return 'status-tentative';
        }
    },

    openEditModal(record, updateUrl = true) {
        if (updateUrl) {
            this.updateUrl(record.id);
        }
        const fields = record.fields;
        const modal = document.getElementById('editModal');
        modal.querySelector('.modal-title').textContent = 'Edit Event';
        
        document.getElementById('eventId').value = record.id;
        document.getElementById('eventName').value = fields['Event Name'] || '';
        document.getElementById('eventDate').value = fields['Event Date'] || '';
        
        // Handle time fields - parse time range if present
        const timeValue = fields[' Time'] || '';
        const parsedTime = this.parseTimeRange(timeValue);
        document.getElementById('eventTimeFrom').value = parsedTime.from;
        document.getElementById('eventTimeTo').value = parsedTime.to;
        
        // Handle Type dropdown with custom option
        this.setDropdownWithCustom('eventType', 'eventTypeCustom', fields['Type of Event'] || '');
        
        const neighborhood = fields['Neighborhood'] || '';
        const address = fields['Address'] || '';
        const eventLocation = fields['Event Location'] || '';
        
        // Handle Neighborhood dropdown with custom option
        const neighborhoodValue = neighborhood || (eventLocation && !address ? eventLocation : '');
        this.setDropdownWithCustom('eventNeighborhood', 'eventNeighborhoodCustom', neighborhoodValue);
        
        document.getElementById('eventAddress').value = address;
        this.setDropdownWithCustom('eventStatus', 'eventStatusCustom', fields['Status (Tentative, Ready, Completed)'] || 'Tentative');
        
        // Handle Demographic dropdown with custom option
        this.setDropdownWithCustom('eventDemographic', 'eventDemographicCustom', fields['Demographic Served'] || '');
        
        // Handle Attendees dropdown with custom option
        this.setDropdownWithCustom('eventAttendees', 'eventAttendeesCustom', fields['Attendees'] || '');
        
        document.getElementById('eventInteractions').value = fields['# of Interactions'] || '';
        document.getElementById('eventEquipment').value = fields['Equipment Needed'] || '';
        document.getElementById('eventNotes').value = fields['Notes'] || '';

        document.getElementById('eventFocusChallengesHighlights').value = fields['Focus Challenges/Highlights'] || '';
        document.getElementById('eventNewContactsMade').value = fields['New Contacts Made'] || '';
        document.getElementById('eventExistingContactsEngaged').value = fields['Existing contacts engaged'] || '';
        document.getElementById('eventPotentialFutureCEC').value = fields['Potential Future CEC engagement opportunities'] || '';
        document.getElementById('eventPossibleAlignmentResearcher').value = fields['Possible Alignment with researcher'] || '';

        document.getElementById('outlookButtonsContainer').style.display = 'flex';
        modal.classList.add('active');
    },

    openAddModal() {
        const modal = document.getElementById('editModal');
        modal.querySelector('.modal-title').textContent = 'New Event';
        
        // Clear hidden ID to indicate new record
        document.getElementById('eventId').value = '';
        
        // Clear all inputs
        document.getElementById('editForm').reset();
        
        // Reset custom dropdowns
        this.setDropdownWithCustom('eventType', 'eventTypeCustom', '');
        this.setDropdownWithCustom('eventStatus', 'eventStatusCustom', 'Tentative');
        this.setDropdownWithCustom('eventDemographic', 'eventDemographicCustom', '');
        this.setDropdownWithCustom('eventAttendees', 'eventAttendeesCustom', '');
        
        // Set default status
        this.setDropdownWithCustom('eventStatus', 'eventStatusCustom', 'Tentative');

        document.getElementById('outlookButtonsContainer').style.display = 'none';
        modal.classList.add('active');
    },

    parseTimeRange(timeString) {
        // Parse time string and return { from: '', to: '' }
        if (!timeString) return { from: '', to: '' };
        
        const trimmed = timeString.trim();
        
        // Check for time range patterns like "12:00 PM - 1:00 PM" or "12:00 - 1:00 PM"
        const rangeMatch = trimmed.match(/(\d{1,2}:\d{2}(?:\s*(?:AM|PM))?)\s*[-–—]\s*(\d{1,2}:\d{2}(?:\s*(?:AM|PM))?)/i);
        if (rangeMatch) {
            return {
                from: rangeMatch[1].trim(),
                to: rangeMatch[2].trim()
            };
        }
        
        // Single time value - put it in 'from' field
        if (/^\d{1,2}:\d{2}(?:\s*(?:AM|PM))?$/i.test(trimmed)) {
            return { from: trimmed, to: '' };
        }
        
        return { from: '', to: '' };
    },
    
    formatTimeForDisplay(fromTime, toTime) {
        // Combine from and to times for display
        if (!fromTime && !toTime) return '';
        if (fromTime && toTime) return `${fromTime} - ${toTime}`;
        return fromTime || toTime;
    },

    setDropdownWithCustom(selectId, customInputId, value) {
        const select = document.getElementById(selectId);
        const customInput = document.getElementById(customInputId);
        const trimmedValue = value ? value.trim() : '';
        
        // Check if value exists in dropdown options
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
            // Set to custom and populate custom input
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
        const hash = window.location.hash;
        if (hash.startsWith('#event=')) {
            window.history.pushState({}, '', window.location.pathname + window.location.search);
        }
    },

    openYearModal() {
        const yearSelect = document.getElementById('yearSelect');
        yearSelect.value = `${this.currentYear} Events`; // Revert selection
        document.getElementById('yearInput').value = '';
        document.getElementById('yearModal').style.display = 'flex';
        setTimeout(() => {
            document.getElementById('yearModal').classList.add('active');
            document.getElementById('yearInput').focus();
        }, 10);
    },

    closeYearModal() {
        const modal = document.getElementById('yearModal');
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    },

    async submitNewYear() {
        const year = document.getElementById('yearInput').value.trim();
        console.log('User entered year:', year);
        
        if (!year) {
            console.log('No year entered');
            this.showNotification('Validation Error', 'Please enter a year.');
            return;
        }
        
        if (!/^\d{4}$/.test(year)) {
            console.log('Year validation failed - not a 4-digit number');
            this.showNotification('Validation Error', 'Please enter a valid 4-digit year (e.g., 2027)');
            return;
        }
        
        console.log('Year validation passed, creating table...');
        this.closeYearModal();
        
        // Register new year with MySQL backend
        const success = await this.createNewYearTable(year);
        console.log('Table creation result:', success);
        
        if (success) {
            // Create option and select it
            const yearSelect = document.getElementById('yearSelect');
            const option = document.createElement('option');
            option.value = `${year} Events`;
            option.textContent = `${year} Events`;
            // Insert before the "Add New Year" option
            yearSelect.insertBefore(option, yearSelect.lastElementChild);
            yearSelect.value = `${year} Events`;
            this.currentYear = year;
            this.fetchEvents();
        }
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

    async saveEvent() {
        const id = document.getElementById('eventId').value;
        
        // Validate # of Interactions field (optional, but must be valid number if provided)
        const interactionsInput = document.getElementById('eventInteractions');
        
        // Check if the input field itself is valid (catches invalid text in number inputs)
        if (!interactionsInput.checkValidity()) {
            this.showNotification('Error', 'Number of interactions must be a valid non-negative number.');
            interactionsInput.focus();
            return;
        }
        
        const interactionsValue = interactionsInput.value.trim();
        let interactionsNum = '';
        
        if (interactionsValue !== '') {
            const parsedNum = parseInt(interactionsValue);
            if (isNaN(parsedNum) || parsedNum < 0) {
                this.showNotification('Error', 'Number of interactions must be a valid non-negative number.');
                interactionsInput.focus();
                return;
            }
            interactionsNum = parsedNum.toString();
        }
        
        // Get values from dropdowns and custom inputs
        const eventType = this.getValueFromCustomDropdown('eventType', 'eventTypeCustom');
        const eventStatus = this.getValueFromCustomDropdown('eventStatus', 'eventStatusCustom');
        const eventDemographic = this.getValueFromCustomDropdown('eventDemographic', 'eventDemographicCustom');
        const eventAttendees = this.getValueFromCustomDropdown('eventAttendees', 'eventAttendeesCustom');
        const eventNeighborhood = this.getValueFromCustomDropdown('eventNeighborhood', 'eventNeighborhoodCustom');
        
        // Combine from and to times
        const timeFrom = document.getElementById('eventTimeFrom').value.trim();
        const timeTo = document.getElementById('eventTimeTo').value.trim();
        const timeValue = this.formatTimeForDisplay(timeFrom, timeTo);
        
        const fields = {
            'Event Name': document.getElementById('eventName').value,
            'Event Date': document.getElementById('eventDate').value,
            ' Time': timeValue,
            'Type of Event': eventType,
            'Neighborhood': eventNeighborhood,
            'Address': document.getElementById('eventAddress').value,
            'Status (Tentative, Ready, Completed)': eventStatus,
            'Demographic Served': eventDemographic,
            'Attendees': eventAttendees,
            '# of Interactions': interactionsNum.toString(),
            'Equipment Needed': document.getElementById('eventEquipment').value,
            'Notes': document.getElementById('eventNotes').value,
            'Focus Challenges/Highlights': document.getElementById('eventFocusChallengesHighlights').value,
            'New Contacts Made': document.getElementById('eventNewContactsMade').value,
            'Existing contacts engaged': document.getElementById('eventExistingContactsEngaged').value,
            'Potential Future CEC engagement opportunities': document.getElementById('eventPotentialFutureCEC').value,
            'Possible Alignment with researcher': document.getElementById('eventPossibleAlignmentResearcher').value
        };

        const saveBtn = document.querySelector('.modal-footer .btn-primary');
        const originalText = saveBtn.innerText;
        saveBtn.innerText = 'Saving...';
        saveBtn.disabled = true;

        try {
            let url = `${this.apiConfig.baseUrl}/api/index.php?resource=events&year=${this.currentYear}`;
            let method = 'POST';

            if (id) {
                url += `&id=${id}`;
                method = 'PATCH';
            }
            
            console.log('Saving event to:', url, 'Method:', method);
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ fields: fields })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.error) {
                this.showNotification('Error', `Error saving event: ${result.error}`);
            } else {
                this.closeModal();
                await this.fetchEvents();
                this.showNotification('Success', 'Event saved successfully!');
            }
        } catch (error) {
            console.error('Error saving event:', error);
            this.showNotification('Error', 'Failed to save changes. Please try again.');
        } finally {
            saveBtn.innerText = originalText;
            saveBtn.disabled = false;
        }
    },

    parseTimeToISO(timeString) {
        // Convert various time formats to HH:MM:SS format
        if (!timeString) return '09:00:00';
        
        const trimmed = timeString.trim();
        
        // Check for 12-hour format with AM/PM
        const ampmMatch = trimmed.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
        if (ampmMatch) {
            let hours = parseInt(ampmMatch[1]);
            const minutes = ampmMatch[2];
            const period = ampmMatch[3].toUpperCase();
            
            if (period === 'PM' && hours !== 12) {
                hours += 12;
            } else if (period === 'AM' && hours === 12) {
                hours = 0;
            }
            
            return `${hours.toString().padStart(2, '0')}:${minutes}:00`;
        }
        
        // Check for 24-hour format
        const timeMatch = trimmed.match(/(\d{1,2}):(\d{2})/);
        if (timeMatch) {
            const hours = timeMatch[1].padStart(2, '0');
            const minutes = timeMatch[2].padStart(2, '0');
            return `${hours}:${minutes}:00`;
        }
        
        return '09:00:00';
    },
    
    refreshData() {
        this.fetchEvents();
    },

    addToOutlookWeb() {
        const eventName = document.getElementById('eventName').value || 'Untitled Event';
        const eventDate = document.getElementById('eventDate').value;
        const eventTimeFrom = document.getElementById('eventTimeFrom').value.trim();
        const eventTimeTo = document.getElementById('eventTimeTo').value.trim();
        const eventType = this.getValueFromCustomDropdown('eventType', 'eventTypeCustom');
        const eventNeighborhood = this.getValueFromCustomDropdown('eventNeighborhood', 'eventNeighborhoodCustom');
        const eventAddress = document.getElementById('eventAddress').value;
        const eventNotes = document.getElementById('eventNotes').value;
        const eventEquipment = document.getElementById('eventEquipment').value;

        if (!eventDate) {
            this.showNotification('Missing Information', 'Please add an event date before adding to calendar.');
            return;
        }

        // Build location string
        let location = '';
        if (eventNeighborhood && eventAddress) {
            location = `${eventNeighborhood} - ${eventAddress}`;
        } else if (eventNeighborhood) {
            location = eventNeighborhood;
        } else if (eventAddress) {
            location = eventAddress;
        }

        // Parse date and time for Outlook Web format
        console.log('Event Date:', eventDate);
        console.log('Event Time From:', eventTimeFrom);
        console.log('Event Time To:', eventTimeTo);
        
        let startDateTime, endDateTime;
        if (eventTimeFrom) {
            const startTime = this.parseTimeToISO(eventTimeFrom);
            startDateTime = new Date(`${eventDate}T${startTime}`);
            
            if (eventTimeTo) {
                const endTime = this.parseTimeToISO(eventTimeTo);
                endDateTime = new Date(`${eventDate}T${endTime}`);
            } else {
                endDateTime = new Date(startDateTime.getTime() + 60 * 60 * 1000); // 1 hour default
            }
            
            console.log('Parsed Start DateTime:', startDateTime);
            console.log('Parsed End DateTime:', endDateTime);
        } else {
            startDateTime = new Date(`${eventDate}T09:00:00`);
            endDateTime = new Date(`${eventDate}T10:00:00`);
        }

        // Format dates for Outlook Web (ISO 8601 format with timezone offset)
        const formatOutlookDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            
            // Get timezone offset in format +/-HHMM
            const offset = -date.getTimezoneOffset();
            const offsetHours = String(Math.floor(Math.abs(offset) / 60)).padStart(2, '0');
            const offsetMinutes = String(Math.abs(offset) % 60).padStart(2, '0');
            const offsetSign = offset >= 0 ? '+' : '-';
            const timezoneOffset = `${offsetSign}${offsetHours}:${offsetMinutes}`;
            
            return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}${timezoneOffset}`;
        };
        
        const formattedStart = formatOutlookDate(startDateTime);
        const formattedEnd = formatOutlookDate(endDateTime);
        console.log('Formatted Start:', formattedStart);
        console.log('Formatted End:', formattedEnd);

        // Build description/body
        let body = '';
        if (eventType) body += `Type: ${eventType}\n`;
        if (eventEquipment) body += `Equipment: ${eventEquipment}\n`;
        if (eventNotes) body += `\nNotes: ${eventNotes}`;

        // Build Outlook Web URL
        const params = new URLSearchParams({
            path: '/calendar/action/compose',
            rru: 'addevent',
            subject: eventName,
            startdt: formattedStart,
            enddt: formattedEnd,
            body: body,
            location: location
        });

        const outlookUrl = `https://outlook.office.com/calendar/0/deeplink/compose?${params.toString()}`;
        
        console.log('Outlook URL:', outlookUrl);
        
        // Open in new tab
        window.open(outlookUrl, '_blank');
    },

    addToOutlookCalendar() {
        const eventName = document.getElementById('eventName').value || 'Untitled Event';
        const eventDate = document.getElementById('eventDate').value;
        const eventTimeFrom = document.getElementById('eventTimeFrom').value.trim();
        const eventTimeTo = document.getElementById('eventTimeTo').value.trim();
        const eventType = this.getValueFromCustomDropdown('eventType', 'eventTypeCustom');
        const eventNeighborhood = this.getValueFromCustomDropdown('eventNeighborhood', 'eventNeighborhoodCustom');
        const eventAddress = document.getElementById('eventAddress').value;
        const eventNotes = document.getElementById('eventNotes').value;
        const eventEquipment = document.getElementById('eventEquipment').value;

        if (!eventDate) {
            this.showNotification('Missing Information', 'Please add an event date before adding to calendar.');
            return;
        }

        // Build location string
        let location = '';
        if (eventNeighborhood && eventAddress) {
            location = `${eventNeighborhood} - ${eventAddress}`;
        } else if (eventNeighborhood) {
            location = eventNeighborhood;
        } else if (eventAddress) {
            location = eventAddress;
        }

        // Parse date and time
        let startDateTime, endDateTime;
        if (eventTimeFrom) {
            const startTime = this.parseTimeToISO(eventTimeFrom);
            startDateTime = new Date(`${eventDate}T${startTime}`);
            
            if (eventTimeTo) {
                const endTime = this.parseTimeToISO(eventTimeTo);
                endDateTime = new Date(`${eventDate}T${endTime}`);
            } else {
                // Default 1 hour duration
                endDateTime = new Date(startDateTime.getTime() + 60 * 60 * 1000);
            }
        } else {
            // All-day event
            startDateTime = new Date(`${eventDate}T00:00:00`);
            endDateTime = new Date(`${eventDate}T23:59:59`);
        }

        // Format dates for iCalendar (YYYYMMDDTHHMMSS)
        const formatICalDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            return `${year}${month}${day}T${hours}${minutes}${seconds}`;
        };

        // Build description
        let description = '';
        if (eventType) description += `Type: ${eventType}\\n`;
        if (eventEquipment) description += `Equipment: ${eventEquipment}\\n`;
        if (eventNotes) description += `\\nNotes: ${eventNotes}`;

        // Generate unique ID
        const uid = `${Date.now()}@cec-events`;

        // Create iCalendar content
        const icsContent = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CEC Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            `UID:${uid}`,
            `DTSTAMP:${formatICalDate(new Date())}`,
            `DTSTART:${formatICalDate(startDateTime)}`,
            `DTEND:${formatICalDate(endDateTime)}`,
            `SUMMARY:${eventName}`,
            location ? `LOCATION:${location}` : '',
            description ? `DESCRIPTION:${description}` : '',
            'STATUS:CONFIRMED',
            'SEQUENCE:0',
            'END:VEVENT',
            'END:VCALENDAR'
        ].filter(line => line).join('\r\n');

        // Create blob and download
        const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${eventName.replace(/[^a-z0-9]/gi, '_')}.ics`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);

        this.showNotification('Success', 'Calendar file downloaded! Open it to add to Outlook or any calendar app.');
    }
};

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    app.init();
});
