/**
 * Field Manager for handling Airtable field configurations
 */

import { Utils } from './utils.js';

export class FieldManager {
  constructor() {
    this.fieldTypeMap = {
      singleLineText: 'Single line text',
      multilineText: 'Long text',
      richText: 'Rich text',
      number: 'Number',
      percent: 'Percent',
      currency: 'Currency',
      checkbox: 'Checkbox',
      singleSelect: 'Single select',
      multipleSelects: 'Multiple select',
      date: 'Date',
      dateTime: 'Date & time',
      email: 'Email',
      phoneNumber: 'Phone number',
      url: 'URL',
      rating: 'Rating'
    };

    this.nonEditableTypes = new Set([
      'formula',
      'rollup',
      'lookup',
      'count',
      'autoNumber',
      'createdTime',
      'lastModifiedTime',
      'createdBy',
      'lastModifiedBy',
      'multipleAttachments',
      'multipleRecordLinks',
      'barcode',
      'button',
      'duration'
    ]);
  }

  /**
   * Format field type for display
   */
  formatFieldType(rawField) {
    const mapped = this.fieldTypeMap[rawField.type];
    if (mapped) return mapped;
    
    if (rawField.type) {
      return rawField.type.replace(/([A-Z])/g, ' $1').replace(/^./, c => c.toUpperCase());
    }
    
    return '';
  }

  /**
   * Build field configuration for editing
   */
  buildFieldConfig(rawField, recordFields = {}) {
    const currentValue = recordFields[rawField.name];
    const baseConfig = {
      id: rawField.id || rawField.name,
      name: rawField.name,
      type: rawField.type,
      typeLabel: this.formatFieldType(rawField),
      description: rawField.description || '',
      required: Boolean(rawField.options && rawField.options.isRequired),
      editable: false,
      readOnlyReason: '',
      input: 'text',
      inputAttrs: {},
      choices: [],
      initialValue: currentValue,
      originalValue: currentValue
    };

    // Helper functions
    const readOnly = (reason) => ({
      ...baseConfig,
      editable: false,
      readOnlyReason: reason || 'This field type cannot be edited from this view.'
    });

    const editable = (input, attrs = {}) => ({
      ...baseConfig,
      editable: true,
      input,
      inputAttrs: attrs
    });

    // Handle different field types
    switch (rawField.type) {
      case 'singleLineText':
      case 'email':
      case 'phoneNumber':
      case 'url':
        return {
          ...editable('text', this.getInputAttrs(rawField.type)),
          initialValue: typeof currentValue === 'string' ? currentValue : ''
        };

      case 'multilineText':
      case 'richText':
        return {
          ...editable('textarea'),
          initialValue: typeof currentValue === 'string' ? currentValue : ''
        };

      case 'number':
      case 'percent':
      case 'currency':
      case 'rating':
        return {
          ...editable('number', { step: 'any' }),
          initialValue: typeof currentValue === 'number' ? currentValue : currentValue || ''
        };

      case 'checkbox':
        return {
          ...editable('checkbox'),
          initialValue: Boolean(currentValue)
        };

      case 'singleSelect':
        return {
          ...editable('single-select'),
          choices: this.extractChoices(rawField),
          initialValue: typeof currentValue === 'string' ? currentValue : ''
        };

      case 'multipleSelects':
        return {
          ...editable('multi-select'),
          choices: this.extractChoices(rawField),
          initialValue: Array.isArray(currentValue) ? [...currentValue] : []
        };

      case 'date':
        return {
          ...editable('date'),
          initialValue: typeof currentValue === 'string' ? currentValue.slice(0, 10) : ''
        };

      case 'dateTime':
        return {
          ...editable('datetime'),
          initialValue: this.dateTimeToLocal(currentValue)
        };

      default:
        if (this.nonEditableTypes.has(rawField.type)) {
          const airtableUrl = rawField.name === 'Attachment'
            ? 'https://airtable.com/appAIxASkp5dv39O6/tbl3gRlcjLqjMJe9W/viwJHCFL6SnytJ3Eg?blocks=hide'
            : 'https://airtable.com/appAIxASkp5dv39O6/tblFznPQqvKqlT4GD/viwjFzqwIEFZbp5iT?blocks=hide';
          return readOnly(`<a target="_blank" href="${airtableUrl}">go to airtable to edit this field</a>`);
        }
        return readOnly();
    }
  }

