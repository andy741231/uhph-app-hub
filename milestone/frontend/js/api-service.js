/**
 * API Service for backend communication
 */

export class ApiService {
  constructor(baseUrl = '../backend/api.php') {
    this.baseUrl = baseUrl;
  }

  /**
   * Handle API errors
   */
  handleError(error) {
    if (error.response && error.response.data) {
      const data = error.response.data;
      if (typeof data === 'string') return data;
      if (data.error) return typeof data.error === 'string' ? data.error : (data.error.message || JSON.stringify(data.error));
    }
    return error.message || 'Request failed';
  }

  /**
   * List records from Airtable
   */
  async listRecords(baseId, table, options = {}) {
    const params = { 
      action: 'listRecords', 
      baseId, 
      table,
      ...options
    };
    
    try {
      const response = await axios.get(this.baseUrl, { params });
      return response.data;
    } catch (error) {
      throw new Error(this.handleError(error));
    }
  }

  /**
   * Get a single record
   */
  async getRecord(baseId, table, id) {
    const params = { 
      action: 'getRecord', 
      baseId, 
      table, 
      id 
    };
    
    try {
      const response = await axios.get(this.baseUrl, { params });
      return response.data;
    } catch (error) {
      throw new Error(this.handleError(error));
    }
  }

  /**
   * Create a new record
   */
  async createRecord(baseId, table, fields) {
    const url = `${this.baseUrl}?action=createRecord&baseId=${encodeURIComponent(baseId)}&table=${encodeURIComponent(table)}`;
    
    try {
      const response = await axios.post(url, { fields });
      return response.data;
    } catch (error) {
      throw new Error(this.handleError(error));
    }
  }

  /**
   * Update a record
   */
  async updateRecord(baseId, table, id, fields) {
    const params = {
      action: 'updateRecord',
      baseId,
      table,
      id
    };
    
    try {
      const response = await axios.patch(this.baseUrl, { fields }, { params });
      return response.data;
    } catch (error) {
      throw new Error(this.handleError(error));
    }
  }

  /**
   * Delete a record
   */
  async deleteRecord(baseId, table, id) {
    const url = `${this.baseUrl}?action=deleteRecord&baseId=${encodeURIComponent(baseId)}&table=${encodeURIComponent(table)}&id=${encodeURIComponent(id)}`;
    
    try {
      const response = await axios.delete(url);
      return response.data;
    } catch (error) {
      throw new Error(this.handleError(error));
    }
  }

  /**
   * List tables in a base
   */
  async listTables(baseId) {
    const params = { 
      action: 'listTables', 
      baseId 
    };
    
    try {
      const response = await axios.get(this.baseUrl, { params });
      return response.data;
    } catch (error) {
      throw new Error(this.handleError(error));
    }
  }

  /**
   * List bases in a workspace
   */
  async listBases(workspaceId) {
    const params = { 
      action: 'listBases', 
      workspaceId 
    };
    
    try {
      const response = await axios.get(this.baseUrl, { params });
      return response.data;
    } catch (error) {
      throw new Error(this.handleError(error));
    }
  }
}
