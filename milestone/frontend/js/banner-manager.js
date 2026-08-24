/**
 * Banner Manager for handling banner content and editing
 */

import { Utils } from './utils.js';
import { FieldManager } from './field-manager.js';

const { reactive } = Vue;

export class BannerManager {
  constructor(apiService) {
    this.apiService = apiService;
    this.fieldManager = new FieldManager();
    this.tableName = 'Banner';
    this.recordId = '';
    this.recordFields = {};
    this.tableSchema = null;
    
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
   * Fetch banner data from Airtable
   */
  async fetchBanner(baseId) {
    if (!baseId) {
      throw new Error('Base ID is missing; unable to load banner.');
    }

    try {
      const response = await this.apiService.listRecords(baseId, this.tableName, { pageSize: 1 });
      const records = response.records || [];
      
      if (records.length) {
        const bannerRecord = records[0] || {};
        const bannerFields = bannerRecord.fields || {};
        this.recordId = bannerRecord.id || '';
        this.recordFields = bannerFields;
        
        if (this.editor.visible && this.tableSchema) {
          this.prepareBannerEditorFields();
        }
        
        return this.extractBannerDetails(bannerFields);
      } else {
        this.recordId = '';
        this.recordFields = {};
        return {
          logoUrl: '',
          title: '',
          subtitle: '',
          description: ''
        };
      }
    } catch (error) {
      this.recordId = '';
      this.recordFields = {};
      throw error;
    }
  }

  /**
   * Extract banner details from fields
   */
  extractBannerDetails(fields) {
    if (!fields) {
      return {
        logoUrl: '',
        title: '',
        subtitle: '',
        description: ''
      };
    }

    const logoField = Utils.getFieldValue(fields, ['Logo', 'Banner Logo', 'Logo Url', 'Image', 'Primary Image']);
    let logoUrl = '';
    if (Array.isArray(logoField) && logoField.length) {
      const first = logoField[0];
      if (first) {
        logoUrl = first.url || (first.thumbnails && first.thumbnails.large && first.thumbnails.large.url) || '';
      }
    } else if (typeof logoField === 'string') {
      logoUrl = logoField;
    }

    return {
      logoUrl,
      title: Utils.normalizeValue(Utils.getFieldValue(fields, ['Title', 'Banner Title', 'Heading', 'Name'])) || '',
      subtitle: Utils.normalizeValue(Utils.getFieldValue(fields, ['Sub-Title', 'Subtitle', 'Tagline'])) || '',
      description: Utils.normalizeValue(Utils.getFieldValue(fields, ['Description', 'Body', 'Summary', 'Details'])) || ''
    };
  }

  /**
   * Open banner editor
   */
  async openEditor(baseId) {
    if (!baseId) {
      this.editor.error = 'Base ID is missing; cannot edit banner.';
      return;
    }

    this.editor.visible = true;
    this.editor.error = '';
    this.editor.success = '';
    this.editor.loading = true;

    try {
      if (!this.recordId) {
        await this.fetchBanner(baseId);
      }

      if (!this.recordId) {
        throw new Error('Banner record was not found.');
      }

      await this.loadBannerSchema(baseId);
      this.prepareBannerEditorFields();
    } catch (error) {
      this.editor.error = error.message;
    } finally {
      this.editor.loading = false;
    }
  }

  /**
   * Close banner editor
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
   * Load banner table schema
   */
  async loadBannerSchema(baseId, force = false) {
    if (this.tableSchema && !force) {
      return this.tableSchema;
    }

    if (!baseId) {
      throw new Error('Base ID is required to load banner schema.');
    }

    const response = await this.apiService.listTables(baseId);
    const tables = response.tables || [];
    const targetName = (this.tableName || 'Banner').toLowerCase();
    let tableSchema = tables.find(table => (table.name || '').toLowerCase() === targetName);

    if (!tableSchema && tables.length === 1) {
      tableSchema = tables[0];
    }

    if (!tableSchema) {
      throw new Error(`Unable to locate the "${this.tableName}" table metadata in Airtable.`);
    }

    this.tableSchema = tableSchema;
    return tableSchema;
  }

  /**
   * Prepare banner editor fields
   */
  prepareBannerEditorFields() {
    if (!this.tableSchema) {
      this.editor.fields = [];
      this.editor.formValues = {};
      return;
    }

    const recordFields = this.recordFields || {};
    const formValues = {};
    const configuredFields = [];

    for (const rawField of this.tableSchema.fields || []) {
      const fieldConfig = this.fieldManager.buildFieldConfig(rawField, recordFields);
      configuredFields.push(fieldConfig);
      formValues[fieldConfig.name] = Utils.cloneValue(fieldConfig.initialValue);
    }

    this.editor.fields = configuredFields;
    this.editor.formValues = formValues;
  }

  /**
   * Submit banner editor changes
   */
  async submitEditor(baseId) {
    if (this.editor.saving || !this.editor.fields.length) return;

    this.editor.error = '';
    this.editor.success = '';

    const updates = {};

    for (const fieldConfig of this.editor.fields) {
      if (!fieldConfig.editable) continue;
      const formValue = this.editor.formValues[fieldConfig.name];

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
      const updatedRecord = await this.apiService.updateRecord(baseId, this.tableName, this.recordId, updates);
      
      if (updatedRecord && updatedRecord.fields) {
        this.recordFields = updatedRecord.fields;
      } else {
        this.recordFields = { ...this.recordFields, ...updates };
      }
      
      this.editor.success = 'Banner updated successfully.';
      this.prepareBannerEditorFields();
      
      return this.extractBannerDetails(this.recordFields);
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
