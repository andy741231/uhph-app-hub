const { createApp } = Vue;

createApp({
    data() {
        return {
            currentView: 'dashboard',
            loading: true,
            submitting: false,
            error: null,
            inventory: [],
            transactions: [],
            users: [],
            showModal: false,
            showItemDetailsModal: false,
            selectedItem: null,
            transactionFilter: 'all',
            searchQuery: '',
            form: {
                type: 'Check Out',
                itemId: '',
                quantity: 1,
                user: '',
                notes: '',
                newItem: {
                    name: '',
                    category: '',
                    description: '',
                    stock: 0,
                    threshold: 10
                }
            },
            toast: {
                show: false,
                type: 'success',
                title: '',
                message: ''
            },
            charts: {
                stock: null,
                transaction: null
            }
        }
    },
    watch: {
        currentView(newVal) {
            if (newVal === 'dashboard') {
                this.$nextTick(() => this.initCharts());
            }
        },
        inventory: {
            handler() { if (this.currentView === 'dashboard') this.initCharts(); },
            deep: true
        },
        transactions: {
            handler() { if (this.currentView === 'dashboard') this.initCharts(); },
            deep: true
        }
    },
    computed: {
        filteredInventory() {
            if (!this.searchQuery) return this.inventory;
            const query = this.searchQuery.toLowerCase();
            return this.inventory.filter(item => 
                (item.fields['Item Name'] || '').toLowerCase().includes(query) ||
                (item.fields['Description'] || '').toLowerCase().includes(query) ||
                (item.fields['Category'] || '').toLowerCase().includes(query)
            );
        },
        filteredTransactions() {
            let txs = this.transactions;
            if (this.transactionFilter !== 'all') {
                txs = txs.filter(t => t.fields['Transaction Type'] === this.transactionFilter);
            }
            return txs; // Already sorted by backend usually, but could sort here too
        },
        lowStockItems() {
            return this.inventory.filter(item => {
                const current = item.fields['Current Stock Quantity'] || 0;
                const min = item.fields['Minimum Stock Threshold'] || 0;
                return current <= min;
            });
        },
        popularItem() {
            const counts = {};
            this.transactions.forEach(t => {
                if (t.fields['Transaction Type'] === 'Check Out') {
                    const itemIds = t.fields['Items'] || [];
                    itemIds.forEach(id => {
                        // Count total quantity checked out
                        counts[id] = (counts[id] || 0) + (t.fields['Quantity'] || 0);
                    });
                }
            });
            
            let maxId = null;
            let maxCount = -1;
            for (const [id, count] of Object.entries(counts)) {
                if (count > maxCount) {
                    maxCount = count;
                    maxId = id;
                }
            }
            
            if (!maxId) return null;
            const item = this.inventory.find(i => i.id === maxId);
            return item ? { name: item.fields['Item Name'], count: maxCount } : null;
        },
        maxQuantity() {
            if (this.form.type !== 'Check Out' || !this.form.itemId) return 9999;
            return this.getCurrentStock(this.form.itemId);
        },
        selectedItemDisplayFields() {
            const item = this.selectedItem;
            if (!item || !item.fields) return [];

            const hidden = new Set([
                'Item Name',
                'Description',
                'Category',
                'Current Stock Quantity',
                'Minimum Stock Threshold'
            ]);

            return Object.entries(item.fields)
                .filter(([key, value]) => !hidden.has(key) && value !== undefined && value !== null && value !== '')
                .map(([key, value]) => {
                    let displayValue = value;
                    if (Array.isArray(value)) displayValue = value.join(', ');
                    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                        try {
                            displayValue = JSON.stringify(value);
                        } catch {
                            displayValue = String(value);
                        }
                    }
                    return { key, value: displayValue };
                });
        }
    },
    methods: {
        async apiRequest(resource, method = 'GET', body = null) {
            const url = `${API_BASE}/api/index.php?resource=${resource}`;
            console.log('Fetching:', url);
            const opts = { method, headers: { 'Content-Type': 'application/json' } };
            if (body) opts.body = JSON.stringify(body);
            const res = await fetch(url, opts);
            if (!res.ok) {
                const err = await res.json().catch(() => ({ error: res.statusText }));
                console.error('API Error:', err);
                throw new Error(err.error || res.statusText);
            }
            const data = await res.json();
            console.log('API Success:', resource, data);
            return data;
        },
        async fetchData() {
            this.loading = true;
            this.error = null;
            try {
                // Fetch all data in parallel from MySQL via PHP API
                const [invRes, txRes, usersRes] = await Promise.all([
                    this.apiRequest('inventory'),
                    this.apiRequest('transactions'),
                    this.apiRequest('users')
                ]);

                this.inventory = invRes.records || [];
                this.transactions = txRes.records || [];
                this.users = usersRes.records || [];
                
                // Initialize charts after data is loaded
                this.$nextTick(() => {
                    if (this.currentView === 'dashboard') {
                        this.initCharts();
                    }
                });
            } catch (err) {
                console.error('Error fetching data:', err);
                this.error = `Failed to load data: ${err.message}`;
            } finally {
                this.loading = false;
            }
        },
        getCurrentStock(itemId) {
            const item = this.inventory.find(i => i.id === itemId);
            return item ? (item.fields['Current Stock Quantity'] || 0) : 0;
        },
        getItemName(itemId) {
            // Since Airtable linked records come as IDs, we try to find the item in our loaded inventory
            // Note: In some Airtable API responses for linked records, it might just give ID. 
            // If the transaction record includes the name (lookup field), we could use that.
            // For now, we search our inventory list.
            if (!itemId) return 'Unknown Item';
            const item = this.inventory.find(i => i.id === itemId);
            return item ? item.fields['Item Name'] : 'Unknown Item';
        },
        getUserName(userId) {
            // Similar logic for users if they are linked records. 
            // However, the Transactions table schema shows "User Name" as a Multiple Select or similar?
            // Checking schema: "User Name" type is "multipleSelects".
            // So userId passed here might actually be the string name or an array of names.
            if (Array.isArray(userId)) return userId.join(', ');
            return userId || 'Unknown User';
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return new Intl.DateTimeFormat('en-US', {
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: 'numeric'
            }).format(date);
        },
        openTransactionModal(type = 'Check Out', itemId = null) {
            this.form = {
                type: type,
                itemId: itemId || (this.inventory.length > 0 ? this.inventory[0].id : ''),
                quantity: 1,
                user: this.users.length > 0 ? this.users[0].id : '',
                notes: '',
                newItem: {
                    name: '',
                    category: '',
                    description: '',
                    stock: 0,
                    threshold: 10
                }
            };
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
        },
        openItemDetailsModal(itemOrId) {
            let item = itemOrId;
            if (typeof itemOrId === 'string') {
                item = this.inventory.find(i => i.id === itemOrId) || null;
            }
            this.selectedItem = item;
            this.showItemDetailsModal = true;
        },
        closeItemDetailsModal() {
            this.showItemDetailsModal = false;
            this.selectedItem = null;
        },
        showToast(type, title, message) {
            this.toast = { show: true, type, title, message };
            setTimeout(() => {
                this.toast.show = false;
            }, 3000);
        },
        async submitTransaction() {
            this.submitting = true;
            try {
                if (this.form.type === 'Create Item') {
                    // Handle New Item Creation via PHP API
                    const response = await this.apiRequest('inventory', 'POST', {
                        name:        this.form.newItem.name,
                        description: this.form.newItem.description || '',
                        category:    this.form.newItem.category || 'Uncategorized',
                        stock:       parseInt(this.form.newItem.stock)     || 0,
                        threshold:   parseInt(this.form.newItem.threshold) || 10
                    });
                    
                    // Add to local inventory list
                    this.inventory.push(response);
                    
                    this.showToast('success', 'Item Created', `${this.form.newItem.name} has been added to inventory.`);
                    this.closeModal();

                } else {
                    // Handle Standard Transaction via PHP API
                    const selectedUser = this.users.find(u => u.id === this.form.user);
                    const userName = selectedUser ? selectedUser.fields['Name'] : this.form.user || 'Unknown';

                    const result = await this.apiRequest('transactions', 'POST', {
                        itemId:   this.form.itemId,
                        type:     this.form.type,
                        quantity: parseInt(this.form.quantity),
                        user:     userName,
                        notes:    this.form.notes || ''
                    });

                    // Update local inventory stock
                    const itemIndex = this.inventory.findIndex(i => i.id === this.form.itemId);
                    if (itemIndex !== -1) {
                        this.inventory[itemIndex].fields['Current Stock Quantity'] = result.newStock;
                    }

                    // Refresh transactions
                    const txRes = await this.apiRequest('transactions');
                    this.transactions = txRes.records || [];

                    this.closeModal();
                    this.showToast('success', 'Success', 'Transaction recorded successfully');
                }

                // Reset form common fields
                this.form.quantity = 1;
                this.form.notes = '';

            } catch (err) {
                console.error('Operation failed:', err);
                const msg = err.message || 'Operation failed';
                this.showToast('error', 'Error', msg);
            } finally {
                this.submitting = false;
            }
        },
        initCharts() {
            if (this.charts.stock) this.charts.stock.destroy();
            if (this.charts.transaction) this.charts.transaction.destroy();

            // 1. Stock by Item Name
            const itemData = {};
            this.inventory.forEach(item => {
                const itemName = item.fields['Item Name'] || 'Unknown';
                itemData[itemName] = item.fields['Current Stock Quantity'] || 0;
            });

            const stockCtx = document.getElementById('stockChart');
            if (stockCtx) {
                this.charts.stock = new Chart(stockCtx, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(itemData),
                        datasets: [{
                            label: 'Stock Quantity',
                            data: Object.values(itemData),
                            backgroundColor: 'rgba(79, 70, 229, 0.6)',
                            borderColor: 'rgba(79, 70, 229, 1)',
                            borderWidth: 1,
                            borderRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { display: false } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

            // 2. Transaction Activity
            const txData = { 'Check In': 0, 'Check Out': 0, 'Restock': 0 };
            this.transactions.forEach(t => {
                const type = t.fields['Transaction Type'];
                if (txData[type] !== undefined) txData[type]++;
            });

            const txCtx = document.getElementById('transactionChart');
            if (txCtx) {
                this.charts.transaction = new Chart(txCtx, {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(txData),
                        datasets: [{
                            data: Object.values(txData),
                            backgroundColor: [
                                'rgba(16, 185, 129, 0.8)', // Emerald (Check In)
                                'rgba(79, 70, 229, 0.8)',  // Indigo (Check Out)
                                'rgba(245, 158, 11, 0.8)'  // Amber (Restock)
                            ],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, font: { family: "'DM Sans', sans-serif" } } }
                        },
                        cutout: '70%'
                    }
                });
            }
        }
    },
    mounted() {
        this.fetchData();
    }
}).mount('#app');
