// Airtable Service - Direct API Integration
class AirtableService {
    constructor(config) {
        this.apiKey = config.apiKey;
        this.baseId = config.baseId;
        this.tables = config.tables;
        this.baseUrl = `https://api.airtable.com/v0/${this.baseId}`;
    }

    async request(method, endpoint, data = null) {
        const url = `${this.baseUrl}/${endpoint}`;
        
        const options = {
            method: method,
            headers: {
                'Authorization': `Bearer ${this.apiKey}`,
                'Content-Type': 'application/json'
            }
        };

        if (data) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error?.message || `Airtable API Error: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Airtable API request failed:', error);
            throw error;
        }
    }

    async getRecords(tableName, params = {}) {
        const tableId = this.tables[tableName] || tableName;
        const queryString = new URLSearchParams(params).toString();
        const endpoint = queryString ? `${tableId}?${queryString}` : tableId;
        return await this.request('GET', endpoint);
    }

    async getRecord(tableName, recordId) {
        const tableId = this.tables[tableName] || tableName;
        return await this.request('GET', `${tableId}/${recordId}`);
    }

    async createRecord(tableName, fields) {
        const tableId = this.tables[tableName] || tableName;
        const data = { fields: fields, typecast: true };
        return await this.request('POST', tableId, data);
    }

    async updateRecord(tableName, recordId, fields) {
        const tableId = this.tables[tableName] || tableName;
        const data = { fields: fields, typecast: true };
        return await this.request('PATCH', `${tableId}/${recordId}`, data);
    }
}
