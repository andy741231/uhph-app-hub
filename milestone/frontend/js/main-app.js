/**
 * Main Application for Airtable Manager
 */

import { ApiService } from './api-service.js';
import { RecordManager } from './record-manager.js';
import { BannerManager } from './banner-manager.js';
import { PdfService } from './pdf-service.js';
import { Utils } from './utils.js';

const { reactive } = Vue;

export class MainApp {
  constructor() {
    this.apiService = new ApiService();
    this.recordManager = new RecordManager(this.apiService);
    this.bannerManager = new BannerManager(this.apiService);
    this.pdfService = new PdfService();
    
    this.state = reactive({
      // API and services
      apiBase: this.apiService.baseUrl,

      // App state
      baseId: 'appAIxASkp5dv39O6',
      tableName: 'Milestone Activities',
      tableSchema: null,
      records: [],
      offset: '',
      loading: false,
      message: { text: '', type: '' },
      pdfError: '',
      isExportingPdf: false,

      // Filters and search
      searchQuery: '',
      sortOption: 'ID:asc:string',
      dateFilter: { from: '', to: '' },
      dateField: 'Deliverable date',

      // Banner state
      bannerDetails: {
        logoUrl: '',
        title: '',
        subtitle: '',
        description: ''
      },
      bannerError: '',
      bannerRecordId: this.bannerManager.recordId,
      bannerEditor: this.bannerManager.editor,

      // Misc
      hiddenFields: this.recordManager.hiddenFields
    });
  }

  /**
   * Initialize the application
   */
  async init() {
    try {
      await Promise.all([
        this.loadRecords(),
        this.loadBanner(),
        this.loadTableSchema()
      ]);
    } catch (error) {
      this.notify(error.message, 'error');
    }
  }

  /**
   * Load table schema
   */
  async loadTableSchema() {
    try {
      const tableResponse = await this.apiService.listTables(this.state.baseId);
      const tables = tableResponse.tables || [];
      const targetName = this.state.tableName.toLowerCase();
      this.state.tableSchema = tables.find(table => (table.name || '').toLowerCase() === targetName);
    } catch (error) {
      console.error('Failed to load table schema:', error);
    }
  }

  /**
   * Load records from Airtable
   */
  async loadRecords(next = false) {
    this.state.loading = true;
    try {
      const options = {};
      if (next && this.state.offset) options.offset = this.state.offset;

      const result = await this.recordManager.loadRecords(this.state.baseId, this.state.tableName, options);

      if (!next) {
        this.state.records = result.records;
      } else {
        this.state.records = [...this.state.records, ...result.records];
      }

      this.state.offset = result.offset || '';
      this.notify(`Loaded ${result.records.length} records${this.state.offset ? ' (more available)' : ''}`);
    } catch (error) {
      this.notify(error.message, 'error');
    } finally {
      this.state.loading = false;
    }
  }

  /**
   * Load next page of records
   */
  async loadNextPage() {
    await this.loadRecords(true);
  }

  /**
   * Load banner content
   */
  async loadBanner() {
    try {
      this.state.bannerDetails = await this.bannerManager.fetchBanner(this.state.baseId);
      this.state.bannerError = '';
      this.state.bannerRecordId = this.bannerManager.recordId;
    } catch (error) {
      this.state.bannerError = error.message;
    }
  }

  /**
   * Get display columns
   */
  get displayColumns() {
    return this.recordManager.getDisplayColumns(this.state.records);
  }

  /**
   * Get filtered records
   */
  get filteredRecords() {
    console.log('filteredRecords computed called', {
      dateFilter: this.state.dateFilter,
      dateField: this.state.dateField,
      recordCount: this.state.records.length
    });
    
    let items = this.recordManager.filterRecords(
      this.state.records,
      this.state.searchQuery,
      this.state.dateFilter,
      this.state.dateField
    );

    return this.recordManager.sortRecords(items, this.state.sortOption);
  }

  /**
   * Check if banner has content
   */
  get hasBannerContent() {
    const details = this.state.bannerDetails || {};
    return Boolean(details.logoUrl || details.title || details.subtitle || details.description);
  }

