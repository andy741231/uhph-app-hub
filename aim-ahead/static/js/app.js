const app = {
    data: [],
    filteredData: [],
    currentPage: 1,
    itemsPerPage: 25,
    sortField: 'year',
    sortDirection: 'desc',

    // Airtable API Configuration
    apiConfig: {
        apiKey: 'REDACTED_AIRTABLE_TOKEN_A',
        baseId: 'appgB0lC8rHTdVUN9',
        tableId: 'stats',
        baseUrl: 'https://api.airtable.com/v0'
    },

    async init() {
        this.bindEvents();
        await this.fetchData();
    },

    bindEvents() {
        document.getElementById('searchInput').addEventListener('input', (e) => this.handleSearch(e.target.value));
        document.getElementById('yearSelect').addEventListener('change', (e) => this.filterData());
        document.getElementById('stateSelect').addEventListener('change', (e) => this.filterData());
        document.getElementById('itemsPerPage').addEventListener('change', (e) => {
            this.itemsPerPage = e.target.value === 'all' ? this.data.length : parseInt(e.target.value);
            this.currentPage = 1;
            this.renderTable();
        });
        document.getElementById('sortBy').addEventListener('change', (e) => {
            const [field, direction] = e.target.value.split('-');
            this.sortField = field;
            this.sortDirection = direction;
            this.sortData();
            this.renderTable();
        });
    },

    async fetchData() {
        try {
            const url = `${this.apiConfig.baseUrl}/${this.apiConfig.baseId}/${this.apiConfig.tableId}`;
            let allRecords = [];
            let offset = null;

            do {
                const fetchUrl = offset ? `${url}?offset=${offset}` : url;
                const response = await fetch(fetchUrl, {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${this.apiConfig.apiKey}`,
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`Airtable API returned ${response.status}: ${response.statusText}`);
                }

                const result = await response.json();
                if (result.records) {
                    allRecords = allRecords.concat(result.records);
                }
                offset = result.offset;
            } while (offset);

            this.data = allRecords.map(record => {
                let yearStr = record.fields.year || 'Unknown';
                const match = yearStr.match(/y(\d+)/i);
                if (match) {
                    yearStr = `Year ${match[1]}`;
                }
                
                return {
                    id: record.id,
                    ...record.fields,
                    year: yearStr
                };
            });
            
            this.filteredData = [...this.data];
            this.populateFilters();
            this.sortData();
            this.renderTable();
        } catch (error) {
            console.error('Error fetching data:', error);
            this.showNotification('Failed to connect to server.');
        }
    },

    populateFilters() {
        const years = [...new Set(this.data.map(d => d.year).filter(Boolean))].sort().reverse();
        const yearSelect = document.getElementById('yearSelect');
        yearSelect.innerHTML = '<option value="">All Years</option>' + 
            years.map(y => `<option value="${y}">${y}</option>`).join('');

        const states = [...new Set(this.data.map(d => d.state).filter(Boolean))].sort();
        const stateSelect = document.getElementById('stateSelect');
        stateSelect.innerHTML = '<option value="">All States</option>' + 
            states.map(s => `<option value="${s}">${s}</option>`).join('');
    },

    handleSearch(query) {
        query = query.toLowerCase();
        this.filteredData = this.data.filter(item => {
            return (
                (item.state || '').toLowerCase().includes(query) ||
                (item.year || '').toString().toLowerCase().includes(query)
            );
        });
        this.filterData(false); // apply other filters
    },

    filterData(resetSearch = true) {
        if (resetSearch) {
            document.getElementById('searchInput').value = '';
            this.filteredData = [...this.data];
        }

        const yearFilter = document.getElementById('yearSelect').value;
        const stateFilter = document.getElementById('stateSelect').value;

        this.filteredData = this.filteredData.filter(item => {
            let match = true;
            if (yearFilter && item.year !== yearFilter) match = false;
            if (stateFilter && item.state !== stateFilter) match = false;
            return match;
        });

        this.currentPage = 1;
        this.sortData();
        this.renderTable();
    },

    sortData() {
        this.filteredData.sort((a, b) => {
            let valA = a[this.sortField] || '';
            let valB = b[this.sortField] || '';

            if (this.sortField === 'year' || this.sortField === 'stakeholders') {
                valA = Number(valA) || 0;
                valB = Number(valB) || 0;
            }

            if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
            if (valA > valB) return this.sortDirection === 'asc' ? 1 : -1;
            return 0;
        });
    },

    renderTable() {
        const tbody = document.getElementById('eventsTableBody');
        const countSpan = document.getElementById('eventsCount');
        
        countSpan.textContent = this.filteredData.length;

        if (this.filteredData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="loading">No records found</td></tr>';
            this.renderPagination();
            return;
        }

        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;
        const pageData = this.filteredData.slice(startIndex, endIndex);

        tbody.innerHTML = pageData.map(item => `
            <tr>
                <td>${item.year || '-'}</td>
                <td>${item.state || '-'}</td>
                <td>${item['consortium average applications'] || '0'}</td>
                <td>${item['south central hub applications'] || '0'}</td>
                <td>${item['consortium average awardees'] || '0'}</td>
                <td>${item['south central hub awardees'] || '0'}</td>
                <td>${item.stakeholders || '0'}</td>
            </tr>
        `).join('');

        this.renderPagination();
    },

    renderPagination() {
        const totalPages = Math.ceil(this.filteredData.length / this.itemsPerPage);
        const container = document.getElementById('paginationControls');
        
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = `
            <button class="btn btn-light" ${this.currentPage === 1 ? 'disabled' : ''} onclick="app.changePage(${this.currentPage - 1})">
                Previous
            </button>
            <span style="margin: 0 10px;">Page ${this.currentPage} of ${totalPages}</span>
            <button class="btn btn-light" ${this.currentPage === totalPages ? 'disabled' : ''} onclick="app.changePage(${this.currentPage + 1})">
                Next
            </button>
        `;
        
        container.innerHTML = html;
    },

    changePage(page) {
        this.currentPage = page;
        this.renderTable();
    },

    toggleFilters() {
        const filters = document.getElementById('filterGroup');
        const text = document.getElementById('filterToggleText');
        if (filters.style.display === 'none') {
            filters.style.display = 'flex';
            text.textContent = 'Hide Filters';
        } else {
            filters.style.display = 'none';
            text.textContent = 'Show Filters';
        }
    },

    showNotification(message) {
        document.getElementById('notificationMessage').textContent = message;
        document.getElementById('notificationModal').style.display = 'flex';
    },

    closeNotificationModal() {
        document.getElementById('notificationModal').style.display = 'none';
    }
};

document.addEventListener('DOMContentLoaded', () => {
    app.init();
});
