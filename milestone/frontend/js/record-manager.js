/**
 * Record Manager for handling record operations
 */

import { Utils } from './utils.js';
import { FieldManager } from './field-manager.js';

const { reactive } = Vue;

export class RecordManager {
  constructor(apiService) {
    this.apiService = apiService;
    this.fieldManager = new FieldManager();
    this.PREFERRED_ORDER = ['Date', 'Milestone Type', 'Deliverable'];
    this.hiddenFields = ['id', 'ID'];
    
    this.editor = reactive({
      visible: false,
      loading: false,
      saving: false,
      error: '',
      success: '',
      fields: [],
      formValues: {}
    });
  }

  /**
   * Load records from Airtable
   */
  async loadRecords(baseId, tableName, options = {}) {
    if (!baseId || !tableName) {
      throw new Error('Base and Table required');
    }

    const params = { 
      baseId, 
      table: tableName,
      ...options
    };

    const response = await this.apiService.listRecords(baseId, tableName, params);
    const records = response.records || [];
    const offset = response.offset || '';

    return { records, offset };
  }

  /**
   * Get single record
   */
  async getRecord(baseId, tableName, id) {
    if (!baseId || !tableName || !id) {
      throw new Error('Base, Table, and ID required');
    }

    return await this.apiService.getRecord(baseId, tableName, id);
  }

  /**
   * Create new record
   */
  async createRecord(baseId, tableName, fields) {
    if (!baseId || !tableName) {
      throw new Error('Base and Table required');
    }

    return await this.apiService.createRecord(baseId, tableName, fields);
  }

  /**
   * Update record
   */
  async updateRecord(baseId, tableName, id, fields) {
    if (!baseId || !tableName || !id) {
      throw new Error('Base, Table, and ID required');
    }

    return await this.apiService.updateRecord(baseId, tableName, id, fields);
  }

  /**
   * Delete record
   */
  async deleteRecord(baseId, tableName, id) {
    if (!baseId || !tableName || !id) {
      throw new Error('Base, Table, and ID required');
    }

    return await this.apiService.deleteRecord(baseId, tableName, id);
  }

  /**
   * Get display columns from records
   */
  getDisplayColumns(records) {
    const firstRecord = records[0];
    if (!firstRecord || !firstRecord.fields) {
      return [];
    }

    const fields = firstRecord.fields;
    const preferred = this.PREFERRED_ORDER.filter(field => Object.prototype.hasOwnProperty.call(fields, field));
    const fallback = Object.keys(fields).filter(key => !preferred.includes(key) && !this.hiddenFields.includes(key));
    
    return preferred.concat(fallback).slice(0, 3);
  }

  /**
   * Filter records based on search and date filters
   */
  filterRecords(records, searchQuery, dateFilter, dateField = 'Deliverable date') {
    let items = records.slice();

    // Apply date filters
    const fromTs = Utils.parseDateFilter(dateFilter.from);
    const toTs = Utils.parseDateFilter(dateFilter.to, true);

    if (fromTs != null || toTs != null) {
      // Debug: Log the date field being used
      console.log('Date filtering active:', { dateField, fromTs, toTs });
      
      items = items.filter(rec => {
        const fieldValue = rec && rec.fields ? rec.fields[dateField] : undefined;
        const recordTs = Utils.normalizeDateForSort(fieldValue);
        
        // Debug: Log first record's date field info
        if (rec === records[0]) {
          console.log('First record date field:', { 
            dateField, 
            fieldValue, 
            recordTs,
            availableFields: rec.fields ? Object.keys(rec.fields) : []
          });
        }
        
        // If no date value, exclude from filtered results when date filter is active
        if (recordTs == null) return false;
        if (fromTs != null && recordTs < fromTs) return false;
        if (toTs != null && recordTs > toTs) return false;
        return true;
      });
      
      console.log(`Date filter result: ${items.length} of ${records.length} records match`);
    }

    // Apply search filter
    const query = searchQuery.trim().toLowerCase();
    if (query) {
      const displayColumns = this.getDisplayColumns(records);
      items = items.filter(rec => {
        if (!rec || !rec.fields) return false;
        return displayColumns.some(col => {
          const value = rec.fields[col];
          if (value == null) return false;
          if (Array.isArray(value)) {
            return value.some(entry => Utils.normalizeValue(entry).toLowerCase().includes(query));
          }
          return Utils.normalizeValue(value).toLowerCase().includes(query);
        });
      });
    }

    return items;
  }

  /**
   * Sort records
   */
  sortRecords(records, sortOption) {
    if (sortOption === 'none') return records;

    const [field, direction, valueType] = sortOption.split(':');
    const multiplier = direction === 'desc' ? -1 : 1;
    const localeOptions = { sensitivity: 'base', numeric: true };

    return records.slice().sort((a, b) => {
      const aField = a && a.fields ? a.fields[field] : undefined;
      const bField = b && b.fields ? b.fields[field] : undefined;

      if (valueType === 'date') {
        const aTime = Utils.normalizeDateForSort(aField);
        const bTime = Utils.normalizeDateForSort(bField);
        if (aTime == null && bTime == null) return 0;
        if (aTime == null) return 1;
        if (bTime == null) return -1;
        return (aTime - bTime) * multiplier;
      }

      const aText = Utils.normalizeSortValue(aField);
      const bText = Utils.normalizeSortValue(bField);
      if (!aText && !bText) return 0;
      if (!aText) return 1;
      if (!bText) return -1;
      return aText.localeCompare(bText, undefined, localeOptions) * multiplier;
    });
  }