  /**
   * View record in detail page
   */
  viewRecord(id) {
    if (!id) return;
    if (!this.state.baseId || !this.state.tableName) {
      this.notify('Base and Table required to view record', 'error');
      return;
    }
    const params = new URLSearchParams({ baseId: this.state.baseId, table: this.state.tableName, id });
    window.location.href = `detail.html?${params.toString()}`;
  }

  /**
   * Delete record
   */
  async deleteRecord(id) {
    if (!id) return;
    if (!confirm('Delete this record?')) return;
    
    this.state.loading = true;
    try {
      await this.recordManager.deleteRecord(this.state.baseId, this.state.tableName, id);
      this.notify('Record deleted');
      await this.loadRecords();
    } catch (error) {
      this.notify(error.message, 'error');
    } finally {
      this.state.loading = false;
    }
  }

  /**
   * Export records to PDF
   */
  async exportPdf() {
    if (this.state.isExportingPdf) return;

    const records = this.filteredRecords.length ? this.filteredRecords : this.state.records;
    if (!records || !records.length) {
      this.state.pdfError = 'No records available to export.';
      return;
    }

    this.state.pdfError = '';
    this.state.isExportingPdf = true;

    try {
      await this.pdfService.exportToPdf(records, this.state.bannerDetails, this.state.tableSchema);
    } catch (error) {
      console.error('Failed to generate PDF', error);
      this.state.pdfError = error.message;
    } finally {
      this.state.isExportingPdf = false;
    }
  }

  /**
   * Open banner editor
   */
  async openBannerEditor() {
    await this.bannerManager.openEditor(this.state.baseId);
  }

  /**
   * Close banner editor
   */
  closeBannerEditor() {
    this.bannerManager.closeEditor();
  }

  /**
   * Submit banner editor
   */
  async submitBannerEditor() {
    try {
      const updatedDetails = await this.bannerManager.submitEditor(this.state.baseId);
      if (updatedDetails) {
        this.state.bannerDetails = updatedDetails;
        this.state.bannerRecordId = this.bannerManager.recordId;
        this.closeBannerEditor();
        this.notify('Banner updated successfully.');
      }
    } catch (error) {
      // Error is handled in banner manager
    }
  }

  /**
   * Display banner field value
   */
  displayBannerFieldValue(fieldConfig) {
    return this.bannerManager.displayFieldValue(fieldConfig);
  }

  /**
   * Show notification
   */
  notify(text, type = 'success') {
    this.state.message = { text, type };
    setTimeout(() => { 
      this.state.message = { text: '', type: '' };
    }, 3000);
  }

  /**
   * Update search query
   */
  updateSearch(query) {
    this.state.searchQuery = query;
  }

  /**
   * Update sort option
   */
  updateSort(option) {
    this.state.sortOption = option;
  }

  /**
   * Update date filter
   */
  updateDateFilter(from, to) {
    this.state.dateFilter = { from, to };
  }

  /**
   * Get Vue.js reactive data
   */
  getVueData() {
    return this.state;
  }

  /**
   * Get Vue.js computed properties
   */
  getVueComputed() {
    return {
      displayColumns: () => this.displayColumns,
      filteredRecords: () => this.filteredRecords,
      hasBannerContent: () => this.hasBannerContent
    };
  }

  /**
   * Get Vue.js methods
   */
  getVueMethods() {
    return {
      // Record operations
      loadRecords: (next = false) => this.loadRecords(next),
      loadNextPage: () => this.loadNextPage(),
      viewRecord: (id) => this.viewRecord(id),
      deleteRecord: (id) => this.deleteRecord(id),
      
      // PDF export
      exportPdf: () => this.exportPdf(),
      
      // Banner operations
      openBannerEditor: () => this.openBannerEditor(),
      closeBannerEditor: () => this.closeBannerEditor(),
      submitBannerEditor: () => this.submitBannerEditor(),
      displayBannerFieldValue: (fieldConfig) => this.displayBannerFieldValue(fieldConfig),
      
      // Utilities
      notify: (text, type) => this.notify(text, type),
      normalizeValue: (value) => Utils.normalizeValue(value)
    };
  }
}
