const dashboard = {
    events: [],
    charts: {},
    currentYear: '2026',
    
    apiConfig: {
        baseUrl: window.location.pathname.replace(/\/[^/]*$/, '') // dynamic base path
    },

    async init() {
        await this.populateYearDropdown();
        await this.detectCurrentYear();
        await this.fetchEvents();
        this.renderStats();
        this.renderCharts();
        this.renderInsights();
        this.populateDemographicFilter();
        this.renderInteractionsInsight();
        
        document.getElementById('yearSelect').addEventListener('change', async (e) => {
            this.currentYear = e.target.value.split(' ')[0];
            this.destroyCharts();
            await this.fetchEvents();
            this.renderStats();
            this.renderCharts();
            this.renderInsights();
            this.populateDemographicFilter();
            this.renderInteractionsInsight();
        });
        
        document.getElementById('demographicFilter').addEventListener('change', () => {
            this.renderInteractionsInsight();
        });
    },

    async populateYearDropdown() {
        const url = `${this.apiConfig.baseUrl}/api/index.php?resource=years`;
        
        try {
            const response = await fetch(url, { method: 'GET' });

            if (!response.ok) {
                console.error('Failed to fetch years:', response.statusText);
                return;
            }
            
            const data = await response.json();
            
            const eventTables = (data.tables || [])
                .map(table => table.name)
                .sort((a, b) => {
                    const yearA = parseInt(a.match(/\d{4}/)?.[0] || '0');
                    const yearB = parseInt(b.match(/\d{4}/)?.[0] || '0');
                    return yearB - yearA;
                });
            
            const yearSelect = document.getElementById('yearSelect');
            yearSelect.innerHTML = '';
            
            eventTables.forEach(tableName => {
                const option = document.createElement('option');
                option.value = tableName;
                option.textContent = tableName;
                yearSelect.appendChild(option);
            });
            
            if (eventTables.length > 0) {
                yearSelect.value = eventTables[0];
                this.currentYear = eventTables[0].split(' ')[0];
            }
            
        } catch (error) {
            console.error('Error fetching years:', error);
        }
    },

    async detectCurrentYear() {
        try {
            const url = `${this.apiConfig.baseUrl}/api/index.php?resource=years`;
            const response = await fetch(url, { method: 'GET' });

            if (!response.ok) return;
            
            const data = await response.json();
            const eventTables = (data.tables || [])
                .map(table => table.name)
                .sort((a, b) => {
                    const yearA = parseInt(a.match(/\d{4}/)?.[0] || '0');
                    const yearB = parseInt(b.match(/\d{4}/)?.[0] || '0');
                    return yearB - yearA;
                });

            if (eventTables.length > 0) {
                this.currentYear = eventTables[0].split(' ')[0];
            }
        } catch (error) {
            console.error('Error detecting year:', error);
        }
    },

    async fetchEvents() {
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
            this.events = data.records || [];
            console.log('Fetched events:', this.events.length);
        } catch (error) {
            console.error('Error fetching events:', error);
            this.showError('Failed to load event data');
        }
    },

    
    renderStats() {
        const totalEvents = this.events.length;
        
        let totalInteractions = 0;
        this.events.forEach(p => {
            const interactions = parseInt(p.fields['# of Interactions'] || 0);
            if (!isNaN(interactions)) totalInteractions += interactions;
        });
        
        const upcomingEvents = this.events.filter(p => {
            const status = (p.fields['Status (Tentative, Ready, Completed)'] || '').toLowerCase();
            return status === 'tentative' || status === 'ready';
        }).length;
        
        const eventTypes = new Set(
            this.events
                .map(p => p.fields['Type of Event'])
                .filter(Boolean)
        );

        document.getElementById('totalEvents').textContent = totalEvents;
        document.getElementById('upcomingEvents').textContent = upcomingEvents;
        document.getElementById('eventTypes').textContent = eventTypes.size;
    },

    renderCharts() {
        this.renderEventTypeChart();
        this.renderStatusChart();
        this.renderLocationChart();
        this.renderDemographicChart();
    },

    destroyCharts() {
        Object.values(this.charts).forEach(chart => {
            if (chart) chart.destroy();
        });
        this.charts = {};
    },

    renderEventTypeChart() {
        const typeCounts = {};
        this.events.forEach(p => {
            const type = p.fields['Type of Event'] || 'Unspecified';
            typeCounts[type] = (typeCounts[type] || 0) + 1;
        });

        const sortedTypes = Object.entries(typeCounts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 8);

        const ctx = document.getElementById('eventTypeChart');
        this.charts.eventType = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: sortedTypes.map(([type]) => type),
                datasets: [{
                    data: sortedTypes.map(([, count]) => count),
                    backgroundColor: [
                        '#C8102E',
                        '#00B388',
                        '#F6BE00',
                        '#2563EB',
                        '#DC2626',
                        '#059669',
                        '#D97706',
                        '#7C3AED'
                    ],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                family: 'Fira Sans',
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    },

    renderStatusChart() {
        const statusCounts = {
            'Tentative': 0,
            'Ready': 0,
            'Completed': 0
        };

        this.events.forEach(p => {
            const status = p.fields['Status (Tentative, Ready, Completed)'] || 'Tentative';
            if (statusCounts.hasOwnProperty(status)) {
                statusCounts[status]++;
            } else {
                statusCounts['Tentative']++;
            }
        });

        const ctx = document.getElementById('statusChart');
        this.charts.status = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(statusCounts),
                datasets: [{
                    label: 'Number of Events',
                    data: Object.values(statusCounts),
                    backgroundColor: ['#F6BE00', '#00B388', '#54585A'],
                    borderColor: ['#D99E00', '#00866C', '#3D3D3E'],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed.y;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${value} events (${percentage}%)`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                family: 'Fira Sans'
                            }
                        },
                        grid: {
                            color: '#E5E7EB'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Fira Sans'
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    },

    renderLocationChart() {
        const locationCounts = {};
        this.events.forEach(p => {
            const neighborhood = p.fields['Neighborhood'] || '';
            const address = p.fields['Address'] || '';
            const eventLocation = p.fields['Event Location'] || '';
            const location = neighborhood || address || eventLocation || 'Unknown';
            locationCounts[location] = (locationCounts[location] || 0) + 1;
        });

        const sortedLocations = Object.entries(locationCounts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 10);

        const ctx = document.getElementById('locationChart');
        this.charts.location = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: sortedLocations.map(([loc]) => loc),
                datasets: [{
                    label: 'Events',
                    data: sortedLocations.map(([, count]) => count),
                    backgroundColor: '#C8102E',
                    borderColor: '#960C22',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                family: 'Fira Sans'
                            }
                        },
                        grid: {
                            color: '#E5E7EB'
                        }
                    },
                    y: {
                        ticks: {
                            font: {
                                family: 'Fira Sans'
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    },

    renderDemographicChart() {
        const demoCounts = {};
        this.events.forEach(p => {
            const demo = p.fields['Demographic Served'] || 'Unspecified';
            demoCounts[demo] = (demoCounts[demo] || 0) + 1;
        });

        const sortedDemos = Object.entries(demoCounts)
            .sort((a, b) => b[1] - a[1]);

        const ctx = document.getElementById('demographicChart');
        this.charts.demographic = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: sortedDemos.map(([demo]) => demo),
                datasets: [{
                    data: sortedDemos.map(([, count]) => count),
                    backgroundColor: [
                        '#C8102E',
                        '#00B388',
                        '#F6BE00',
                        '#2563EB',
                        '#DC2626',
                        '#059669'
                    ],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                family: 'Fira Sans',
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    },

    renderInsights() {
        const insights = this.generateInsights();
        const container = document.getElementById('insightsContent');
        
        container.innerHTML = insights.map(insight => `
            <div class="insight-item">
                <i data-lucide="${insight.icon}" class="insight-icon"></i>
                <div class="insight-text">${insight.text}</div>
            </div>
        `).join('');

        const topCategories = this.getTopCategories();
        const categoriesContainer = document.getElementById('topCategories');
        
        categoriesContainer.innerHTML = topCategories.map(cat => `
            <div class="category-item">
                <span class="category-name">${cat.name}</span>
                <span class="category-badge">${cat.count}</span>
            </div>
        `).join('');

        lucide.createIcons();
    },

    generateInsights() {
        const insights = [];
        const total = this.events.length;
        if (total === 0) {
            insights.push({ icon: 'info', text: 'No events data available yet.' });
            return insights;
        }
        
        // Status breakdown
        const completed = this.events.filter(p => 
            (p.fields['Status (Tentative, Ready, Completed)'] || '').toLowerCase() === 'completed'
        ).length;
        const completionRate = ((completed / total) * 100).toFixed(1);
        
        insights.push({
            icon: 'check-circle',
            text: `<strong>${completionRate}%</strong> of events are completed (${completed} out of ${total})`
        });

        // Total interactions
        let totalInteractions = 0;
        this.events.forEach(p => {
            const interactions = parseInt(p.fields['# of Interactions'] || 0);
            if (!isNaN(interactions)) totalInteractions += interactions;
        });
        if (totalInteractions > 0) {
            const avgInteractions = (totalInteractions / total).toFixed(0);
            insights.push({
                icon: 'users',
                text: `Average of <strong>${avgInteractions} interactions</strong> per event, with <strong>${totalInteractions.toLocaleString()}</strong> total`
            });
        }

        // Top event type
        const typeCounts = {};
        this.events.forEach(p => {
            const type = p.fields['Type of Event'] || 'Unspecified';
            typeCounts[type] = (typeCounts[type] || 0) + 1;
        });
        const topType = Object.entries(typeCounts).sort((a, b) => b[1] - a[1])[0];
        if (topType) {
            insights.push({
                icon: 'trending-up',
                text: `<strong>${topType[0]}</strong> is the most common event type with ${topType[1]} events`
            });
        }

        // Top location
        const locationCounts = {};
        this.events.forEach(p => {
            const neighborhood = p.fields['Neighborhood'] || '';
            const address = p.fields['Address'] || '';
            const eventLocation = p.fields['Event Location'] || '';
            const loc = neighborhood || address || eventLocation;
            if (loc) locationCounts[loc] = (locationCounts[loc] || 0) + 1;
        });
        const topLocation = Object.entries(locationCounts).sort((a, b) => b[1] - a[1])[0];
        if (topLocation) {
            insights.push({
                icon: 'map-pin',
                text: `<strong>${topLocation[0]}</strong> is the most used venue with ${topLocation[1]} events`
            });
        }

        // Demographics
        const demographics = new Set(this.events.map(p => p.fields['Demographic Served']).filter(Boolean));
        insights.push({
            icon: 'globe',
            text: `Events serve <strong>${demographics.size} demographic groups</strong>, showing broad community reach`
        });

        return insights;
    },

    getTopCategories() {
        const typeCounts = {};
        this.events.forEach(p => {
            const type = p.fields['Type of Event'] || 'Unspecified';
            typeCounts[type] = (typeCounts[type] || 0) + 1;
        });

        return Object.entries(typeCounts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 5)
            .map(([name, count]) => ({ name, count }));
    },

    populateDemographicFilter() {
        const demographics = new Set();
        this.events.forEach(e => {
            const demo = e.fields['Demographic Served'];
            if (demo) demographics.add(demo);
        });

        const select = document.getElementById('demographicFilter');
        select.innerHTML = '<option value="all">All Demographics</option>';
        
        Array.from(demographics).sort().forEach(demo => {
            const option = document.createElement('option');
            option.value = demo;
            option.textContent = demo;
            select.appendChild(option);
        });
    },

    renderInteractionsInsight() {
        const selectedDemo = document.getElementById('demographicFilter').value;
        
        let filteredEvents = this.events;
        if (selectedDemo !== 'all') {
            filteredEvents = this.events.filter(e => 
                e.fields['Demographic Served'] === selectedDemo
            );
        }

        // Only count completed events for interactions
        const completedEvents = filteredEvents.filter(e => {
            const status = (e.fields['Status (Tentative, Ready, Completed)'] || '').toLowerCase();
            return status === 'completed';
        });

        let totalInteractions = 0;
        const eventsWithInteractions = [];
        
        completedEvents.forEach(e => {
            const interactions = parseInt(e.fields['# of Interactions'] || 0);
            if (!isNaN(interactions)) {
                totalInteractions += interactions;
                if (interactions > 0) {
                    eventsWithInteractions.push({
                        name: e.fields['Event Name'] || 'Untitled Event',
                        interactions: interactions,
                        date: e.fields['Event Date'] || '',
                        type: e.fields['Type of Event'] || 'Unspecified'
                    });
                }
            }
        });

        const eventCount = completedEvents.length;
        const avgInteractions = eventCount > 0 ? (totalInteractions / eventCount).toFixed(1) : 0;
        
        eventsWithInteractions.sort((a, b) => b.interactions - a.interactions);
        const topEvent = eventsWithInteractions.length > 0 ? eventsWithInteractions[0].name : '-';

        document.getElementById('filteredTotalInteractions').textContent = totalInteractions.toLocaleString();
        document.getElementById('filteredEventCount').textContent = eventCount;
        document.getElementById('filteredAvgInteractions').textContent = avgInteractions;
        document.getElementById('filteredTopEvent').textContent = topEvent.length > 30 ? topEvent.substring(0, 30) + '...' : topEvent;

        const breakdownContainer = document.getElementById('interactionsBreakdown');
        
        if (eventsWithInteractions.length === 0) {
            breakdownContainer.innerHTML = '<div class="loading">No interaction data available for this filter.</div>';
            return;
        }

        const topEvents = eventsWithInteractions.slice(0, 10);
        
        breakdownContainer.innerHTML = topEvents.map(event => {
            let displayDate = event.date;
            if (displayDate) {
                try {
                    const dateObj = new Date(displayDate);
                    displayDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                } catch(e) { /* keep raw */ }
            }
            
            return `
                <div class="breakdown-item">
                    <div class="breakdown-event-name">${event.name}</div>
                    <div class="breakdown-event-meta">
                        <span>${event.type}</span>
                        <span>${displayDate || 'No date'}</span>
                        <div class="breakdown-interactions">
                            <i data-lucide="users"></i>
                            <span>${event.interactions.toLocaleString()}</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        lucide.createIcons();
    },

    showError(message) {
        console.error(message);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    dashboard.init();
});