  /**
   * Get input attributes based on field type
   */
  getInputAttrs(fieldType) {
    switch (fieldType) {
      case 'url': return { type: 'url' };
      case 'email': return { type: 'email' };
      case 'phoneNumber': return { type: 'tel' };
      default: return {};
    }
  }

  /**
   * Extract choices from field options
   */
  extractChoices(rawField) {
    if (!rawField.options || !Array.isArray(rawField.options.choices)) {
      return [];
    }
    
    return rawField.options.choices
      .map(choice => choice && (choice.name || choice))
      .filter(Boolean);
  }

  /**
   * Convert datetime to local format
   */
  dateTimeToLocal(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const offsetMinutes = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offsetMinutes * 60000);
    return local.toISOString().slice(0, 16);
  }

  /**
   * Convert local datetime to ISO
   */
  dateTimeToIso(value) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    return date.toISOString();
  }

  /**
   * Check if field values are equal
   */
  fieldValuesEqual(fieldConfig, newValue, currentValue) {
    switch (fieldConfig.input) {
      case 'number': {
        const a = newValue === '' || newValue === null ? null : Number(newValue);
        const b = typeof currentValue === 'number' ? currentValue : (currentValue === '' ? null : Number(currentValue));
        if (Number.isNaN(a) && Number.isNaN(b)) return true;
        return a === b;
      }
      case 'checkbox':
        return Boolean(newValue) === Boolean(currentValue);
      case 'multi-select': {
        const arrA = Array.isArray(newValue) ? [...newValue] : [];
        const arrB = Array.isArray(currentValue) ? [...currentValue] : [];
        if (arrA.length !== arrB.length) return false;
        return arrA.every((val, idx) => val === arrB[idx]);
      }
      case 'date':
        return (newValue || '') === ((currentValue || '').slice ? (currentValue || '').slice(0, 10) : '');
      case 'datetime': {
        const iso = newValue ? this.dateTimeToIso(newValue) : null;
        const normalizedCurrent = currentValue ? new Date(currentValue).toISOString() : null;
        return iso === normalizedCurrent;
      }
      default:
        return (newValue ?? '') === (currentValue ?? '');
    }
  }

  /**
   * Format value for saving to Airtable
   */
  formatValueForSave(fieldConfig, value) {
    switch (fieldConfig.input) {
      case 'number':
        if (value === '' || value === null || Number.isNaN(Number(value))) return null;
        return Number(value);
      case 'checkbox':
        return Boolean(value);
      case 'multi-select':
        return Array.isArray(value) ? value.filter(Boolean) : [];
      case 'single-select':
        return value || null;
      case 'date':
        return value || null;
      case 'datetime':
        return this.dateTimeToIso(value);
      default:
        return typeof value === 'string' ? value : (value == null ? null : value);
    }
  }

  /**
   * Display field value for readonly fields
   */
  displayFieldValue(fieldConfig) {
    const value = fieldConfig.originalValue;
    if (Array.isArray(value)) {
      if (value.length && typeof value[0] === 'object') {
        return value.map(item => {
          if (!item) return '';
          // Special handling for Collaborator field
          if (item.name && item.email) {
            return `${item.name} - ${item.email}`;
          }
          return item.name || item.filename || item.url || JSON.stringify(item);
        }).join(', ');
      }
      return value.join(', ');
    }
    if (typeof value === 'boolean') return value ? 'True' : 'False';
    return Utils.normalizeValue(value) || '—';
  }

  /**
   * Extract attachments from field value
   */
  extractAttachments(value) {
    const items = [];
    const addItem = (entry, fallbackName = 'Attachment') => {
      if (!entry) return;
      if (typeof entry === 'string') {
        const url = entry;
        if (/^https?:\/\//i.test(url)) {
          items.push({ name: fallbackName, url });
        }
        return;
      }
      if (typeof entry === 'object') {
        const url = entry.url || entry.href || entry.link;
        if (!url) return;
        const name = entry.name || entry.filename || entry.title || fallbackName;
        items.push({ name, url });
      }
    };

    if (Array.isArray(value)) {
      value.forEach((entry, idx) => addItem(entry, `Attachment ${idx + 1}`));
    } else if (value) {
      addItem(value);
    }
    return items;
  }
}
