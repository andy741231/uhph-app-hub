const dashboard = {
    data: [],
    filteredData: [],
    charts: {},
    usTopology: null,

    // Airtable API Configuration
    apiConfig: {
        apiKey: 'REDACTED_AIRTABLE_TOKEN_A',
        baseId: 'appgB0lC8rHTdVUN9',
        tableId: 'stats',
        baseUrl: 'https://api.airtable.com/v0'
    },

    async init() {
        document.getElementById('globalYearFilter').addEventListener('change', () => this.filterData());
        document.getElementById('stakeholdersStateFilter').addEventListener('change', () => this.renderStakeholdersChart());

        await this.fetchTopology();
        await this.fetchData();
    },

    async fetchTopology() {
        try {
            const response = await fetch('https://unpkg.com/us-atlas/states-10m.json');
            this.usTopology = await response.json();
        } catch (error) {
            console.error('Failed to load US topology data:', error);
        }
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

            this.data = allRecords.map(r => {
                let yearStr = r.fields.year || 'Unknown';
                const match = yearStr.match(/y(\d+)/i);
                if (match) {
                    yearStr = `Year ${match[1]}`;
                }

                return {
                    id: r.id,
                    year: yearStr,
                    state: r.fields.state || 'Unknown',
                consortiumApps: Number(r.fields['consortium average applications']) || 0,
                scHubApps: Number(r.fields['south central hub applications']) || 0,
                consortiumAwardees: Number(r.fields['consortium average awardees']) || 0,
                scHubAwardees: Number(r.fields['south central hub awardees']) || 0,
                stakeholders: Number(r.fields.stakeholders) || 0
                };
            });

            this.filteredData = [...this.data];
            this.populateFilters();
            this.updateDashboard();
        } catch (error) {
            console.error('Error in fetchData:', error);
        }
    },

    populateFilters() {
        const states = [...new Set(this.data.map(d => d.state).filter(s => s !== 'Unknown'))].sort();
        const mapStateSelect = document.getElementById('stakeholdersStateFilter');
        mapStateSelect.innerHTML = '<option value="">All States</option>' + 
            states.map(s => `<option value="${s}">${s}</option>`).join('');

        const years = [...new Set(this.data.map(d => d.year).filter(y => y !== 'Unknown'))].sort();
        const yearOptions = '<option value="">All Years</option>' + 
            years.map(y => `<option value="${y}">${y}</option>`).join('');

        document.getElementById('globalYearFilter').innerHTML = yearOptions;
    },

    filterData() {
        const yearFilter = document.getElementById('globalYearFilter').value;
        if (yearFilter) {
            this.filteredData = this.data.filter(d => d.year === yearFilter);
        } else {
            this.filteredData = [...this.data];
        }
        this.updateDashboard();
    },

    updateDashboard() {
        this.updateSummaryStats();
        this.renderCharts();
    },

    updateSummaryStats() {
        const totalApps = this.filteredData.reduce((sum, d) => sum + d.scHubApps, 0);
        const totalAwardees = this.filteredData.reduce((sum, d) => sum + d.scHubAwardees, 0);
        const totalStakeholders = this.filteredData.reduce((sum, d) => sum + d.stakeholders, 0);

        document.getElementById('totalApplications').textContent = totalApps.toLocaleString();
        document.getElementById('totalAwardees').textContent = totalAwardees.toLocaleString();
        document.getElementById('totalStakeholders').textContent = totalStakeholders.toLocaleString();
    },

    aggregateByYear(dataKey1, dataKey2 = null) {
        const aggregated = {};
        this.filteredData.forEach(d => {
            if (!aggregated[d.year]) {
                aggregated[d.year] = { val1: 0, val2: 0 };
            }
            aggregated[d.year].val1 += d[dataKey1];
            if (dataKey2) {
                aggregated[d.year].val2 += d[dataKey2];
            }
        });

        // Filter out year labels that have 0 data for these specific metrics
        let years = Object.keys(aggregated).filter(y => aggregated[y].val1 > 0 || aggregated[y].val2 > 0).sort();
        
        return {
            labels: years,
            dataset1: years.map(y => aggregated[y].val1),
            dataset2: dataKey2 ? years.map(y => aggregated[y].val2) : []
        };
    },

    renderCharts() {
        this.renderApplicationsChart();
        this.renderAwardeesChart();
        this.renderStakeholdersChart();
    },

    createChart(canvasId, type, data, options) {
        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }
        const ctx = document.getElementById(canvasId).getContext('2d');
        this.charts[canvasId] = new Chart(ctx, { type, data, options });
    },

    renderApplicationsChart() {
        const data = this.aggregateByYear('scHubApps', 'consortiumApps');
        
        this.createChart('applicationsChart', 'bar', {
            labels: data.labels,
            datasets: [
                {
                    label: 'South Central Hub',
                    data: data.dataset1,
                    backgroundColor: '#2563EB',
                    borderRadius: 4
                },
                {
                    label: 'Consortium Average',
                    data: data.dataset2,
                    backgroundColor: '#93C5FD',
                    borderRadius: 4
                }
            ]
        }, {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        });
    },

    renderAwardeesChart() {
        const data = this.aggregateByYear('scHubAwardees', 'consortiumAwardees');
        
        this.createChart('awardeesChart', 'bar', {
            labels: data.labels,
            datasets: [
                {
                    label: 'South Central Hub',
                    data: data.dataset1,
                    backgroundColor: '#059669',
                    borderRadius: 4
                },
                {
                    label: 'Consortium Average',
                    data: data.dataset2,
                    backgroundColor: '#6EE7B7',
                    borderRadius: 4
                }
            ]
        }, {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        });
    },

    renderStakeholdersChart() {
        if (!this.usTopology) {
            console.warn('US Topology data not loaded yet.');
            return;
        }

        const stateFilter = document.getElementById('stakeholdersStateFilter').value;

        // Base filter for stakeholders charts
        const stakeholdersData = this.filteredData.filter(d => !stateFilter || d.state === stateFilter);

        // Aggregate stakeholders by state (for map)
        const stateData = {};
        stakeholdersData.forEach(d => {
            if (!stateData[d.state]) {
                stateData[d.state] = 0;
            }
            stateData[d.state] += d.stakeholders;
        });

        // Format data for chartjs-chart-geo
        // chartjs-chart-geo uses topojson.feature
        const statesFeature = ChartGeo.topojson.feature(this.usTopology, this.usTopology.objects.states).features;
        
        const mapData = statesFeature.map(feature => {
            const stateName = feature.properties.name;
            return {
                feature: feature,
                value: stateData[stateName] || 0
            };
        });

        this.createChart('stakeholdersChart', 'choropleth', {
            labels: statesFeature.map(f => f.properties.name),
            datasets: [{
                label: 'States',
                outline: statesFeature,
                data: mapData
            }]
        }, {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const stateName = context.raw.feature.properties.name;
                            const value = context.raw.value;
                            return `${stateName}: ${value} Stakeholders`;
                        }
                    }
                }
            },
            scales: {
                projection: {
                    axis: 'x',
                    projection: 'albersUsa'
                }
            }
        });

        // Aggregate stakeholders by year (for bar chart)
        const yearData = {};
        stakeholdersData.forEach(d => {
            if (!yearData[d.year]) {
                yearData[d.year] = 0;
            }
            yearData[d.year] += d.stakeholders;
        });

        const years = Object.keys(yearData).filter(y => yearData[y] > 0).sort();

        this.createChart('stakeholdersBarChart', 'bar', {
            labels: years,
            datasets: [{
                label: 'Stakeholders',
                data: years.map(y => yearData[y]),
                backgroundColor: '#D97706',
                borderRadius: 4
            }]
        }, {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    dashboard.init();
});