  /**
   * Process record fields for display
   */
  processRecordFields(record, primaryKeys = ['Date', 'Milestone Type', 'Deliverable'], tableSchema = null) {
    if (!record || !record.fields) {
      return { primaryFields: [], additionalFields: [] };
    }

    const fields = record.fields;
    const primaryFields = [];
    const additionalFields = [];

    // Process primary fields
    primaryKeys.forEach(key => {
      if (fields.hasOwnProperty(key)) {
        const value = Utils.normalizeValue(fields[key]);
        primaryFields.push({ key, value });
      }
    });

    // Get field order from table schema if available
    const fieldOrder = tableSchema && tableSchema.fields 
      ? tableSchema.fields.map(f => f.name)
      : Object.keys(fields);

    // Process additional fields in schema order
    fieldOrder.forEach(key => {
      if (!primaryKeys.includes(key) && !this.hiddenFields.includes(key) && fields.hasOwnProperty(key)) {
        const value = fields[key];
        const normalizedValue = Utils.normalizeValue(value);
        const attachments = this.fieldManager.extractAttachments(value);
        
        additionalFields.push({
          key,
          value: normalizedValue,
          attachments: attachments.length > 0 ? attachments : null
        });
      }
    });

    return { primaryFields, additionalFields };
  }

  /**
   * Open record editor
   */
  async openEditor(baseId, tableName, recordId) {
    if (!baseId || !tableName || !recordId) {
      this.editor.error = 'Missing required parameters for editing.';
      return;
    }

    this.editor.visible = true;
    this.editor.error = '';
    this.editor.success = '';
    this.editor.loading = true;

    try {
      // Load table schema and record data
      const [tableResponse, record] = await Promise.all([
        this.apiService.listTables(baseId),
        this.apiService.getRecord(baseId, tableName, recordId)
      ]);

      const tables = tableResponse.tables || [];
      const targetName = tableName.toLowerCase();
      let tableSchema = tables.find(table => (table.name || '').toLowerCase() === targetName);

      if (!tableSchema) {
        throw new Error(`Unable to locate the "${tableName}" table metadata.`);
      }

      this.prepareEditorFields(tableSchema, record.fields || {});
    } catch (error) {
      this.editor.error = error.message;
    } finally {
      this.editor.loading = false;
    }
  }

  /**
   * Close record editor
   */
  closeEditor() {
    this.editor.visible = false;
    this.editor.loading = false;
    this.editor.saving = false;
    this.editor.error = '';
    this.editor.success = '';
    this.editor.fields = [];
    this.editor.formValues = {};
  }

  /**
   * Prepare editor fields
   */
  prepareEditorFields(tableSchema, recordFields) {
    const formValues = {};
    const configuredFields = [];

    for (const rawField of tableSchema.fields || []) {
      const fieldConfig = this.fieldManager.buildFieldConfig(rawField, recordFields);
      configuredFields.push(fieldConfig);
      formValues[fieldConfig.name] = Utils.cloneValue(fieldConfig.initialValue);
    }

    this.editor.fields = configuredFields;
    this.editor.formValues = formValues;
  }

  /**
   * Submit record editor changes
   */
  async submitEditor(baseId, tableName, recordId) {
    if (this.editor.saving || !this.editor.fields.length) return;

    this.editor.error = '';
    this.editor.success = '';

    const updates = {};

    for (const fieldConfig of this.editor.fields) {
      if (!fieldConfig.editable) continue;
      const formValue = this.editor.formValues[fieldConfig.name];

      // Validation
      if (fieldConfig.required) {
        const isEmpty = (fieldConfig.input === 'multi-select' && (!Array.isArray(formValue) || formValue.length === 0)) ||
          (fieldConfig.input === 'checkbox' ? false : (formValue === '' || formValue === null || typeof formValue === 'undefined'));
        if (isEmpty) {
          this.editor.error = `${fieldConfig.name} is required.`;
          return;
        }
      }

      if (fieldConfig.input === 'number' && formValue !== '' && formValue !== null) {
        const numeric = Number(formValue);
        if (Number.isNaN(numeric)) {
          this.editor.error = `${fieldConfig.name} must be a valid number.`;
          return;
        }
      }

      const formattedValue = this.fieldManager.formatValueForSave(fieldConfig, formValue);
      const currentValue = fieldConfig.originalValue;

      if (this.fieldManager.fieldValuesEqual(fieldConfig, formValue, currentValue)) continue;
      updates[fieldConfig.name] = formattedValue;
    }

    if (!Object.keys(updates).length) {
      this.editor.success = 'No changes to save.';
      return;
    }

    this.editor.saving = true;
    try {
      const updatedRecord = await this.apiService.updateRecord(baseId, tableName, recordId, updates);
      this.editor.success = 'Record updated successfully.';
      
      // Update form with new values
      if (updatedRecord && updatedRecord.fields) {
        this.prepareEditorFields({ fields: this.editor.fields.map(f => ({ name: f.name, type: f.type })) }, updatedRecord.fields);
      }
      
      return updatedRecord;
    } catch (error) {
      this.editor.error = error.message;
      throw error;
    } finally {
      this.editor.saving = false;
    }
  }

  /**
   * Display field value for readonly fields
   */
  displayFieldValue(fieldConfig) {
    return this.fieldManager.displayFieldValue(fieldConfig);
  }
}
