const dashboard = {
    partners: [],
    charts: {},
    
    apiConfig: {
        baseUrl: window.location.pathname.replace(/\/[^/]*$/, '')
    },

    async init() {
        await this.fetchPartners();
        this.renderStats();
        this.renderCharts();
        this.renderInsights();
        this.renderStateTable();
    },

    async fetchPartners() {
        try {
            const url = `${this.apiConfig.baseUrl}/api/index.php?resource=partners`;
            
            const response = await fetch(url, { method: 'GET' });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            this.partners = data.records || [];
            console.log('Fetched partners:', this.partners.length);
        } catch (error) {
            console.error('Error fetching partners:', error);
            this.showError('Failed to load partner data');
        }
    },

    async refreshData() {
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i data-lucide="loader" class="icon-small"></i> Loading...';
        btn.disabled = true;

        await this.fetchPartners();
        this.renderStats();
        this.destroyCharts();
        this.renderCharts();
        this.renderInsights();
        this.renderStateTable();

        btn.innerHTML = originalHTML;
        btn.disabled = false;
        lucide.createIcons();
    },

    renderStats() {
        const totalPartners = this.partners.length;
        
        let formalMOUs = 0;
        let nonFormalMOUs = 0;
        
        this.partners.forEach(p => {
            const status = p.fields['with MOUs/Subaward? '] || 'Non-Formal';
            if (status.includes('Non-Formal')) {
                nonFormalMOUs++;
            } else if (status.includes('Formal')) {
                formalMOUs++;
            } else {
                nonFormalMOUs++;
            }
        });
        
        const cities = new Set(
            this.partners
                .map(p => p.fields['City'])
                .filter(Boolean)
        );
        
        const orgTypes = new Set(
            this.partners
                .map(p => p.fields['Org Type'])
                .filter(Boolean)
        );

        document.getElementById('totalPartners').textContent = totalPartners;
        document.getElementById('uniqueCities').textContent = cities.size;
        document.getElementById('orgTypes').textContent = orgTypes.size;
    },

    renderCharts() {
        this.renderOrgTypeChart();
        this.renderMOUStatusChart();
        this.renderCityChart();
        this.renderPIChart();
        this.renderNeighborhoodChart();
    },

    destroyCharts() {
        Object.values(this.charts).forEach(chart => {
            if (chart) chart.destroy();
        });
        this.charts = {};
    },

    renderOrgTypeChart() {
        const orgTypeCounts = {};
        this.partners.forEach(p => {
            const type = p.fields['Org Type'] || 'Unspecified';
            orgTypeCounts[type] = (orgTypeCounts[type] || 0) + 1;
        });

        const sortedTypes = Object.entries(orgTypeCounts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 8);

        const ctx = document.getElementById('orgTypeChart');
        this.charts.orgType = new Chart(ctx, {
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

    renderMOUStatusChart() {
        const mouCounts = {
            'Formal (MOU)': 0,
            'Non-Formal': 0
        };

        this.partners.forEach(p => {
            const status = p.fields['with MOUs/Subaward? '] || 'Non-Formal';
            if (status.includes('Non-Formal')) {
                mouCounts['Non-Formal']++;
            } else if (status.includes('Formal')) {
                mouCounts['Formal (MOU)']++;
            } else {
                mouCounts['Non-Formal']++;
            }
        });

        const ctx = document.getElementById('mouStatusChart');
        this.charts.mouStatus = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(mouCounts),
                datasets: [{
                    label: 'Number of Partners',
                    data: Object.values(mouCounts),
                    backgroundColor: ['#00B388', '#F6BE00'],
                    borderColor: ['#00866C', '#D99E00'],
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
                                return `${value} partners (${percentage}%)`;
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

    renderCityChart() {
        const cityCounts = {};
        this.partners.forEach(p => {
            const city = p.fields['City'] || 'Unknown';
            cityCounts[city] = (cityCounts[city] || 0) + 1;
        });

        const sortedCities = Object.entries(cityCounts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 10);

        const ctx = document.getElementById('cityChart');
        this.charts.city = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: sortedCities.map(([city]) => city),
                datasets: [{
                    label: 'Partners',
                    data: sortedCities.map(([, count]) => count),
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

    renderPIChart() {
        const piCounts = {};
        this.partners.forEach(p => {
            const pi = p.fields['PI'] || 'Unassigned';
            piCounts[pi] = (piCounts[pi] || 0) + 1;
        });

        const sortedPIs = Object.entries(piCounts)
            .sort((a, b) => b[1] - a[1]);

        const ctx = document.getElementById('piChart');
        this.charts.pi = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: sortedPIs.map(([pi]) => pi),
                datasets: [{
                    data: sortedPIs.map(([, count]) => count),
                    backgroundColor: [
                        '#C8102E',
                        '#00B388',
                        '#F6BE00',
                        '#2563EB',
                        '#DC2626'
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

    renderNeighborhoodChart() {
        const neighborhoodCounts = {};
        this.partners.forEach(p => {
            const neighborhood = p.fields['Neighborhood'] || 'Not Specified';
            neighborhoodCounts[neighborhood] = (neighborhoodCounts[neighborhood] || 0) + 1;
        });

        const sortedNeighborhoods = Object.entries(neighborhoodCounts)
            .sort((a, b) => b[1] - a[1]);

        const ctx = document.getElementById('neighborhoodChart');
        this.charts.neighborhood = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: sortedNeighborhoods.map(([name]) => name),
                datasets: [{
                    label: 'Partners',
                    data: sortedNeighborhoods.map(([, count]) => count),
                    backgroundColor: '#7C3AED',
                    borderColor: '#6D28D9',
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
                                return `${value} partners (${percentage}%)`;
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
        const total = this.partners.length;
        
        const formalMOUs = this.partners.filter(p => {
            const status = p.fields['with MOUs/Subaward? '] || 'Non-Formal';
            return status.includes('Formal') && !status.includes('Non-Formal');
        }).length;
        const mouRate = ((formalMOUs / total) * 100).toFixed(1);
        
        insights.push({
            icon: 'file-check',
            text: `<strong>${mouRate}%</strong> of partnerships have formal MOUs (${formalMOUs} out of ${total})`
        });

        const orgTypeCounts = {};
        this.partners.forEach(p => {
            const type = p.fields['Org Type'] || 'Unspecified';
            orgTypeCounts[type] = (orgTypeCounts[type] || 0) + 1;
        });
        const topOrgType = Object.entries(orgTypeCounts).sort((a, b) => b[1] - a[1])[0];
        if (topOrgType) {
            insights.push({
                icon: 'trending-up',
                text: `<strong>${topOrgType[0]}</strong> is the most common organization type with ${topOrgType[1]} partners`
            });
        }

        const cityCounts = {};
        this.partners.forEach(p => {
            const city = p.fields['City'];
            if (city) cityCounts[city] = (cityCounts[city] || 0) + 1;
        });
        const topCity = Object.entries(cityCounts).sort((a, b) => b[1] - a[1])[0];
        if (topCity) {
            insights.push({
                icon: 'map-pin',
                text: `<strong>${topCity[0]}</strong> has the highest concentration with ${topCity[1]} partners`
            });
        }

        const piCounts = {};
        this.partners.forEach(p => {
            const pi = p.fields['PI'];
            if (pi) piCounts[pi] = (piCounts[pi] || 0) + 1;
        });
        const topPI = Object.entries(piCounts).sort((a, b) => b[1] - a[1])[0];
        if (topPI) {
            insights.push({
                icon: 'users',
                text: `<strong>${topPI[0]}</strong> manages the most partnerships with ${topPI[1]} partners`
            });
        }

        const states = new Set(this.partners.map(p => p.fields['State']).filter(Boolean));
        insights.push({
            icon: 'globe',
            text: `Partnerships span across <strong>${states.size} states</strong>, showing broad geographic reach`
        });

        return insights;
    },

    getTopCategories() {
        const orgTypeCounts = {};
        this.partners.forEach(p => {
            const type = p.fields['Org Type'] || 'Unspecified';
            orgTypeCounts[type] = (orgTypeCounts[type] || 0) + 1;
        });

        return Object.entries(orgTypeCounts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 5)
            .map(([name, count]) => ({ name, count }));
    },

    renderStateTable() {
        const stateData = {};
        
        this.partners.forEach(p => {
            const state = p.fields['State'] || 'Unknown';
            const mouStatus = p.fields['with MOUs/Subaward? '] || 'Non-Formal';
            
            if (!stateData[state]) {
                stateData[state] = {
                    total: 0,
                    formal: 0,
                    nonFormal: 0
                };
            }
            
            stateData[state].total++;
            if (mouStatus.includes('Non-Formal')) {
                stateData[state].nonFormal++;
            } else if (mouStatus.includes('Formal')) {
                stateData[state].formal++;
            } else {
                stateData[state].nonFormal++;
            }
        });

        const sortedStates = Object.entries(stateData)
            .sort((a, b) => b[1].total - a[1].total);

        const tbody = document.getElementById('stateTableBody');
        tbody.innerHTML = sortedStates.map(([state, data]) => {
            const mouRate = ((data.formal / data.total) * 100).toFixed(1);
            let rateClass = 'low';
            if (mouRate >= 70) rateClass = 'high';
            else if (mouRate >= 40) rateClass = 'medium';
            
            return `
                <tr>
                    <td><strong>${state}</strong></td>
                    <td>${data.total}</td>
                    <td>
                        <span class="percentage-badge ${rateClass}">${mouRate}%</span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${mouRate}%"></div>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    },

    showError(message) {
        console.error(message);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    dashboard.init();
});
